# Running tests and checks

From the module root, with PHP 8.3+ and PHPUnit 10.5+:

```sh
phpunit -c phpunit.xml.dist --log-junit tests/reports/unit-junit.xml
node tests/Frontend/quote-request.test.cjs
node tests/Frontend/inline-form.test.cjs
node tests/Frontend/button-events.test.cjs
node tests/Frontend/submit-boundary.test.cjs
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

`tests/Integration` requires the actual Magento test harness. All 26 integration-test assets were **NOT RUN** in the preparation environment: 20 declared methods and six inherited Magento backend ACL checks. They cover schema, snapshots, persistence, CSRF, email leases, status isolation, native EAV store overrides and Use Default, export and mass-status ACLs, and form keys in the POST body. Configure `dev/tests/integration/phpunit.xml` and a dedicated test database separately. **Warning: never use a production database for these tests.**

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

The 1.1.3 suite contained 607 tests/1842 assertions; those cases remain in the current 701-test/5459-assertion suite. The new configuration contract checks protect both path-derived default email IDs and old aliases. Backend configuration validation runs before save; native persistence and Save Config dispatch remain unverified. `AdminButtonActionsTest` executes the actual five mutation controllers with controlled request/resource/transport/session dependencies. Its 82 tests/591 assertions are included in the full total.

`button-events.test.cjs` adds 19 actual-AMD event/output cases. With the modal (17), inline (10) and grid (10) cases, there were 56 unique JavaScript scenarios before the additional 34 submission-boundary cases in 1.1.4 (90 current unique scenarios). To exercise the four inherited grid button-click cases, pass the actual installed or downloaded upstream Magento AMD parent:

```sh
node tests/Frontend/grid-export.test.cjs view/adminhtml/web/js/grid/export.js /path/to/magento/app/code/Magento/Ui/view/base/web/js/grid/export.js
```

In a Composer deployment that last path is normally `vendor/magento/module-ui/view/base/web/js/grid/export.js`. The actual parent methods execute with DOM, jQuery, underscore and uiElement doubles; this does not initialize Magento UI or a browser. Without that optional third argument only the six base grid scenarios run.

The native configuration-option report executes real core XML conversion and option lookup for both target tags. External reference sources and the authoring probe are not shipped; the file-contract unit tests and complete `ConfigSectionTest` integration asset are shipped. The integration asset includes two declared methods and two inherited backend ACL checks, all NOT RUN here. See `docs/CONFIGURATION_FIX.md` and the current validation report.

## Submission regressions — 1.1.4

The current suite includes `BlockPurchasingRoutesTest` (37 cases/244 assertions) and `FrontendSubmitPipelineTest` (57 cases/3373 assertions). The route suite uses an explicit request/response/event fixture and real purchasing policy cases; the old controller-getter defect is covered by modern actions without those methods. The pipeline executes the actual controller/service, validation, catalogue/eligibility/display/date policies, rate limiter, resource idempotency lookup and both email senders, with explicit framework/session/catalogue/database/transport dependencies.

`submit-boundary.test.cjs` executes the actual quote AMD handlers with the shared DOM/jQuery/AJAX harness. Eight payloads are captured and checked against the shipped fixture. URL-encoding is performed by a documented deterministic test encoder rather than real jQuery HTTP transport. PHP consumes those fields through `parse_str`. Six actual controller responses are recorded and tested through popup and inline handlers (12 of the 34 new client cases). The normal PHP run verifies recorded responses against current execution; the normal Node run verifies payload captures and consumes those responses. No sockets, real browser, Magento bootstrap, MySQL transactions or SMTP are involved.

The fixtures are shipped and ordinary tests do not regenerate them. After an intentional relevant source change, regenerate and reverify in this order:

```sh
node tests/Frontend/submit-boundary.test.cjs --write-fixtures
CORESH_CAPTURE_SUBMIT_RESPONSES=tests/Fixtures/frontend-submit-responses.json phpunit -c phpunit.xml.dist --filter FrontendSubmitPipelineTest
phpunit -c phpunit.xml.dist --log-junit tests/reports/unit-junit.xml
node tests/Frontend/submit-boundary.test.cjs
```

The response-generation run omits 12 fixture-equality assertions to avoid comparing against a stale file while writing it; the normal 57-test run contains 3373 assertions. The full current suite contains 701 tests/5459 assertions. Do not count response-generation or repeated target executions as additional distinct cases.

The native predispatch report executes actual core FrontController, ActionFlag and payload methods from both tags with explicit infrastructure doubles. It records 34 fixed PASS and 34 expected baseline getter failures. The authoring probe and downloaded references are external to the package; shipped observer/pipeline tests provide reproducible isolated regressions. The full native deployment path is prepared in `FrontendSubmissionTest` (three additional methods, all NOT RUN): accepted popup, owned identical retry, changed-payload token rejection with external email disabled. See `docs/SUBMISSION_FIX.md` and `docs/TEST_REPORT.md` for exact evidence and limitations.

## Installed-source regressions — 1.1.5

`tools/check_installed_release.php` is a standalone, classless, read-only PHP CLI checker. Run it from a separately extracted trusted package, outside the web document root. It compares reference runtime hashes with an explicit module directory or the module resolved by the selected project's Composer autoloader and ComponentRegistrar. It rejects old/mixed/missing/extra source, unsafe/incomplete trusted manifests, self-comparison, symlink escapes/cycles, distinct known copies and disagreement between registration/autoload paths. Missing docs/tests/NOTICE and the explanatory `i18n/README.md` are permitted; missing/changed translation CSV is rejected.

The new Python regression file launches the real checker in PHP subprocesses against temporary filesystem fixtures. Its Composer ClassLoader and ComponentRegistrar are explicitly identified method-compatible doubles. No installed Magento, database, generated DI, web HTTP, FPM or SMTP is invoked. The checker does not load the observer class; full mode executes the selected installed project's Composer registration files, which are trusted project code. A `FILES_MATCH` result verifies the named files/CLI resolution, not the web worker's loaded bytecode or successful submission. See `docs/DEPLOYMENT_VERIFICATION.md` for server commands and acceptance.

Run all tool tests with the intended PHP binary:

```sh
PYTHONDONTWRITEBYTECODE=1 PHP_BINARY=/absolute/path/to/php python3 -m unittest discover -s tests/Tools -p 'test_*.py' -v
```

The current tool suite contains 70 cases: 13 language-scanner, 15 native declaration/source-tool and 42 installed-source checks. All pass on PHP 8.3.6/Python 3.12.14. The application PHP/AMD suites remain 701 tests/5459 assertions and 90 unique JavaScript scenarios, respectively. Actual native FileClassScanner/PhpScanner execution for both tags finds no class or generated entity in the new CLI tool; this narrow file-scope check does not compile installed Magento.
