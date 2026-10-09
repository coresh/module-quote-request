"""Regression coverage for exact upstream provenance and missing-source reporting."""
import importlib.util
import unittest
from pathlib import Path


MODULE = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("native_reference", MODULE / "tools/fetch_class_reference.py")
REFERENCE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(REFERENCE)


class NativeDeclarationToolsTest(unittest.TestCase):
    def entry(self, name="Magento\\Backend\\Block\\Template", tag="2.4.8-p5"):
        file, url = REFERENCE.source_for(name, tag)
        return {"file": file, "source_url": url, "sha256": "a" * 64}

    def manifest(self):
        name = "Magento\\Backend\\Block\\Template"
        return {"schema": 1, "tag": "2.4.8-p5", "classes": {name: self.entry(name)}}

    def test_framework_mapping_preserves_official_internal_root(self):
        file, url = REFERENCE.source_for("Magento\\Framework\\View\\Element\\Template", "2.4.9")
        self.assertEqual("lib/internal/Magento/Framework/View/Element/Template.php", file)
        self.assertEqual(f"https://raw.githubusercontent.com/magento/magento2/2.4.9/{file}", url)

    def test_module_mapping_preserves_official_app_code_root(self):
        self.assertEqual("app/code/Magento/Backend/Block/Template.php", self.entry()["file"])

    def test_console_6_pin_matches_248_requirement(self):
        entry = self.entry("Symfony\\Component\\Console\\Command\\Command")
        self.assertIn("/symfony/console/v6.4.0/Command/Command.php", entry["source_url"])

    def test_console_7_pin_matches_249_requirement(self):
        entry = self.entry("Symfony\\Component\\Console\\Command\\Command", "2.4.9")
        self.assertIn("/symfony/console/v7.4.0/Command/Command.php", entry["source_url"])

    def test_psr_log_has_explicit_primary_source(self):
        self.assertEqual("https://raw.githubusercontent.com/php-fig/log/3.0.0/src/LoggerInterface.php",
                         self.entry("Psr\\Log\\LoggerInterface")["source_url"])

    def test_unknown_dependency_is_not_replaced_by_a_stub(self):
        self.assertIsNone(REFERENCE.source_for("Unverified\\UnknownDependency", "2.4.9"))

    def test_invalid_class_names_are_rejected(self):
        for name in ("Magento\\..\\Template", "Magento\\Backend/Template", "Magento\\Backend\\Template\n", "Magento"):
            with self.subTest(name=name):
                self.assertIsNone(REFERENCE.source_for(name, "2.4.9"))

    def test_unverified_tag_is_rejected(self):
        self.assertIsNone(REFERENCE.source_for("Magento\\Backend\\Block\\Template", "develop"))

    def test_missing_class_records_ignore_invalid_json_and_duplicates(self):
        stderr = 'MISSING_CLASS "Magento\\\\Backend\\\\Block\\\\Template"\nMISSING_CLASS invalid\nMISSING_CLASS null\nMISSING_CLASS "Magento\\\\Backend\\\\Block\\\\Template"\n'
        self.assertEqual({"Magento\\Backend\\Block\\Template"}, REFERENCE.missing_classes(stderr))

    def test_valid_manifest_is_accepted(self):
        REFERENCE.validate_manifest(self.manifest(), "2.4.8-p5")

    def test_wrong_target_tag_is_rejected(self):
        with self.assertRaises(ValueError):
            REFERENCE.validate_manifest(self.manifest(), "2.4.9")

    def test_unverified_source_url_is_rejected(self):
        manifest = self.manifest()
        next(iter(manifest["classes"].values()))["source_url"] = "https://example.invalid/Template.php"
        with self.assertRaises(ValueError):
            REFERENCE.validate_manifest(manifest, "2.4.8-p5")

    def test_source_path_traversal_is_rejected(self):
        manifest = self.manifest()
        next(iter(manifest["classes"].values()))["file"] = "../../Template.php"
        with self.assertRaises(ValueError):
            REFERENCE.validate_manifest(manifest, "2.4.8-p5")

    def test_invalid_hash_is_rejected(self):
        manifest = self.manifest()
        next(iter(manifest["classes"].values()))["sha256"] = "unknown"
        with self.assertRaises(ValueError):
            REFERENCE.validate_manifest(manifest, "2.4.8-p5")

    def test_stale_console_dependency_pin_is_rejected(self):
        name = "Symfony\\Component\\Console\\Command\\Command"
        manifest = {"schema": 1, "tag": "2.4.9", "classes": {name: self.entry(name, "2.4.8-p5")}}
        with self.assertRaises(ValueError):
            REFERENCE.validate_manifest(manifest, "2.4.9")


if __name__ == "__main__":
    unittest.main()
