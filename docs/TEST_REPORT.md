# Validation report — Coresh_QuoteRequest 1.1.7

Date: **2026-10-11, UTC**. Executed PHP runtime: **8.3.6**, PHPUnit **10.5.66**. This report distinguishes newly executed checks from retained historical evidence. No production server, endpoint, user session or recipient was accessed.

## Newly executed checks

| Check | Result | Evidence and boundaries |
| --- | --- | --- |
| Full isolated module PHPUnit suite | **PASS: 735 tests, 5630 assertions** | `phpunit-8.3.log`, `unit-junit.xml`. Actual module classes with explicit framework/catalogue/database/mail doubles. Includes 13 new product-form contracts and four new grid DI contracts. |
| Frontend AMD behavior | **PASS: 90 unique scenarios** | Current `quote-request-117.json`, `inline-form-117.json`, `button-events-117.json`, `submit-boundary-117.json`; native-parent grid reports for both tags. Actual AMD handlers with DOM/jQuery/AJAX doubles. The grid's second tag repeats the same ten scenarios and is not added to the unique count. |
| Python tools | **PASS: 86 tests** | `python-tools-tests.log`: 13 language-scanner, 15 declaration/source-provenance, 58 installed-release cases. Six new admin-source checks include rejection of exact old XML and a rebuilt legacy reference manifest. |
| Native product field conversion, merge and preparation | **PASS: 48 field cases across both tags** | `native-product-form-contract.json`. Includes eight exact expected baseline exceptions. Real tagged UI XML converters, layout argument parser, metadata merge and Field/Select/Input preparation. Old metadata merge produces eight controls, corrected merge four. Both merge directions checked. |
| Native DI collection isolation | **PASS: 77 checks per tag, 154 total** | `native-grid-collection-isolation-2.4.8-p5.json`, `native-grid-collection-isolation-2.4.9.json`. Real DOM/mapper, ObjectManager stage configuration, CollectionFactory and Reporting methods. Old shared-map loss and `Not registered handle` are reproduced using explicit fixture registrations. Corrected shared maps survive global/area definitions and both module orders; Coresh reporting resolves its private factory and applies pagination/filter pool. |
| Static and official schema checks | **PASS: 198 per tag; 0 FAIL, 0 NOT RUN** | `static-248p5.json`, `static-249.json`: 140 PHP/PHTML syntax checks, three application JS checks, 27 XML parses, 26 official XSD checks, two Composer checks. Includes the two test-only legacy XML snapshots. XSD validity alone cannot detect the native UI or DI-stage defects. |

Local installed-source comparison and package language checks are recorded in their current JSON reports. They verify the separate local sources only, not the user's serving process.

## Native execution boundaries

The product-form probe loads unmodified upstream source with recorded URLs/hashes. Context, configuration storage, definitions, element factory and scalar/array argument evaluation use explicit adapters/doubles. Select and UiComponentFactory constructors are bypassed with reflection. EAV metadata is controlled fixture data following the inspected native Eav/AbstractModifier container structure. Native EAV `setupAttributeMeta`, actual attribute persistence, ObjectManager/bootstrap, complete HTML render and product save are NOT RUN. Both merge directions preserve supplied options, labels, scopes, default helpers, disabled state and validation. Normal native metadata order keeps the EAV notices; reverse order keeps the XML notices.

The DI probe uses actual target DOM/mapper and Config behavior, including argument inheritance and native virtual type resolution. Object creation for fixture collections, SearchResult data, filter pool and search criteria are explicitly controlled. Fixture names include the reported Mageplaza handles, but no real Mageplaza collection classes or tables are inferred. Installed compiled DI, real collections/database and the actual third-party package are NOT RUN.

Authoring probes and downloaded upstream caches are outside the production module; source URLs/hashes and commands are recorded in reports. Reproducible ordinary unit/checker tests are shipped. Exact old XML snapshots are test-only inputs and are never registered as runtime configuration.

## Scope and security

Two application XML files and the classless installed-source checker change relative to the verified 1.1.6 archive. The native product EAV containers retain inherited field metadata. The grid's factory is scoped to Coresh reporting, avoiding a shared configuration override. Existing request validation, POST/body form-key enforcement, idempotency, trusted product snapshots, ACL, private-grid field protection and purchasing restrictions remain covered by the full isolated regression suite.

All package text is maintained in English. No submitted server cookies, form keys, request tokens, customer addresses or raw server reports are packaged.

## Retained historical evidence

The complete preceding report is `TEST_REPORT_1.1.6.md`. Native 75-class declaration checks per tag, 62 native frontend event checks, 16 source contracts per tag and older compiler/config probes remain historical evidence for unchanged application classes and frontend registration. They were not rerun for this XML-only application change and are not counted as newly executed 1.1.7 tests.

## Not run and live acceptance

Installed Magento upgrade/DI generation/interceptors/bootstrap; real browser/HTTP/admin product editing and saving; actual Mageplaza package; real database/EAV persistence/concurrency; SMTP delivery/inbox; PHP-FPM/OPcache; PHP 8.4/8.5; full installed ACL dispatch; theme/CSP/FPC/Varnish and live checkout/REST/GraphQL. The existing 26 Magento integration-test assets still require a disposable installed test deployment and remain NOT RUN here.

Use `ADMIN_UI_FIX_1.1.7.md` for backed-up installation, source verification, runtime refresh and live acceptance. Require a fresh product edit/save, working Coresh and Mageplaza grids, and a successful storefront enquiry with one matching admin record before confirming the target server repair.
