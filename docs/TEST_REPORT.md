# Validation report — Coresh_QuoteRequest 1.1.1

Date: **2026-10-09, UTC**. The final 1.1.1 sources were checked using PHP **8.3.6**, PHPUnit **10.5.66**, Composer **2.7.1**, Node **24.19.0**, and Python **3.12.14**. Exact version output is recorded in `tests/reports/runtime-versions.json`. The supplied 1.1.0 ZIP was the update baseline; its SHA-256 is `598141f3e0ca3684b57fdc4ae56b01c686d973283a971cde854d38ecef9736ae`. This environment has no installed Magento instance, test MySQL database, or running storefront.

## Checks actually executed

| Check | Result | Scope | Log in `tests/reports/` |
| --- | --- | --- | --- |
| Actual module PHP classes with minimal framework test doubles | PASS, exit 0 | **370 tests, 765 assertions**, no failures or errors | `phpunit-8.3.log`, `unit-junit.xml` |
| Modal and admin submit-once AMD modules with DOM, jQuery, and customer-data test doubles | PASS | 17 scenarios | `frontend-isolated.json` |
| Inline-form AMD module with DOM test doubles | PASS | 10 scenarios | `inline-isolated.json` |
| Grid-export AMD module with DOM test doubles | PASS | Six scenarios | `grid-export-isolated.json` |
| PHP 8.3 syntax | PASS | **117 PHP/PHTML files**, including test assets; 81 application files | `static-248p5.json`, `static-249.json` |
| JavaScript syntax | PASS | Three application JS files | Both static-check reports |
| XML parsing with external entities disabled | PASS | 25 XML files, including the JUnit report | Both static-check reports |
| Official Magento **2.4.8-p5** XSD files | PASS | 24 module XML files | `static-248p5.json` |
| Official Magento **2.4.9** XSD files | PASS | 24 module XML files | `static-249.json` |
| Composer identity/autoload and `composer validate --strict` | PASS, exit 0 | Two checks | Both static-check reports |
| New APIs: signatures and patterns in official source code for both tags | PASS | 16 limited checks per tag | `source-contracts-248p5.json`, `source-contracts-249.json` |
| English-content scanner regressions | PASS | 13 Python tests | `english-scanner-tests.log` |
| Package language scan | PASS | No Cyrillic findings or unscanned files; see the report for counts and its explicit output-file omission | `english-content.json` |
| Tool syntax | PASS | Five Python files compiled in memory; one Bash runner checked with `bash -n` | `tools-syntax.json` |

Each static-check report contains **171 PASS, 0 FAIL, 0 NOT RUN**. The repeated lint and Composer checks in the two reports are not independent additional tests. The JavaScript behavioral suites contain **33 scenarios** in total.

The historical development-toolchain result retained from **2026-10-08** was not rerun for this release. Dependency resolution used a separate manifest with the same two `require-dev` constraints and without Magento runtime dependencies. This was a **dry run**: no packages were installed, and neither Coding Standard nor PHPUnit 12 was executed. Full Composer installation of the module on the target Magento versions remains unverified. The actual PHPUnit run used version 10.5.66.

## Behavioral coverage of the executed tests

Existing coverage verifies the input allowlist, exact DECIMAL quantities and limits, UTF-8 and control characters, email injection, server-side product context, configurable options, store/website/status/visibility checks, date/group/category eligibility, contact-recipient fallback, purchasing guards for current and persisted stores, cache TTL, collection reentrancy, POST/form keys, and email-variable switches.

The additional 1.1.1 regressions verify:

- Out-of-order private-profile reloads cannot replace the latest response, including product switches and successive inline submissions; manual entries are preserved.
- The admin listing strips only its internal `idempotency_key` and `payload_hash` fields; unrelated data sources and public fields remain unchanged.
- Both individual admin mutations reject GET, query-only, malformed, wrong, and framework-invalid form keys. Valid POST requests still update the status or invoke notification sending.
- Malformed ISO dates, embedded NUL/control bytes, invalid UTF-8, leap days, empty bounds, and reversed periods receive safe validation or fail-closed behavior.
- Submission tokens bind to the validated payload and private session; owned retries and concurrent-insert winners do not send again. Persistence failures are not acknowledged as success; transport failures leave accepted requests saved and logs redacted.
- Notification handling rejects injected addresses, honors store configuration, respects a busy send lease, and restores inline translation on both success and failure.
- The language scanner detects Cyrillic in content and names, reports unreadable files and symlinks, preserves authored output files, and handles prior generated reports without repeating stale findings. Unicode validation still covers non-ASCII data using `é`.

Existing regressions also verify:

- Inheritance of the global button text, custom labels, hiding the request through a custom blank label, static Label mode, and rejection of a new direct POST when the request is hidden.
- Tri-state cart and price overrides, the master module switch, and prevention of a product-level Yes setting from bypassing the global server-side purchasing restriction.
- The separate group-restriction switch: disabling the flag permits all groups; enabling it with an empty list denies all groups; guest group 0 remains a valid ID.
- The upgrade patch and subsequent legacy import: restrictions remain at the original scope; an explicitly empty child scope permits all groups; existing new values and repeat imports are not overwritten. Six import-regression cases execute the actual importer method with database test doubles.
- Product backend validation, Unicode lengths, invalid UTF-8, explicit numeric 0, and preservation of native `false`/`null` sentinels without premature normalization. For a custom blank label, the backend returns one ASCII space, which the presentation policy treats as an empty label.
- Migration approval tokens: exact store binding, digest validation, the 600-second lifetime, rejection of future timestamps, malformed/wrong/expired tokens, and unknown source schemas.
- CSV formula payloads, leading control characters, Unicode whitespace and format characters, sanitization of every declared field, exclusion of unknown fields, and closure and deletion of a partially written file after an error.
- Rejection of stock CSV/XML export for this listing's namespace, canonical selected/excluded ID validation, rejection of simultaneous selection modes, and literal wildcard handling in full-text searches over fixed fields.
- Mass-status resource validation and updates limited to `request_status`/`updated_at`, with a bounded batch size.
- Modal and inline retries with the original token, a new token after payload/product changes and success, runtime form keys, duplicate submissions, safe `.text()` responses, and fresh private-profile data.
- Grid export sends the runtime form key in the POST body, forces the namespace, serializes nested filters/IDs and native select-all, and removes the temporary form.

The default PHPUnit bootstrap uses explicitly identified minimal framework test doubles. Node executes the actual AMD modules with isolated DOM test doubles. These results **do not constitute Magento integration testing, browser testing, or verification of runtime interception and escaping**.

## Prepared Magento integration assets — NOT RUN

`tests/Integration` contains **17 prepared test assets**: 13 declared methods and four inherited `testAclHasAccess`/`testAclNoAccess` checks from the official Magento backend test base. An installed target Magento test harness and a separate MySQL test database are required.

| Asset | Scenarios |
| --- | --- |
| `PersistenceTest` — two | Declarative fields; snapshot, quantity, and plain-text round trips |
| `SubmitCsrfTest` — two | AJAX POST without a form key; framework rejection of GET |
| `AtomicResourceTest` — four | Unique idempotency index; failed email transition; dedicated lease, expiry, and attempt CAS; individual and bulk status updates preserve email state and snapshots |
| `ProductDisplayScopeTest` — three | Actual EAV save/reload of a blank store override, explicit numeric 0, and native AttributeFilter Use Default: varchar `false` and numeric `null` delete store rows and restore parent values |
| `ExportAclTest` — three, including two inherited | Actual backend ACL and rejection of a form key supplied only in the query instead of the POST body |
| `MassStatusAclTest` — three, including two inherited | Actual manage ACL and rejection of a form key supplied only in the query instead of the POST body |

These are executable prepared assets; **all are NOT RUN**. The EAV fixture uses the actual core AttributeFilter, repositories, and resource queries. It does not cover the complete admin product controller or UI. The modern EAV UpdateHandler supports valid empty strings, so source inspection does not establish a blank-label persistence defect in that path. The module uses a space as an explicit persisted blank sentinel; native persistence remains unverified until the asset is executed.

The lease assets test sequential database transitions, including an admin edit after lease expiry. A multi-process race requires a separate check. The failed-email asset invokes the resource transition, rather than the actual mail transport.

## Checks requiring a target deployment

| Check | Status | Reason |
| --- | --- | --- |
| Fresh installation, two upgrade runs, DI compilation, static-content deployment, and integration on 2.4.8-p5 / 2.4.9 | **NOT RUN** | No installed Magento instance or test database |
| PHP 8.4/8.5 runtime and PHPUnit 11/12 execution | **NOT RUN** | Actual runs used PHP 8.3.6 and PHPUnit 10.5.66 |
| Magento Coding Standard 41, PHPStan/Psalm, and full Composer dependency installation | **NOT RUN** | Only validation and a separate development dependency-resolution dry run were executed |
| Luma/Blank browser behavior, inline product tab, and admin grid/full-text/export/mass-status/ACL UI | **NOT RUN** | No running storefront, admin, or browser runtime |
| Email rendering/escaping, Reply-To, recipient/sender, and external delivery | **NOT RUN** | No Magento email renderer or test mail transport |
| REST/GraphQL/frontend cart/order endpoints and store-switch interception | **NOT RUN** | Policy unit tests do not execute runtime endpoints or plugins |
| FPC/Varnish, restrictive CSP, private PII isolation between two customers, and store scopes | **NOT RUN** | No Magento cache or browser runtime |
| Concurrent submit/resend/rate-limit/crash recovery | **NOT RUN** | No multi-process database/mail deployment |
| Complete migration against a representative legacy database, native EAV/admin persistence, and production deprecations | **NOT RUN** | No corresponding target deployment or source database |

This report does not certify the complete Magento/PHP combinations. XSD checks and limited source-contract checks confirm static properties of the sources for both tags; installation, DI compilation, and runtime verification are still required. Platform requirements are documented in `COMPATIBILITY_MATRIX.md`.

## Reproducing the checks

Commands and the boundaries of the test doubles are documented in `tests/README.md`. To run unit tests with actual framework classes, set `MAGENTO_UNIT_AUTOLOAD` to the installed target's `vendor/autoload.php`. Use `tools/run-magento-checks.sh` for installation, upgrade, compilation, static deployment, and integration on a separate disposable test deployment with its own database. Runtime tools and downloaded reference sources are not included in the module ZIP.
