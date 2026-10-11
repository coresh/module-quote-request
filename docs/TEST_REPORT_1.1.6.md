# Validation report — Coresh_QuoteRequest 1.1.6

Date: **2026-10-11, UTC**. Actual runtime: PHP **8.3.6**, PHPUnit **10.5.66**, Composer **2.7.1**, Node **24.19.0**, Python **3.12.14**. Exact outputs: `tests/reports/runtime-versions.json`.

The input `Coresh_QuoteRequest-1.1.5(1).zip` SHA-256 is `aaddfc3881732d307dd895d002677d288df5c618597c71f87f70ffecf350d86f`, byte-identical to delivered 1.1.5. The supplied fresh 03:59:06 UTC trace still executes the old observer getter before Submit. Source/execution mismatch is established; the present installed file, active release/node and OPcache state are not observed. No production endpoint, user session, recipient or server was accessed.

## Executed checks

| Check | Actual result | Evidence in `tests/reports/` and scope |
| --- | --- | --- |
| Module PHPUnit suite | **PASS: 718 tests, 5534 assertions** | `phpunit-8.3.log`, `unit-junit.xml`; actual classes with explicit framework/catalogue/database/mail doubles. Seventeen new observer/shim/registration cases. |
| Frontend AMD behavior | **PASS: 90 unique scenarios** | 17 modal, 10 inline, 19 button events, 10 grid, 34 boundary scenarios. DOM/jQuery/AJAX doubles; grid base repeats and second tag not counted again. |
| Python tools | **PASS: 80 tests** | `python-tools-tests.log`; 13 language-scanner, 15 declaration/source-provenance, 52 installed-source cases. Ten new checker cases. |
| Native event conversion, merge and dispatch | **PASS: 62 checks, 31 per tag** | `native-event-registration.json`; six configuration checks plus 25 route scenarios for each target. Actual core DOM/Converter/Manager/InvokerDefault/ObserverFactory/FrontController, explicit infrastructure doubles. |
| Native production declarations | **PASS: 75/75 per tag** | `native-declarations-2.4.8-p5.json`, `native-declarations-2.4.9.json`; actual upstream parents/interfaces/traits, separate PHP processes. No constructors/DI/bootstrap. |
| Static and official schema checks | **PASS: 192 per tag, 0 FAIL, 0 NOT RUN** | `static-248p5.json`, `static-249.json`; 138 PHP/PHTML, three application JS, 25 XML parses, 24 XSD checks, two Composer checks. Repeated checks are not extra unique tests. |
| Official source contracts | **PASS: 16 per tag** | `source-contracts-248p5.json`, `source-contracts-249.json`; narrow signatures/patterns only. |
| Native checker file/token scanners | **PASS on both tags** | `native-installed-checker-file-scope.json`; actual FileClassScanner/PhpScanner find no class/generated entity. PhpScanner constructor bypassed via Reflection, full DI NOT RUN. |
| Tool syntax | **PASS: 15 checks** | `tools-syntax.json`; eight Python in-memory compiles, one Bash runner, six CJS files. |
| Separate local release comparison | **PASS: 114 runtime files; old 1.1.5 rejected** | `installed-source-checker-smoke.json`; new runtime files on a distinct local copy, previous local 1.1.5 rejected; not the user's server. |
| Package language | **PASS: no findings/unscanned files** | `english-content.json`; English content, no Cyrillic/unscanned findings, explicit output-file omission. |

## New event proof

Actual native DOM merge retains the old observer instance attribute and applies `disabled=true`. Actual InvokerDefault skips it before asking ObserverFactory for the old class. Fresh configuration is therefore required. Quote/Reports/customer section routes have zero policy calls and zero observer factory requests. Actual modern Submit executes; synthetic product ID 1984 and quantity 1 reach the controlled submit service. Purchase-route AJAX denial is safe HTTP 403 JSON, navigation denial is HTTP 302, allowed actions execute, and saved quote stores are checked on all three routes. Checkout success/failure remain accessible. The old class is not autoloaded by these native cases. An additional token action shape is synthetic; the module has no token controller and generates its request token in AMD.

No native constructor is bypassed in this event probe. Full Config Reader discovery/cache/platform bootstrap does not run. Request/response transports, router, permissive native framework validator, ObjectManager resolution, config storage, policy decisions, sessions, submit service and logger are explicit doubles. Actual module POST/body form-key checks run. Reports uses a modern action shape on 2.4.8-p5 and native View with reporting disabled on 2.4.9. Source URLs/hashes, exact current application hashes and outputs are in the report. Its authoring probe and external core caches are outside release runtime; ordinary package tests reproduce the isolated observer, XML and submission contracts.

## Form and security coverage

Eight actual AMD payloads are URL-encoded by a documented deterministic adapter; PHP consumes them through parse_str. Actual module controller/service/validator/policies/resource idempotency lookup and both mail senders execute with explicit framework/catalogue/storage/transport dependencies. Six actual PHP responses are verified by ordinary PHP tests and consumed by popup/inline handlers in 12 of 34 boundary cases. Normal tests do not regenerate fixtures.

Current/saved-store restrictions, POST/body CSRF, input allowlist, trusted product snapshots, exact quantities, session-bound token fingerprint, identical retry, safe mail fields and failed-notification preservation remain covered. No additional definite submission/security defect was found in the focused audit. Rate-limit localized failure is HTTP 400; JS HTTP 429 covers a possible external response. Production JS, schema, submission protocol and email templates are unchanged. Production curl cookies/tokens/customer addresses are neither replayed nor packaged.

## Not run

Installed Magento upgrade/DI/generated interceptors/bootstrap; actual browser/HTTP/server/FPM/OPcache; real database persistence/concurrency/EAV; SMTP/rendered delivery/inbox; full native request-validator and admin configuration/ACL dispatch; PHP 8.4/8.5; theme/CSP/FPC/Varnish; live REST/GraphQL/checkout/payment. All 26 prepared Magento integration assets (20 declared and six inherited ACL methods) remain NOT RUN. Native/source checks do not certify complete Magento/PHP installations.

Use DEPLOYMENT_VERIFICATION.md for active-path installation, Composer/DI/config/web-PHP refresh and fresh HTTP/admin/retry/mail acceptance. The earlier complete report is retained as TEST_REPORT_1.1.5.md. Old predispatch 34 PASS/34 expected failures, configuration-template, compiler file-scope, original readonly-fatal and development dependency dry-run reports are retained historical evidence, not newly rerun 1.1.6 cases. Current 75-class checks and 62 event checks provide current relevant coverage.
