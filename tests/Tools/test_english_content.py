"""Behavioral checks for the package's residual-Cyrillic scanner."""
from __future__ import annotations

import contextlib
import importlib.util
import io
import json
import tempfile
import unittest
from pathlib import Path

SOURCE = Path(__file__).resolve().parents[2] / "tools/check_english_content.py"
SPEC = importlib.util.spec_from_file_location("english_content_checker", SOURCE)
checker = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(checker)


class EnglishContentTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)

    def test_english_and_non_cyrillic_unicode_pass(self):
        (self.root / "document.txt").write_text("Professional English. Café. \u03bb\n", encoding="utf-8")
        report = checker.scan(self.root)
        self.assertEqual("PASS", report["status"])
        self.assertEqual(1, report["utf8_files_scanned"])
        self.assertEqual([], report["findings"])

    def test_cyrillic_content_reports_exact_positions(self):
        (self.root / "document.txt").write_text("English\nA\u042fB\u044f\n", encoding="utf-8")
        report = checker.scan(self.root)
        self.assertEqual("FAIL", report["status"])
        self.assertEqual([
            {"path": "document.txt", "kind": "content", "line": 2, "column": 2, "codepoint": "U+042F"},
            {"path": "document.txt", "kind": "content", "line": 2, "column": 4, "codepoint": "U+044F"},
        ], report["findings"])

    def test_cyrillic_directory_and_extensionless_filename_are_checked(self):
        directory = self.root / "\u042f"
        directory.mkdir()
        (directory / "\u044f").write_text("English", encoding="utf-8")
        report = checker.scan(self.root)
        self.assertEqual(2, report["names_scanned"])
        self.assertEqual(2, len(report["findings"]))
        self.assertTrue(all(item["kind"] == "name" for item in report["findings"]))

    def test_no_vendor_hidden_or_report_directory_exclusion(self):
        for name in ("vendor", ".hidden", "tests/reports"):
            directory = self.root / name
            directory.mkdir(parents=True)
            (directory / "sample.data").write_text("\u042f", encoding="utf-8")
        report = checker.scan(self.root)
        self.assertEqual(3, report["utf8_files_scanned"])
        self.assertEqual(3, len(report["findings"]))

    def test_explicit_output_is_only_omission_and_names_are_checked(self):
        output = self.root / "\u042f.json"
        output.write_text("\u042f", encoding="utf-8")
        (self.root / "other.json").write_text("\u042f", encoding="utf-8")
        report = checker.scan(self.root, output)
        self.assertEqual(1, len(report["omitted_output"]))
        self.assertEqual(1, report["utf8_files_scanned"])
        self.assertEqual({"name", "content"}, {item["kind"] for item in report["findings"]})

    def test_non_utf8_content_fails_instead_of_silent_skip(self):
        (self.root / "binary.dat").write_bytes(b"\xff\xfe")
        report = checker.scan(self.root)
        self.assertEqual("FAIL", report["status"])
        self.assertEqual(1, len(report["unscanned"]))
        self.assertEqual(0, report["utf8_files_scanned"])

    def test_symlink_is_reported_and_not_followed(self):
        (self.root / "target.txt").write_text("English", encoding="utf-8")
        (self.root / "link.txt").symlink_to(self.root / "target.txt")
        report = checker.scan(self.root)
        self.assertEqual("FAIL", report["status"])
        self.assertEqual(1, len(report["unscanned"]))
        self.assertEqual(1, report["utf8_files_scanned"])

    def test_extended_blocks_and_combining_cyrillic_are_detected(self):
        for codepoint in (0x0400, 0x052F, 0x1C80, 0x2DE0, 0xA640, 0x1E030, 0xFE2E):
            with self.subTest(codepoint=codepoint):
                self.assertTrue(checker.is_cyrillic(chr(codepoint)))
        self.assertFalse(checker.is_cyrillic("é"))

    def test_escape_notation_is_language_neutral(self):
        (self.root / "fixture.py").write_text('value = "\\u042f"\n', encoding="utf-8")
        self.assertEqual("PASS", checker.scan(self.root)["status"])

    def test_cli_writes_json_with_failure_exit(self):
        (self.root / "source.txt").write_text("\u042f", encoding="utf-8")
        output = self.root / "report.json"
        with contextlib.redirect_stdout(io.StringIO()):
            result = checker.main(["--module", str(self.root), "--output", str(output)])
        self.assertEqual(1, result)
        report = json.loads(output.read_text(encoding="utf-8"))
        self.assertEqual(checker.CHECKER, report["checker"])
        self.assertEqual("FAIL", report["status"])
        self.assertNotIn("\u042f", output.read_text(encoding="utf-8"))

    def test_cli_replaces_own_stale_report_without_repeating_findings(self):
        output = self.root / "report.json"
        output.write_text(json.dumps({"checker": checker.CHECKER, "findings": ["\u042f"]}, ensure_ascii=False), encoding="utf-8")
        (self.root / "source.txt").write_text("English", encoding="utf-8")
        with contextlib.redirect_stdout(io.StringIO()):
            result = checker.main(["--module", str(self.root), "--output", str(output)])
        self.assertEqual(0, result)
        report = json.loads(output.read_text(encoding="utf-8"))
        self.assertEqual([], report["findings"])
        self.assertEqual(1, len(report["omitted_output"]))

    def test_cli_refuses_to_overwrite_authored_file(self):
        output = self.root / "composer.json"
        output.write_text('{"name":"example/module"}\n', encoding="utf-8")
        before = output.read_bytes()
        with contextlib.redirect_stderr(io.StringIO()), self.assertRaises(SystemExit) as exception:
            checker.main(["--module", str(self.root), "--output", str(output)])
        self.assertEqual(2, exception.exception.code)
        self.assertEqual(before, output.read_bytes())

    def test_output_symlink_and_symlink_parent_are_rejected(self):
        target = self.root / "target"
        target.mkdir()
        alias = self.root / "alias"
        alias.symlink_to(target, target_is_directory=True)
        with self.assertRaises(ValueError):
            checker.validate_output(alias / "report.json")
        output = self.root / "report.json"
        output.symlink_to(target / "report.json")
        with self.assertRaises(ValueError):
            checker.validate_output(output)


if __name__ == "__main__":
    unittest.main()
