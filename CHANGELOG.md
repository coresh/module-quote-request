# Changelog

## 1.1.9 — 2026-10-11

- Correct the native Reply-To API and replace shared mail builders with per-send factory instances for store notifications and customer confirmations.
- Guard request reload and owned email-attempt completion; do not mark a successful transport handoff as a failed send when status persistence or refresh fails.
- Preserve inline translation state, including native template-emulation boundaries, without masking the mail outcome during cleanup.
- Provide fixed, safe failure codes and actionable administrator messages; distinguish unconfirmed status after handoff from a failed send. Retain POST-body form keys and the separate email ACL permission.
- Retain the persisted request ID and accepted storefront response when notification reloads or warning-log writes fail; a mail failure does not undo a saved enquiry.
- Remove the obsolete module identifier from the package and use neutral legacy migration labels while retaining verified schema checks and author attribution.
- Add sender, controller, source-contract, migration and installed-source regressions; verify the repaired senders against native mail classes for both target tags. SMTP delivery and an installed Magento deployment remain separate acceptance checks.

## 1.1.8 — 2026-10-11

- Move Coresh — Quote Requests to the Sales menu while retaining its label, action URL and separate view/manage/send permissions.
- Declare Magento_Sales as a Composer and module-sequence dependency so its parent menu is registered before the extension's entry.
- Update the administrator navigation, upgrade and permission instructions in README and the configuration guide; retain the Customers migration page.
- Document the native Sales-container permission needed by restricted roles; preserve existing Coresh ACL identifiers and independent mutation permissions.
- Add menu and access-control regressions and rerun the PHP, JavaScript, tool and target-XSD checks. No request persistence or email behavior changes.

## 1.1.7 — 2026-10-11

- Correct product form overrides to use native EAV `container_<attribute_code>` paths and explicit select/input `formElement` values; retain dynamic options, scope and defaults without duplicate controls.
- Give the Coresh admin grid a dedicated collection factory and inject it into its reporting virtual type; stop replacing the shared admin `CollectionFactory.collections` map used by other extensions.
- Add product form and DI isolation regressions, native target-source reproductions, and updated installed-source verification.
- Include the previous quote-submission and purchasing-policy corrections; no database schema, public submission protocol or email template changes.

## 1.1.6 — 2026-10-11

- Explicitly disable the legacy global purchasing observer; bind the corrected guard only to checkout, multishipping and PayPal predispatch events. Quote, Reports and customer section actions do not invoke it.
- Rename the implementation to EnforcePurchasingPolicy and retain BlockPurchasingRoutes as a deprecated compatibility subclass with no own execution code.
- Preserve existing purchasing restrictions, saved quote-store checks, safe denial outputs and all submission security/idempotency protections.
- Pin the new observer, compatibility class and event configuration in installed-source verification; add registration and native event-dispatch regressions.
- Document the fresh 03:59 server trace, verified uploaded/executed source mismatch and concrete installation/runtime acceptance steps. No database schema or production JavaScript changes.

## 1.1.5 — 2026-10-10

- Retain the corrected 1.1.4 application source: the supplied new archive already contains it, while the attached 17:23 web errors execute incompatible older observer instructions.
- Add a read-only installed-source checker for trusted runtime hashes, Composer/registered module paths, distinct known installations and manifest/path integrity; explicitly leave PHP-FPM/web execution unverified.
- Add isolated filesystem/autoload/registration regressions and a deployment recovery/web acceptance procedure with supported Cloudways PHP-FPM refresh.
- Correct the documented module rate-limit response to HTTP 400; the independent JavaScript 429 case verifies client handling rather than a different server protocol.
- Restore the omitted English documentation and NOTICE, and refresh the executed test/evidence reports. No application behavior or schema changes are introduced by this release.

## 1.1.4 — 2026-10-10

- Removed calls to request/response getters that modern ActionInterface controllers do not provide, fixing the predispatch HTTP 500 for quote submission and other non-purchase routes.
- Used the event/current HTTP request, injected shared HTTP response and native no-dispatch flag to preserve catalogue purchasing restrictions for modern and legacy actions.
- Corrected invalid reused-token guidance: a page refresh resets the client state; reopening the same product alone need not do so.
- Added actual native FrontController execution probes, observer/store-policy tests, captured AMD POST payloads and frontend-to-backend response regressions.
- Included prepared complete Magento submission/persistence assets with their unexecuted status clearly recorded.

## 1.1.3 — 2026-10-09

- Registered the native configuration-path default email template IDs; retained earlier IDs as equivalent aliases and aligned new default values.
- Prevented malformed or null quote-button JSON from interrupting click handlers and purchase suppression.
- Kept admin submit buttons available when an earlier validator has already cancelled submission.
- Rejected control characters in date and security-limit configuration, malformed sibling date values and reversed padded date ranges.
- Added native template-option reproduction and regression evidence for both target tags, configuration contracts, button events and controller outputs.
- Included the English documentation and licensing notice referenced by the uploaded package.

## 1.1.2 — 2026-10-09

- Fixed the `RequestDetails::$formKey` readonly inheritance conflict with the native mutable Backend Template property. Preserved the public constructor argument and current form-key behavior.
- Added native class declaration checks against actual parent/interface/trait source for both target Magento tags, including reproduction of the uploaded 1.1.1 fatal error.
- Restricted individual administrative request lookups to canonical positive IDs within the unsigned INT database range.
- Prevented malformed imported legacy dates from raising PHP `ValueError` and aborting migration.
- Added block, identifier, migration, rate-limit, customer-confirmation and source-provenance regressions, plus native integration assets explicitly marked NOT RUN.

## 1.1.1 — 2026-10-09

- Ignore outdated asynchronous customer-profile responses so they cannot overwrite a newer form reload.
- Remove internal idempotency and payload digests from this module's admin listing data.
- Require the current POST-body form key for individual status updates and notification resends, matching the other admin actions.
- Reject malformed ISO dates before parsing, including embedded NUL bytes, without an uncaught PHP `ValueError`.
- Added regression tests for these changes, submission ownership and notification failures, plus a repeatable package-language check.
- Documented validation results and the remaining Magento runtime checks in the release reports.
- Updated documentation references and installation examples for this release.

## 1.1.0 — 2026-10-08

- Store-scoped product settings: inherited/custom labels, an empty custom label, Allow Add to Cart, and Disclose Price.
- Static Label display without a form; a public POST cannot create a request when the enquiry is hidden.
- Explicit customer-group restriction switch that preserves existing rules during upgrades and legacy imports.
- Full-text search, Phone/Description columns, CSV export with formula-injection protection, and bulk status changes.
- Admin verified legacy preview/import page with a dedicated ACL, POST FormKey validation, and an expiring approval token bound to a store.
- Sparsh source-module detection with an explicit explanation when a verified migration adapter is unavailable.
- Separate email lease timestamp that admin handling-status changes do not modify.
- Additional regression tests and an updated report of checks actually performed.

## 1.0.0 — 2026-10-07

- New Coresh_QuoteRequest architecture based on the supplied requirements and reference source.
- Server-verified product snapshots, quantity, customer context, and configurable-option resolution.
- Configurable email fields, templates, sender, and store; request persistence, admin resend, and separate ACL resources.
- Modal enquiry, listing/PDP controls, button/link display, enquiry tab, and store/group/date/category eligibility.
- Cart Hidden, independent price visibility, and a shared server-side purchasing policy.
- Explicit legacy migration with dry-run support, unit/integration test assets, reports, and verifiable packaging.

Executed check outputs are retained under `tests/reports/`. This changelog does not replace runtime certification.
