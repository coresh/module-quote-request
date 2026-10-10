# Running tests and checks

From the module root, with PHP 8.3+ and PHPUnit 10.5+:

```sh
phpunit -c phpunit.xml.dist --log-junit unit-junit.xml
node tests/Frontend/quote-request.test.cjs
node tests/Frontend/inline-form.test.cjs
node tests/Frontend/button-events.test.cjs
node tests/Frontend/grid-export.test.cjs
python3 tools/static_checks.py --output static-validation.json
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s tests/Tools -p 'test_*.py' -v
python3 tools/check_english_content.py --output english-content.json
```

The default PHPUnit bootstrap uses explicitly identified minimal framework test doubles. It executes the module's actual policies and controllers; it does not verify Magento dependency injection, interception, database behavior, or browser rendering. To run unit tests with the framework from an installed target Magento version, set `MAGENTO_UNIT_AUTOLOAD` to its `vendor/autoload.php`. Never substitute `tests/Fixtures/isolated_framework.php` for the production framework.

Node executes the actual RequireJS modules with isolated DOM, jQuery, and customer-data test doubles. The modal and inline-form tests cover retries, runtime form keys, idempotency tokens, safe error text, duplicate submissions, and private-profile changes. Actual Luma, browser, CSP, and full-page-cache checks require a separate deployment.

The English-content check scans every file and directory name, and the contents of every UTF-8 file, for Cyrillic characters. It has no extension or directory allowlist. The exact `--output` path is omitted from content scanning to avoid including a previous generated report in its own findings; this omission is recorded explicitly. Non-UTF-8 files and symlinks are reported, rather than silently skipped. This check identifies residual Cyrillic text; it cannot assess translation quality or prove that every phrase is English. Unicode regression fixtures use non-Cyrillic characters or language-neutral Unicode escape sequences.

XSD validation requires Python 3 and `lxml`. Fetch the official XSD files, including their includes, imports, and redefinitions:

```sh
python3 tools/fetch_magento_reference.py --tag 2.4.8-p5 --destination reference/2.4.8-p5
python3 tools/static_checks.py --xsd-root reference/2.4.8-p5 --output static-248p5.json
python3 tools/fetch_magento_reference.py --tag 2.4.9 --destination reference/2.4.9
python3 tools/static_checks.py --xsd-root reference/2.4.9 --output static-249.json
```

`tests/Integration` requires the actual Magento test harness. All 23 integration-test assets were **NOT RUN** in the preparation environment: 17 declared methods and six inherited Magento backend ACL checks. They cover schema, snapshots, persistence, CSRF, email leases, status isolation, native EAV store overrides and Use Default, export and mass-status ACLs, and form keys in the POST body. Configure `dev/tests/integration/phpunit.xml` and a dedicated test database separately. **Warning: never use a production database for these tests.**

```sh
MAGENTO_TEST_ENVIRONMENT=1 MAGENTO_TEST_ROOT=/path/to/disposable/magento bash tools/run-magento-checks.sh
```

The runner upgrades the disposable test deployment twice, compiles dependency injection, deploys static content, and invokes the integration harness. It is not a complete functional test suite. Additional acceptance checks are listed in `docs/TEST_REPORT.md`: mail capture, admin ACLs, REST/GraphQL purchasing guards, stores, browser and theme behavior, CSP, concurrent requests, and legacy import.

To perform a limited API comparison against an official checkout of the target tag:

```sh
python3 tools/check_source_contracts.py --tag 2.4.9 --source-root /path/to/magento2 --output source-contracts-249.json
```

This check compares 16 source signatures or patterns. It does not execute dependency injection, plugins, or EAV persistence. Development-toolchain dependency resolution was checked separately without installing packages; see `tests/reports/dev-toolchain-resolution.json`. PHPUnit 10.5.66 was executed. The Composer-selected PHPUnit 12.5.38 was not executed.

## Native class declaration checks

The following checker loads all production PHP class declarations in separate processes using actual upstream parent, interface and trait files. These are source files, not replacements for Magento in production. Constructors, the Magento bootstrap, DI compilation and interceptors are not executed.

```sh
python3 tools/fetch_class_reference.py --tag 2.4.8-p5 --destination /path/to/reference/2.4.8-p5 --php php --output tests/reports/native-declarations-2.4.8-p5.json
python3 tools/fetch_class_reference.py --tag 2.4.9 --destination /path/to/reference/2.4.9 --php php --output tests/reports/native-declarations-2.4.9.json
```

Use `--offline` for a subsequent run with complete existing references. The manifest pins the official Magento tag and the selected third-party sources, and records SHA-256 hashes. The PHP loader verifies paths and source hashes. Unknown or unavailable declaration dependencies are reported rather than replaced with generic stubs. The selected Symfony Console sources are 6.4.0 and 7.4.0 for the respective target constraints; this is not a full Composer dependency installation or a check of every allowed dependency patch.

The new block fixture models the native parent's mutable protected `formKey` property for the isolated regression suite. The separate native declaration check uses the actual source instead. Two added integration methods require real object creation, parent initialization, persisted requests, template escaping and POST forms; they remain NOT RUN until the Magento integration harness is executed.

## Configuration and button regressions — 1.1.3

The full isolated PHP suite contains 607 tests/1842 assertions. The new configuration contract checks protect both path-derived default email IDs and old aliases. Backend configuration validation runs before save; native persistence and Save Config dispatch remain unverified. `AdminButtonActionsTest` executes the actual five mutation controllers with controlled request/resource/transport/session dependencies. Its 82 tests/591 assertions are included in the full total.

`button-events.test.cjs` adds 19 actual-AMD event/output cases. With the modal (17), inline (10) and grid (10) cases, there are 56 unique JavaScript scenarios. To exercise the four inherited grid button-click cases, pass the actual installed or downloaded upstream Magento AMD parent:

```sh
node tests/Frontend/grid-export.test.cjs view/adminhtml/web/js/grid/export.js /path/to/magento/app/code/Magento/Ui/view/base/web/js/grid/export.js
```

In a Composer deployment that last path is normally `vendor/magento/module-ui/view/base/web/js/grid/export.js`. The actual parent methods execute with DOM, jQuery, underscore and uiElement doubles; this does not initialize Magento UI or a browser. Without that optional third argument only the six base grid scenarios run.

The native configuration-option report executes real core XML conversion and option lookup for both target tags. External reference sources and the authoring probe are not shipped; the file-contract unit tests and complete `ConfigSectionTest` integration asset are shipped. The integration asset includes two declared methods and two inherited backend ACL checks, all NOT RUN here. See `docs/CONFIGURATION_FIX.md` and the current validation report.
