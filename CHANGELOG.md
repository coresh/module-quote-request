# Changelog

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
- Admin Darsh preview/import page with a dedicated ACL, POST FormKey validation, and an expiring approval token bound to a store.
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

The scope of checks actually performed is recorded in TEST_REPORT. This changelog does not replace runtime certification.
