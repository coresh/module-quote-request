# SPDX-License-Identifier: GPL-3.0-or-later
"""Exercise the real PHP checker against isolated source/autoload fixtures.

Composer ClassLoader and ComponentRegistrar below are explicit test doubles.
No Magento application, HTTP, database, generated DI or FPM is executed.
"""
import hashlib
import json
import os
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path


MODULE = Path(__file__).resolve().parents[2]
SCRIPT = MODULE / "tools/check_installed_release.php"
PHP = os.environ.get("PHP_BINARY", "php")
VERIFIED_SOURCE_SHA256 = {
    "Observer/EnforcePurchasingPolicy.php": "5a0feacbc173df0c53dd9d0bcfd897e76167d8676c9f9f24c4a442c5ec3562b8",
    "Observer/BlockPurchasingRoutes.php": "abba995ebfda20466d790e0d97004d2222fb4af44a4fc6b173e248017096febb",
    "etc/frontend/events.xml": "37a79a4359b37cb45eee828f8f2ee8be268af39974000e9c0cba0e1127bf1d00",
    "view/adminhtml/ui_component/product_form.xml": "b8b1def1dacc26491a1c3ae2ead042f66592ad213c60f02372499876530a1775",
    "etc/adminhtml/di.xml": "b96cef7defdd6abf4d3e88a0907dcfbeb0291590935d1f42af977786ab3df286",
}
REQUIRED = (
    "Observer/EnforcePurchasingPolicy.php", "Controller/Request/Submit.php",
    "Model/Service/SubmitQuoteRequest.php", "etc/events.xml", "registration.php", "composer.json",
    "Observer/BlockPurchasingRoutes.php", "etc/frontend/events.xml",
    "view/adminhtml/ui_component/product_form.xml", "etc/adminhtml/di.xml",
)
LEGACY_ADMIN_SOURCE = (
    ("view/adminhtml/ui_component/product_form.xml", "product_form.xml",
     "bc0b63ff912c14e2ca90681eefd16bbeeeb5c960ff93c38ed97655b3cbb566f6",
     "reference_product_form_configuration_not_verified_fix"),
    ("etc/adminhtml/di.xml", "adminhtml_di.xml",
     "d7b324a6dee35dae59777dcf675466001c1ca1969708855522253cfb1106db49",
     "reference_admin_collection_factory_not_verified_fix"),
)


class InstalledReleaseTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.reference = self.root / "trusted"
        self.reference.mkdir()
        for name in REQUIRED:
            path = self.reference / name
            path.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(MODULE / name, path)
        self.write(self.reference, "view/frontend/web/js/quote-request.js", "// fixture client asset\n")
        self.write(self.reference, "view/frontend/templates/modal.phtml", "<form></form>\n")
        self.write(self.reference, "docs/report.md", "Reference documentation only\n")
        self.write(self.reference, "tests/result.json", '{"scope":"isolated"}\n')
        self.write(self.reference, "NOTICE.md", "Attribution\n")
        self.write(self.reference, "i18n/README.md", "Translation documentation only\n")
        self.manifest()
        self.installed = self.root / "installed"
        shutil.copytree(self.reference, self.installed)

    def write(self, root, relative, content):
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8")
        return path

    def manifest(self):
        paths = sorted(path for path in self.reference.rglob("*") if path.is_file() and path.name != "SHA256SUMS")
        (self.reference / "SHA256SUMS").write_text("".join(
            f"{hashlib.sha256(path.read_bytes()).hexdigest()}  {path.relative_to(self.reference).as_posix()}\n"
            for path in paths
        ), encoding="utf-8")

    def run_checker(self, *options, expected_exit=0, reference=True):
        arguments = [PHP, str(SCRIPT)]
        if reference:
            arguments.append(f"--expected-root={self.reference}")
        arguments.extend(options or (f"--module-root={self.installed}",))
        process = subprocess.run(arguments, text=True, capture_output=True, timeout=15, check=False)
        self.assertEqual(expected_exit, process.returncode, process.stdout + process.stderr)
        self.assertEqual("", process.stderr)
        report = json.loads(process.stdout)
        self.assertIn("PHP_FPM_loaded_classes_and_OPcache", report["not_verified"])
        self.assertIn("does not confirm", report["notice"])
        return report

    def codes(self, report):
        return {error["code"] for error in report["errors"]}

    def magento_fixture(self, registered=None, resolved=None, autoload_extra=""):
        magento = self.root / "magento"
        magento.mkdir(exist_ok=True)
        registered = registered or magento / "vendor/coresh/module-quote-request"
        if not registered.exists():
            shutil.copytree(self.reference, registered)
        resolved = resolved or registered / "Observer/EnforcePurchasingPolicy.php"
        # These method signatures match Composer findFile() and native registrar
        # instance getPath(); the fixture never loads the Observer class itself.
        source = f'''<?php
namespace Composer\\Autoload {{
    class ClassLoader {{
        public function findFile($class) {{
            return $class === 'Coresh\\\\QuoteRequest\\\\Observer\\\\EnforcePurchasingPolicy'
                ? {json.dumps(str(resolved))} : false;
        }}
    }}
}}
namespace Magento\\Framework\\Component {{
    class ComponentRegistrar {{
        const MODULE = 'module';
        private static $paths = [];
        public static function register($type, $name, $path) {{
            if (isset(self::$paths[$type][$name])) {{ throw new \\LogicException('duplicate fixture module'); }}
            self::$paths[$type][$name] = $path;
        }}
        public function getPath($type, $name) {{ return self::$paths[$type][$name] ?? null; }}
    }}
}}
namespace {{
    \\Magento\\Framework\\Component\\ComponentRegistrar::register('module', 'Coresh_QuoteRequest', {json.dumps(str(registered))});
    {autoload_extra}
    return new \\Composer\\Autoload\\ClassLoader();
}}
'''
        self.write(magento, "vendor/autoload.php", source)
        return magento

    def test_fixture_uses_the_verified_production_fixes(self):
        for relative, expected_hash in VERIFIED_SOURCE_SHA256.items():
            with self.subTest(file=relative):
                self.assertEqual(expected_hash, hashlib.sha256((self.reference / relative).read_bytes()).hexdigest())

    def test_matching_source_files_return_files_match_with_limits(self):
        report = self.run_checker()
        self.assertEqual("FILES_MATCH", report["status"])
        self.assertEqual(12, report["checked_runtime_files"])
        self.assertEqual(str(self.installed), report["paths"]["module_root"])
        self.assertFalse(report["composer_registration_files_executed"])
        self.assertIn("installed_Composer_and_ComponentRegistrar_resolution", report["not_verified"])

    def test_target_cannot_be_used_as_its_own_trusted_reference(self):
        alias = self.root / "reference-alias"
        alias.symlink_to(self.reference, target_is_directory=True)
        for target in (self.reference, alias):
            with self.subTest(target=target.name):
                report = self.run_checker(f"--module-root={target}", expected_exit=2)
                self.assertIn("reference_and_target_roots_must_differ", self.codes(report))

    def test_check_does_not_change_source_files(self):
        before = {str(path): (path.read_bytes(), path.stat().st_mtime_ns) for path in self.root.rglob("*") if path.is_file()}
        self.run_checker()
        after = {str(path): (path.read_bytes(), path.stat().st_mtime_ns) for path in self.root.rglob("*") if path.is_file()}
        self.assertEqual(before, after)

    def test_missing_server_docs_tests_and_notice_are_accepted(self):
        shutil.rmtree(self.installed / "docs")
        shutil.rmtree(self.installed / "tests")
        (self.installed / "NOTICE.md").unlink()
        (self.installed / "i18n/README.md").unlink()
        (self.installed / "SHA256SUMS").unlink()
        self.assertEqual("FILES_MATCH", self.run_checker()["status"])

    def test_old_observer_is_rejected(self):
        self.write(self.installed, REQUIRED[0], "<?php $request = $controller->getRequest();\n")
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_changed", "file": REQUIRED[0]}, report["errors"])

    def test_legacy_observer_body_cannot_replace_the_compatibility_shim(self):
        relative = "Observer/BlockPurchasingRoutes.php"
        self.write(self.installed, relative, "<?php class BlockPurchasingRoutes { function execute($event) { $event->getControllerAction()->getRequest(); } }\n")
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_changed", "file": relative}, report["errors"])

    def test_missing_new_purchasing_observer_is_rejected(self):
        (self.installed / REQUIRED[0]).unlink()
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_missing", "file": REQUIRED[0]}, report["errors"])

    def test_missing_legacy_compatibility_shim_is_rejected(self):
        relative = "Observer/BlockPurchasingRoutes.php"
        (self.installed / relative).unlink()
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_missing", "file": relative}, report["errors"])

    def test_missing_frontend_event_registration_is_rejected(self):
        relative = "etc/frontend/events.xml"
        (self.installed / relative).unlink()
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_missing", "file": relative}, report["errors"])

    def test_enabled_legacy_global_observer_registration_is_rejected(self):
        relative = "etc/frontend/events.xml"
        path = self.installed / relative
        original = path.read_text()
        self.assertIn('disabled="true"', original)
        path.write_text(original.replace('disabled="true"', 'disabled="false"'))
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_changed", "file": relative}, report["errors"])

    def test_mixed_runtime_release_is_rejected(self):
        self.write(self.installed, REQUIRED[1], "<?php // stale controller\n")
        self.write(self.installed, "view/frontend/web/js/quote-request.js", "// stale client\n")
        report = self.run_checker(expected_exit=1)
        self.assertEqual(2, len(report["errors"]))
        self.assertEqual({"installed_runtime_file_changed"}, self.codes(report))

    def test_missing_runtime_file_is_rejected(self):
        (self.installed / "etc/events.xml").unlink()
        self.assertIn("installed_runtime_file_missing", self.codes(self.run_checker(expected_exit=1)))

    def test_extra_installed_runtime_file_is_rejected(self):
        self.write(self.installed, "Controller/Request/Debug.php", "<?php // customization\n")
        self.assertIn("installed_unlisted_runtime_file", self.codes(self.run_checker(expected_exit=1)))

    def test_changed_translation_csv_is_rejected(self):
        self.write(self.reference, "i18n/en_US.csv", '"Quote Request","Quote Request"\n')
        self.manifest()
        self.write(self.installed, "i18n/en_US.csv", '"Quote Request","Changed Label"\n')
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_changed", "file": "i18n/en_US.csv"}, report["errors"])

    def test_missing_translation_csv_is_rejected(self):
        self.write(self.reference, "i18n/en_US.csv", '"Quote Request","Quote Request"\n')
        self.manifest()
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_missing", "file": "i18n/en_US.csv"}, report["errors"])

    def test_unlisted_reference_runtime_file_is_rejected(self):
        self.write(self.reference, "Model/Unlisted.php", "<?php\n")
        self.assertIn("reference_unlisted_runtime_file", self.codes(self.run_checker(expected_exit=2)))

    def test_reference_hash_mismatch_is_rejected(self):
        self.write(self.reference, "etc/events.xml", "<changed/>\n")
        self.assertIn("reference_file_missing_or_changed", self.codes(self.run_checker(expected_exit=2)))

    def test_reference_missing_runtime_file_is_rejected(self):
        (self.reference / "etc/events.xml").unlink()
        self.assertIn("reference_file_missing_or_changed", self.codes(self.run_checker(expected_exit=2)))

    def test_reference_observer_must_be_the_pinned_fix(self):
        self.write(self.reference, REQUIRED[0], "<?php // unverified source\n")
        self.manifest()
        self.assertIn("reference_observer_not_verified_fix", self.codes(self.run_checker(expected_exit=2)))

    def test_reference_legacy_observer_must_be_the_pinned_shim(self):
        self.write(self.reference, "Observer/BlockPurchasingRoutes.php", "<?php // obsolete observer body\n")
        self.manifest()
        self.assertIn("reference_legacy_observer_not_verified_shim", self.codes(self.run_checker(expected_exit=2)))

    def test_reference_route_event_wiring_must_be_the_pinned_fix(self):
        path = self.reference / "etc/frontend/events.xml"
        path.write_text(path.read_text().replace('disabled="true"', 'disabled="false"'))
        self.manifest()
        self.assertIn("reference_route_observer_registration_not_verified_fix", self.codes(self.run_checker(expected_exit=2)))

    def test_legacy_admin_snapshots_match_the_exact_116_release_sources(self):
        # These are unchanged snapshots from the delivered 1.1.6 archive,
        # rather than invented substitutes for the two reported admin failures.
        for relative, fixture, expected_hash, _ in LEGACY_ADMIN_SOURCE:
            with self.subTest(file=relative):
                source = MODULE / "tests/Tools/fixtures/1.1.6" / fixture
                self.assertEqual(expected_hash, hashlib.sha256(source.read_bytes()).hexdigest())

    def test_installed_legacy_product_form_is_rejected(self):
        relative, fixture, _, _ = LEGACY_ADMIN_SOURCE[0]
        shutil.copyfile(MODULE / "tests/Tools/fixtures/1.1.6" / fixture, self.installed / relative)
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_changed", "file": relative}, report["errors"])

    def test_installed_legacy_shared_collection_factory_override_is_rejected(self):
        relative, fixture, _, _ = LEGACY_ADMIN_SOURCE[1]
        shutil.copyfile(MODULE / "tests/Tools/fixtures/1.1.6" / fixture, self.installed / relative)
        report = self.run_checker(expected_exit=1)
        self.assertIn({"code": "installed_runtime_file_changed", "file": relative}, report["errors"])

    def test_rebuilt_reference_manifest_cannot_trust_legacy_product_form(self):
        relative, fixture, _, code = LEGACY_ADMIN_SOURCE[0]
        shutil.copyfile(MODULE / "tests/Tools/fixtures/1.1.6" / fixture, self.reference / relative)
        self.manifest()
        report = self.run_checker(expected_exit=2)
        self.assertIn({"code": code, "file": relative}, report["errors"])

    def test_rebuilt_reference_manifest_cannot_trust_legacy_shared_collection_factory_override(self):
        relative, fixture, _, code = LEGACY_ADMIN_SOURCE[1]
        shutil.copyfile(MODULE / "tests/Tools/fixtures/1.1.6" / fixture, self.reference / relative)
        self.manifest()
        report = self.run_checker(expected_exit=2)
        self.assertIn({"code": code, "file": relative}, report["errors"])

    def test_missing_installed_admin_fix_files_are_rejected(self):
        for relative, _, _, _ in LEGACY_ADMIN_SOURCE:
            with self.subTest(file=relative):
                original = (self.installed / relative).read_bytes()
                (self.installed / relative).unlink()
                report = self.run_checker(expected_exit=1)
                self.assertIn({"code": "installed_runtime_file_missing", "file": relative}, report["errors"])
                (self.installed / relative).write_bytes(original)

    def test_reference_with_only_legacy_observer_and_global_wiring_is_rejected(self):
        (self.reference / REQUIRED[0]).unlink()
        self.write(self.reference, "Observer/BlockPurchasingRoutes.php", "<?php class BlockPurchasingRoutes {}\n")
        self.write(self.reference, "etc/frontend/events.xml", '<config><event name="controller_action_predispatch"><observer name="coresh_quote_request_block_purchase_routes" instance="Coresh\\QuoteRequest\\Observer\\BlockPurchasingRoutes"/></event></config>\n')
        self.manifest()
        report = self.run_checker(expected_exit=2)
        self.assertIn({"code": "reference_manifest_missing_required_file", "file": REQUIRED[0]}, report["errors"])
        self.assertTrue({
            "reference_observer_not_verified_fix", "reference_legacy_observer_not_verified_shim",
            "reference_route_observer_registration_not_verified_fix",
        }.issubset(self.codes(report)))

    def test_reference_manifest_requires_critical_runtime_entries(self):
        manifest = self.reference / "SHA256SUMS"
        original = manifest.read_text()
        for relative in REQUIRED:
            with self.subTest(file=relative):
                manifest.write_text("\n".join(line for line in original.splitlines() if not line.endswith("  " + relative)) + "\n")
                report = self.run_checker(expected_exit=2)
                self.assertIn({"code": "reference_manifest_missing_required_file", "file": relative}, report["errors"])

    def test_manifest_traversal_absolute_empty_duplicate_and_invalid_hash_are_rejected(self):
        manifest = self.reference / "SHA256SUMS"
        original = manifest.read_text()
        invalid = [
            "a" * 64 + "  ../outside.php\n", "a" * 64 + "  /absolute.php\n",
            "a" * 64 + "  etc//events.xml\n", "a" * 64 + "  ./composer.json\n",
            "a" * 64 + "  etc/../composer.json\n", original.splitlines()[0] + "\n",
            "invalid  composer.json\n", "a" * 64 + "  SHA256SUMS\n",
        ]
        for line in invalid:
            with self.subTest(line=line):
                manifest.write_text(original + line)
                self.assertIn("reference_manifest_invalid", self.codes(self.run_checker(expected_exit=2)))

    def test_reference_manifest_symlink_escape_is_rejected(self):
        outside = self.write(self.root, "outside-manifest", (self.reference / "SHA256SUMS").read_text())
        (self.reference / "SHA256SUMS").unlink()
        (self.reference / "SHA256SUMS").symlink_to(outside)
        self.assertIn("reference_manifest_missing_or_unsafe", self.codes(self.run_checker(expected_exit=2)))

    def test_reference_runtime_symlink_escape_is_rejected(self):
        source = self.reference / "etc/events.xml"
        outside = self.write(self.root, "outside.xml", source.read_text())
        source.unlink()
        source.symlink_to(outside)
        self.assertIn("reference_manifest_invalid", self.codes(self.run_checker(expected_exit=2)))

    def test_installed_runtime_symlink_escape_is_rejected(self):
        source = self.installed / "etc/events.xml"
        outside = self.write(self.root, "outside.xml", source.read_text())
        source.unlink()
        source.symlink_to(outside)
        self.assertIn("installed_unsafe_file", self.codes(self.run_checker(expected_exit=1)))

    def test_symlink_cycle_is_rejected_without_following_forever(self):
        (self.installed / "loop").symlink_to(self.installed, target_is_directory=True)
        self.assertIn("installed_unsafe_file", self.codes(self.run_checker(expected_exit=1)))

    def test_missing_module_root_is_rejected(self):
        self.assertIn("module_root_missing", self.codes(self.run_checker(f"--module-root={self.root / 'absent'}", expected_exit=2)))

    def test_malformed_duplicate_and_conflicting_arguments_are_rejected(self):
        cases = [
            ("--unknown=value",), ("--module-root",), ("--module-root=",),
            (f"--module-root={self.installed}", f"--module-root={self.installed}"),
            (f"--module-root={self.installed}", f"--magento-root={self.root}"),
        ]
        for arguments in cases:
            with self.subTest(arguments=arguments):
                self.assertTrue(self.run_checker(*arguments, expected_exit=2)["errors"])

    def test_missing_reference_manifest_is_rejected(self):
        (self.reference / "SHA256SUMS").unlink()
        self.assertIn("reference_manifest_missing_or_unsafe", self.codes(self.run_checker(expected_exit=2)))

    def test_missing_autoload_is_rejected(self):
        self.assertIn("composer_autoload_missing", self.codes(self.run_checker(f"--magento-root={self.root}", expected_exit=2)))

    def test_missing_magento_root_is_rejected(self):
        self.assertIn("magento_root_missing", self.codes(self.run_checker(f"--magento-root={self.root / 'absent'}", expected_exit=2)))

    def test_missing_reference_root_is_rejected(self):
        report = self.run_checker(f"--expected-root={self.root / 'absent'}", f"--module-root={self.installed}", reference=False, expected_exit=2)
        self.assertIn("reference_root_missing", self.codes(report))

    def test_missing_target_argument_is_rejected(self):
        result = subprocess.run([PHP, str(SCRIPT), f"--expected-root={self.reference}"], capture_output=True, text=True, timeout=15, check=False)
        self.assertEqual(2, result.returncode)
        self.assertEqual("", result.stderr)
        self.assertIn("exactly_one_target_root_required", self.codes(json.loads(result.stdout)))

    def test_magento_resolves_matching_registration_and_composer_source(self):
        magento = self.magento_fixture()
        report = self.run_checker(f"--magento-root={magento}")
        self.assertEqual("FILES_MATCH", report["status"])
        self.assertTrue(report["composer_registration_files_executed"])
        self.assertEqual(str(magento / "vendor/coresh/module-quote-request"), report["paths"]["registered_module_root"])
        self.assertEqual(str(magento / "vendor/coresh/module-quote-request/Observer/EnforcePurchasingPolicy.php"), report["paths"]["autoload_observer_file"])

    def test_new_observer_class_is_required_from_composer_resolution(self):
        magento = self.magento_fixture()
        autoload = magento / "vendor/autoload.php"
        original = autoload.read_text()
        self.assertIn("EnforcePurchasingPolicy", original)
        autoload.write_text(original.replace("EnforcePurchasingPolicy", "BlockPurchasingRoutes"))
        report = self.run_checker(f"--magento-root={magento}", expected_exit=1)
        self.assertIn("registered_and_autoload_paths_differ", self.codes(report))
        self.assertIsNone(report["paths"]["autoload_observer_file"])

    def test_composer_resolution_does_not_load_or_instantiate_observer_classes(self):
        magento = self.magento_fixture(autoload_extra="spl_autoload_register(static function ($class) { if (str_starts_with($class, 'Coresh\\\\')) { throw new \\RuntimeException('Observer loading is forbidden in this fixture'); } });")
        report = self.run_checker(f"--magento-root={magento}")
        self.assertEqual("FILES_MATCH", report["status"])

    def test_duplicate_known_copies_are_rejected_before_autoload(self):
        magento = self.magento_fixture()
        shutil.copytree(self.reference, magento / "app/code/Coresh/QuoteRequest")
        report = self.run_checker(f"--magento-root={magento}", expected_exit=1)
        self.assertIn("duplicate_installed_module_copies", self.codes(report))
        self.assertFalse(report["composer_registration_files_executed"])
        self.assertEqual(2, len(report["paths"]["known_installed_copies"]))

    def test_path_repository_symlinks_to_same_module_are_accepted(self):
        magento = self.magento_fixture(registered=self.installed)
        vendor = magento / "vendor/coresh/module-quote-request"
        vendor.parent.mkdir(parents=True, exist_ok=True)
        vendor.symlink_to(self.installed, target_is_directory=True)
        app = magento / "app/code/Coresh/QuoteRequest"
        app.parent.mkdir(parents=True, exist_ok=True)
        app.symlink_to(self.installed, target_is_directory=True)
        report = self.run_checker(f"--magento-root={magento}")
        self.assertEqual("FILES_MATCH", report["status"])
        self.assertEqual({str(self.installed)}, set(report["paths"]["known_installed_copies"].values()))

    def test_autoload_and_registered_module_path_difference_is_rejected(self):
        magento = self.magento_fixture(resolved=self.installed / REQUIRED[0])
        report = self.run_checker(f"--magento-root={magento}", expected_exit=1)
        self.assertIn("registered_and_autoload_paths_differ", self.codes(report))
        self.assertIsNotNone(report["paths"]["registered_module_root"])
        self.assertIsNotNone(report["paths"]["autoload_observer_file"])

    def test_known_copy_and_external_registered_path_difference_is_rejected(self):
        magento = self.magento_fixture(registered=self.installed)
        shutil.copytree(self.reference, magento / "vendor/coresh/module-quote-request")
        self.assertIn("known_and_registered_module_paths_differ", self.codes(self.run_checker(f"--magento-root={magento}", expected_exit=1)))

    def test_missing_resolved_observer_is_rejected(self):
        magento = self.magento_fixture(resolved=self.root / "absent.php")
        self.assertIn("registered_and_autoload_paths_differ", self.codes(self.run_checker(f"--magento-root={magento}", expected_exit=1)))

    def test_autoload_failure_is_json_and_hides_exception_text(self):
        magento = self.magento_fixture(autoload_extra="echo 'SECRET_OUTPUT'; throw new \\RuntimeException('SECRET_EXCEPTION');")
        report = self.run_checker(f"--magento-root={magento}", expected_exit=1)
        self.assertIn("composer_registration_or_resolution_failed", self.codes(report))
        self.assertNotIn("SECRET", json.dumps(report))

    def test_autoload_warning_is_json_and_hides_warning_text(self):
        magento = self.magento_fixture(autoload_extra="trigger_error('SECRET_WARNING', E_USER_WARNING);")
        report = self.run_checker(f"--magento-root={magento}", expected_exit=1)
        self.assertIn("composer_registration_or_resolution_failed", self.codes(report))
        self.assertNotIn("SECRET", json.dumps(report))

    def test_missing_registered_module_is_rejected(self):
        magento = self.magento_fixture()
        autoload = magento / "vendor/autoload.php"
        autoload.write_text(autoload.read_text().replace("return self::$paths[$type][$name] ?? null;", "return null;"))
        self.assertIn("registered_module_missing", self.codes(self.run_checker(f"--magento-root={magento}", expected_exit=1)))

    def test_missing_component_registrar_is_rejected(self):
        magento = self.root / "magento"
        self.write(magento, "vendor/autoload.php", "<?php namespace Composer\\Autoload; class ClassLoader {} return new ClassLoader();\n")
        self.assertIn("component_registrar_unavailable", self.codes(self.run_checker(f"--magento-root={magento}", expected_exit=1)))

    def test_unreadable_known_copy_symlink_is_rejected(self):
        magento = self.root / "magento"
        path = magento / "vendor/coresh/module-quote-request"
        path.parent.mkdir(parents=True)
        path.symlink_to(self.root / "absent", target_is_directory=True)
        report = self.run_checker(f"--magento-root={magento}", expected_exit=1)
        self.assertIn("known_module_copy_unreadable", self.codes(report))
        self.assertFalse(report["composer_registration_files_executed"])

    def test_non_composer_loader_is_rejected(self):
        magento = self.root / "magento"
        self.write(magento, "vendor/autoload.php", "<?php return new stdClass();\n")
        self.assertIn("composer_loader_unavailable", self.codes(self.run_checker(f"--magento-root={magento}", expected_exit=1)))

    def test_help_is_json_with_both_modes_and_limits(self):
        report = self.run_checker("--help", reference=False)
        self.assertEqual("USAGE", report["status"])
        self.assertEqual(3, len(report["usage"]))

    def test_inclusion_does_not_run_cli_or_touch_autoload(self):
        script = f"require {json.dumps(str(SCRIPT))}; echo 'included';"
        result = subprocess.run([PHP, "-r", script], capture_output=True, text=True, timeout=15, check=False)
        self.assertEqual(0, result.returncode)
        self.assertEqual("included", result.stdout)
        self.assertEqual("", result.stderr)


if __name__ == "__main__":
    unittest.main()
