#!/usr/bin/env python3
"""Repeatable static checks. Does not install Magento or execute storefront operations."""
from __future__ import annotations

import argparse
import json
import re
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

from lxml import etree


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--module", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--output", type=Path)
    parser.add_argument("--xsd-root", type=Path, help="Checked-out Magento source root for URN XSD validation")
    args = parser.parse_args()
    root = args.module.resolve()
    checks = []

    def record(name, status, detail):
        checks.append({"check": name, "status": status, "detail": detail})

    def command(name, argv):
        result = subprocess.run(argv, cwd=root, capture_output=True, text=True)
        record(name, "PASS" if result.returncode == 0 else "FAIL", {
            "command": argv, "exit_code": result.returncode,
            "stdout": result.stdout.strip(), "stderr": result.stderr.strip(),
        })

    manifest_path = root / "composer.json"
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        expected = manifest.get("name") == "coresh/module-quote-request" and manifest.get("type") == "magento2-module"
        expected &= manifest.get("autoload", {}).get("psr-4", {}).get("Coresh\\QuoteRequest\\") in ("", "./")
        expected &= "registration.php" in manifest.get("autoload", {}).get("files", [])
        record("Composer metadata and module identity", "PASS" if expected else "FAIL", manifest.get("name"))
    except (OSError, ValueError) as exc:
        record("Composer JSON", "FAIL", str(exc))

    composer = shutil.which("composer")
    if composer:
        command("composer validate --strict", [composer, "validate", "--strict"])
    else:
        record("composer validate --strict", "NOT RUN", "composer executable is unavailable")

    php = shutil.which("php")
    for path in sorted(set(root.rglob("*.php")) | set(root.rglob("*.phtml"))):
        if "vendor" in path.parts:
            continue
        if php:
            command("PHP syntax: " + str(path.relative_to(root)), [php, "-l", str(path)])
        else:
            record("PHP syntax: " + str(path.relative_to(root)), "NOT RUN", "PHP executable unavailable")

    node = shutil.which("node")
    for path in sorted(root.rglob("*.js")):
        if node:
            command("JavaScript syntax: " + str(path.relative_to(root)), [node, "--check", str(path)])
        else:
            record("JavaScript syntax: " + str(path.relative_to(root)), "NOT RUN", "Node executable unavailable")

    xml_parser = etree.XMLParser(resolve_entities=False, no_network=True)
    if args.xsd_root:
        class MagentoUrnResolver(etree.Resolver):
            def resolve(self, url, public_id, context):
                candidate = None
                match = re.fullmatch(r"urn:magento:module:(Magento_[A-Za-z]+):(.+)", url)
                if match:
                    candidate = args.xsd_root / "app/code/Magento" / match.group(1)[8:] / match.group(2)
                match = re.fullmatch(r"urn:magento:framework:(.+)", url)
                if match:
                    candidate = args.xsd_root / "lib/internal/Magento/Framework" / match.group(1)
                if candidate is not None and candidate.is_file():
                    return self.resolve_filename(str(candidate), context)
                return None
        xml_parser.resolvers.add(MagentoUrnResolver())
    xsi = "{http://www.w3.org/2001/XMLSchema-instance}noNamespaceSchemaLocation"
    for path in sorted(root.rglob("*.xml")):
        if "vendor" in path.parts:
            continue
        label = str(path.relative_to(root))
        try:
            tree = etree.parse(str(path), xml_parser)
            record("XML well-formed: " + label, "PASS", "Parsed without resolving external entities")
            urn = tree.getroot().get(xsi, "")
            if not urn:
                continue
            if not args.xsd_root:
                record("Magento XSD: " + label, "NOT RUN", "Pass --xsd-root with target Magento source")
                continue
            # Magento URNs are not HTTP schema addresses. Resolve only known local roots.
            schema_path = None
            module_match = re.fullmatch(r"urn:magento:module:(Magento_[A-Za-z]+):(.+)", urn)
            framework_match = re.fullmatch(r"urn:magento:framework:(.+)", urn)
            if module_match:
                schema_path = args.xsd_root / "app/code/Magento" / module_match.group(1)[8:] / module_match.group(2)
            elif framework_match:
                schema_path = args.xsd_root / "lib/internal/Magento/Framework" / framework_match.group(1)
            if schema_path is None or not schema_path.is_file():
                record("Magento XSD: " + label, "NOT RUN", "Target XSD is not available: " + urn)
                continue
            schema = etree.XMLSchema(etree.parse(str(schema_path), xml_parser))
            schema.assertValid(tree)
            record("Magento XSD: " + label, "PASS", str(schema_path))
        except (OSError, etree.Error) as exc:
            record("XML validation: " + label, "FAIL", str(exc))

    totals = {status: sum(c["status"] == status for c in checks) for status in ("PASS", "FAIL", "NOT RUN")}
    report = {"generated_at": datetime.now(timezone.utc).isoformat(), "checks": checks, "totals": totals}
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(totals))
    for check in checks:
        if check["status"] == "FAIL":
            print(check["check"], check["detail"], file=sys.stderr)
    return 1 if totals["FAIL"] else 0


if __name__ == "__main__":
    raise SystemExit(main())
