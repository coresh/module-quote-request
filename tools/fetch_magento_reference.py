#!/usr/bin/env python3
"""Fetch only official tagged XSDs for offline checks; never installs a Magento runtime."""
import argparse
import concurrent.futures
import re
import urllib.request
from pathlib import Path
from lxml import etree


def urn_path(urn):
    match = re.fullmatch(r"urn:magento:module:(Magento_[A-Za-z]+):(.+)", urn)
    if match:
        return Path("app/code/Magento") / match.group(1)[8:] / match.group(2)
    match = re.fullmatch(r"urn:magento:framework:(.+)", urn)
    return Path("lib/internal/Magento/Framework") / match.group(1) if match else None


def main():
    args = argparse.ArgumentParser()
    args.add_argument("--tag", required=True, choices=["2.4.8-p5", "2.4.9"])
    args.add_argument("--destination", required=True, type=Path)
    args.add_argument("--module", type=Path, default=Path(__file__).resolve().parents[1])
    opt = args.parse_args()
    ns = "{http://www.w3.org/2001/XMLSchema-instance}noNamespaceSchemaLocation"
    pending = set()
    for path in opt.module.rglob("*.xml"):
        root = etree.parse(str(path)).getroot()
        resolved = urn_path(root.get(ns, ""))
        if resolved:
            pending.add(resolved)
    seen = set()
    failed = []
    while pending:
        batch = pending - seen
        if not batch:
            break
        seen.update(batch)

        def fetch(path):
            local = opt.destination / path
            try:
                if local.is_file():
                    data = local.read_bytes()
                else:
                    url = f"https://raw.githubusercontent.com/magento/magento2/{opt.tag}/{path.as_posix()}"
                    data = urllib.request.urlopen(url, timeout=30).read()
                    local.parent.mkdir(parents=True, exist_ok=True)
                    local.write_bytes(data)
                refs = []
                root = etree.fromstring(data)
                for item in root.findall("{http://www.w3.org/2001/XMLSchema}include") + root.findall("{http://www.w3.org/2001/XMLSchema}import") + root.findall("{http://www.w3.org/2001/XMLSchema}redefine"):
                    location = item.get("schemaLocation", "")
                    candidate = urn_path(location)
                    if not candidate and location and ":" not in location:
                        candidate = Path(__import__('posixpath').normpath((path.parent / location).as_posix()))
                    if candidate:
                        refs.append(candidate)
                return path, refs, None
            except Exception as exc:
                return path, [], str(exc)

        pending = set()
        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            for path, refs, error in pool.map(fetch, batch):
                if error:
                    failed.append((str(path), error))
                pending.update(refs)
    print(f"Tag {opt.tag}: {len(seen) - len(failed)} XSDs fetched; {len(failed)} unavailable")
    for path, error in failed:
        print(path, error)
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
