#!/usr/bin/env python3
"""Find residual Cyrillic in package names and UTF-8 content without allowlists.

This checks character coverage, not English grammar or translation quality.
The selected JSON output is the only content exclusion, and is reported.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import unicodedata
from datetime import datetime, timezone
from pathlib import Path

CHECKER = "coresh-quote-request-english-content"
# Include newer Unicode blocks even when Python's Unicode database predates them.
CYRILLIC_RANGES = (
    (0x0400, 0x052F), (0x1C80, 0x1C8F), (0x2DE0, 0x2DFF),
    (0xA640, 0xA69F), (0x1E030, 0x1E08F),
)


def is_cyrillic(character: str) -> bool:
    codepoint = ord(character)
    return any(start <= codepoint <= end for start, end in CYRILLIC_RANGES) or (
        "CYRILLIC" in unicodedata.name(character, "")
    )


def positions(text: str):
    """Yield one-based line/column positions, with no source-text disclosure."""
    line = column = 1
    for character in text:
        if is_cyrillic(character):
            yield {"line": line, "column": column, "codepoint": f"U+{ord(character):04X}"}
        if character == "\n":
            line += 1
            column = 1
        else:
            column += 1


def scan(root: Path, output: Path | None = None) -> dict:
    root = root.resolve()
    output = output.absolute() if output is not None else None
    findings, unreadable, omitted = [], [], []
    files_scanned = names_scanned = 0

    def error(exception):
        unreadable.append({"path": str(exception.filename), "reason": str(exception)})

    for directory, directories, files in os.walk(root, followlinks=False, onerror=error):
        directories.sort()
        files.sort()
        for name in directories + files:
            path = Path(directory) / name
            relative = path.relative_to(root).as_posix()
            names_scanned += 1
            for position in positions(name):
                findings.append({"path": relative, "kind": "name", **position})
            if path.is_symlink():
                unreadable.append({"path": relative, "reason": "Symlinks are not followed; content was not scanned."})
                continue
            if path.is_dir():
                continue
            if not path.is_file():
                unreadable.append({"path": relative, "reason": "Not a regular file; content was not scanned."})
                continue
            if output is not None and path.absolute() == output:
                omitted.append({"path": relative, "reason": "Explicit --output report; omitted to prevent stale self-findings."})
                continue
            try:
                content = path.read_bytes().decode("utf-8")
            except (OSError, UnicodeDecodeError) as exception:
                unreadable.append({"path": relative, "reason": str(exception)})
                continue
            files_scanned += 1
            for position in positions(content):
                findings.append({"path": relative, "kind": "content", **position})

    return {
        "checker": CHECKER,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "root": str(root),
        "status": "PASS" if not findings and not unreadable else "FAIL",
        "scope": "All file/directory names and all UTF-8 file contents; no extension or directory allowlist.",
        "limitation": "Character scan only; does not assess translation quality or prove English-language content.",
        "names_scanned": names_scanned,
        "utf8_files_scanned": files_scanned,
        "findings": findings,
        "unscanned": unreadable,
        "omitted_output": omitted,
    }


def validate_output(path: Path) -> None:
    """Do not follow symlinks or overwrite an existing authored package file."""
    for component in (path, *path.parents):
        if component.is_symlink():
            raise ValueError("The output path or a parent is a symlink.")
    if path.exists():
        try:
            previous = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, ValueError) as exception:
            raise ValueError("Refusing to overwrite an existing file that is not this checker's report.") from exception
        if not isinstance(previous, dict) or previous.get("checker") != CHECKER:
            raise ValueError("Refusing to overwrite an existing file that is not this checker's report.")


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--module", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--output", type=Path, help="JSON report; replaces only this checker's existing reports")
    args = parser.parse_args(argv)
    root = args.module.resolve()
    if not root.is_dir():
        parser.error("--module must be an existing directory")
    if args.output is not None:
        args.output = args.output.absolute()
        try:
            validate_output(args.output)
        except ValueError as exception:
            parser.error(str(exception))
    report = scan(root, args.output)
    if args.output is not None:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, ensure_ascii=True, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({key: report[key] for key in ("status", "names_scanned", "utf8_files_scanned")} | {
        "findings": len(report["findings"]), "unscanned": len(report["unscanned"]),
        "omitted_output": len(report["omitted_output"]),
    }))
    return 0 if report["status"] == "PASS" else 1


if __name__ == "__main__":
    raise SystemExit(main())
