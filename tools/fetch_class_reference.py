#!/usr/bin/env python3
"""Fetch native declaration dependencies and probe with real PHP, without Magento DI.

Only classes actually requested by PHP's native loader are fetched. Magento files
come from the selected official tag. Third-party declarations use explicit pinned
primary-source repositories. This is not a Composer install or a Magento runtime.
"""
from __future__ import annotations

import argparse
import concurrent.futures
import hashlib
import json
import re
import subprocess
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

SUPPORTED_TAGS = ("2.4.8-p5", "2.4.9")
EXTERNAL_SOURCES = (
    ("Symfony\\Component\\Console\\", "symfony/console", {"2.4.8-p5": "v6.4.0", "2.4.9": "v7.4.0"}, "", "vendor/symfony/console/"),
    ("Psr\\Log\\", "php-fig/log", "3.0.0", "src/", "vendor/psr/log/src/"),
)


def source_for(name: str, tag: str) -> tuple[str, str] | None:
    if tag not in SUPPORTED_TAGS or not re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]*(?:\\[A-Za-z_][A-Za-z0-9_]*)+", name):
        return None
    if name.startswith("Magento\\Framework\\"):
        file = "lib/internal/" + name.replace("\\", "/") + ".php"
        return file, f"https://raw.githubusercontent.com/magento/magento2/{tag}/{file}"
    if name.startswith("Magento\\"):
        file = "app/code/" + name.replace("\\", "/") + ".php"
        return file, f"https://raw.githubusercontent.com/magento/magento2/{tag}/{file}"
    for prefix, repository, version, remote_root, local_root in EXTERNAL_SOURCES:
        if name.startswith(prefix):
            if isinstance(version, dict):
                version = version[tag]
            suffix = name[len(prefix):].replace("\\", "/") + ".php"
            return local_root + suffix, f"https://raw.githubusercontent.com/{repository}/{version}/{remote_root}{suffix}"
    return None


def missing_classes(stderr: str) -> set[str]:
    result = set()
    for line in stderr.splitlines():
        if line.startswith("MISSING_CLASS "):
            try:
                name = json.loads(line[len("MISSING_CLASS "):])
            except json.JSONDecodeError:
                continue
            if isinstance(name, str):
                result.add(name)
    return result


def validate_manifest(manifest: dict, tag: str) -> None:
    """Reject mixed tag/vendor mappings and unsafe source paths before loading."""
    if manifest.get("schema") != 1 or manifest.get("tag") != tag or not isinstance(manifest.get("classes"), dict):
        raise ValueError("Existing manifest has a different schema/tag or invalid classes.")
    for name, entry in manifest["classes"].items():
        expected = source_for(name, tag)
        if not isinstance(entry, dict) or expected is None or (entry.get("file"), entry.get("source_url")) != expected:
            raise ValueError(f"Source manifest mapping is not the pinned primary source: {name}")
        if not isinstance(entry.get("sha256"), str) or not re.fullmatch(r"[a-f0-9]{64}", entry["sha256"]):
            raise ValueError(f"Source manifest hash is invalid: {name}")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--tag", required=True, choices=SUPPORTED_TAGS)
    parser.add_argument("--destination", required=True, type=Path)
    parser.add_argument("--module", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--php", default="php")
    parser.add_argument("--cache-root", action="append", default=[], type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--offline", action="store_true", help="Require existing manifest/files; perform no network fetch.")
    args = parser.parse_args()
    checker = args.module / "tools/check_class_declarations.php"
    args.destination.mkdir(parents=True, exist_ok=True)
    manifest_path = args.destination / "class_manifest.json"
    manifest = {"schema": 1, "tag": args.tag, "classes": {}}
    if manifest_path.exists():
        manifest = json.loads(manifest_path.read_text())
        validate_manifest(manifest, args.tag)
    classes = json.loads(subprocess.check_output([args.php, str(checker), "--module", str(args.module), "--list"], text=True))
    unavailable = {}

    def save_manifest() -> None:
        manifest_path.write_text(json.dumps(manifest, indent=2, sort_keys=True) + "\n")

    def probe(item: dict) -> dict:
        completed = subprocess.run([args.php, str(checker), "--module", str(args.module), "--reference", str(args.destination),
                                    "--class", item["class"]], text=True, capture_output=True, timeout=30)
        try:
            data = json.loads(completed.stdout)
        except json.JSONDecodeError:
            data = {"class": item["class"], "status": "FAIL", "error": completed.stderr.strip() or completed.stdout.strip()}
        data["file"] = item["file"]
        data["exit_code"] = completed.returncode
        data["missing_classes"] = sorted(missing_classes(completed.stderr))
        if completed.returncode != 0:
            data["status"] = "FAIL"
        return data

    def fetch(name: str) -> tuple[str, dict | None, str | None]:
        resolved = source_for(name, args.tag)
        if resolved is None:
            return name, None, "No explicitly pinned primary-source mapping."
        file, url = resolved
        local = args.destination / file
        try:
            if local.is_file():
                data = local.read_bytes()
            else:
                cache = next((root / file for root in args.cache_root if (root / file).is_file()), None)
                if cache is not None:
                    data = cache.read_bytes()
                elif args.offline:
                    return name, None, "Reference file unavailable in offline mode."
                else:
                    data = urllib.request.urlopen(url, timeout=30).read()
                if not data.lstrip().startswith(b"<?php"):
                    return name, None, "Fetched source is not PHP."
                local.parent.mkdir(parents=True, exist_ok=True)
                local.write_bytes(data)
            return name, {"file": file, "source_url": url, "sha256": hashlib.sha256(data).hexdigest()}, None
        except (OSError, urllib.error.URLError) as error:
            return name, None, str(error)

    save_manifest()
    results = []
    for iteration in range(80):
        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            results = list(pool.map(probe, classes))
        needed = {name for result in results for name in result["missing_classes"]
                  if name not in manifest["classes"] and name not in unavailable}
        if not needed:
            break
        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            for name, entry, error in pool.map(fetch, sorted(needed)):
                if entry is not None:
                    manifest["classes"][name] = entry
                else:
                    unavailable[name] = error
        save_manifest()
        print(f"Round {iteration + 1}: {len(needed)} requested, {len(manifest['classes'])} native upstream sources available", flush=True)
    else:
        raise SystemExit("Dependency discovery exceeded its bounded rounds.")
    for result in results:
        if result["status"] != "PASS" and result["missing_classes"]:
            result["status"] = "NOT RUN"
            result["unavailable_dependencies"] = {name: unavailable.get(name, "Unavailable") for name in result["missing_classes"]}
    totals = {status: sum(result["status"] == status for result in results) for status in ("PASS", "FAIL", "NOT RUN")}
    report = {"generated_at": datetime.now(timezone.utc).isoformat(), "tag": args.tag,
              "php_version": subprocess.check_output([args.php, "-r", "echo PHP_VERSION;"], text=True),
              "scope": "Native application class declarations against unmodified upstream parent/interface/trait sources only. Constructors, DI compilation, interceptors, database, framework bootstrap and runtime behavior NOT RUN.",
              "upstream_source_count": len(manifest["classes"]), "upstream_sources": manifest["classes"],
              "totals": totals, "checks": results}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2) + "\n")
    print(json.dumps(totals))
    for result in results:
        if result["status"] != "PASS":
            print(result["status"], result["class"], result.get("error", ""))
    return 1 if totals["FAIL"] or totals["NOT RUN"] else 0


if __name__ == "__main__":
    raise SystemExit(main())
