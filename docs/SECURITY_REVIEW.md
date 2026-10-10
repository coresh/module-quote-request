# Coresh_QuoteRequest security review

Date: 2026-10-08. Scope: version 1.1.0 source code in `update/Coresh_QuoteRequest`, configuration, templates, available isolated checks, and fixes made following an independent review. The main safeguards from version 1.0.0 were reviewed again; additions in 1.1.0 are described below. This section records the historical 1.1.0 review. The 1.1.1 review is appended separately.

## Status and limits of verification

No identified critical defect remained in the reviewed code after the fixes described here. This is the result of a limited source review; it is neither security certification nor proof that unknown vulnerabilities are absent.

The development environment did not contain installed Magento Open Source 2.4.8-p5 or 2.4.9 instances with a database and working storefront. Actual DI compilation, integration/functional tests, administrative roles, REST/GraphQL, Magento email rendering, SMTP, FPC/Varnish, and CSP checks on these versions are **NOT RUN**. Isolated tests exercise the code with controlled framework doubles; they are not tests of an installed Magento instance. Exact commands and results are recorded in `TEST_REPORT.md`; version status is documented in `COMPATIBILITY_MATRIX.md`.

## Reviewed safeguards

| Surface | Implementation and reviewed logic |
| --- | --- |
| Request submission | `Controller/Request/Submit.php` implements `HttpPostActionInterface`, additionally checks the HTTP method, compares the **POST** `form_key` with the server's `FormKey` using `hash_equals`, then runs the framework validator. The JSON response prohibits caching. |
| Untrusted input | `QuoteRequestValidator` returns only allowlisted fields. It rejects arrays in place of scalars, invalid IDs/email/UTF-8/control bytes, and negative, zero, or excessive quantities. Quantity is normalized to DECIMAL(12,4) without floating-point conversion. |
| Product context and authorization | `ProductContextResolver` obtains a catalogue snapshot in the store context and validates website/status/visibility, eligibility, and the configurable child's relationship to its parent. The client cannot assign name/SKU/URL/customer ID/store ID/status/email flags. Customer ID and group come from the authenticated server context. |
| Output and messages | Storefront/admin values are escaped for their HTML, attribute, or URL context. JavaScript displays names and messages through `.text()`, including HTTP 4xx application errors. Email uses scalar variables and `escape`; the customer's message is not permitted as HTML. |
| Administrative actions | Separate `ADMIN_RESOURCE` permissions govern view/manage/send_email/config. The grid specifies `aclResource` and uses text cells. Status changes and resends require POST with a form key. Notification recipients come from store configuration, rather than the storefront POST. |
| SQL and storage | Framework resource APIs and bound parameters are used. Dynamic SQL values are not constructed by concatenating input. There are no storefront uploads, arbitrary filesystem paths, `eval`, shell execution, or remote code downloads. |
| Email | The configured sender identity belongs to the store; the validated customer email is used as Reply-To. The template and store come from the original request. Disabled product inclusion flags replace the corresponding values with empty values and exclude their rows from the standard template. Inline translation is restored through `finally`. |
| Errors | The user receives a safe message. The logger receives a request reference and a sanitized error code. The JSON response contains no complete customer message, tokens, or stack trace. |
| PII and shared cache | Customer names/emails are absent from cacheable templates and are loaded through a private customer-data section. The modal first loads a fresh section; previously autofilled values are cleared when it is reopened. The form key and random request token are set at runtime. |
| Catalogue mode | The shared policy checks the current store, the quote object's store, and the persisted store retrieved directly from the database by quote ID. This covers changes to the Store header/parameter and in-memory store changes before persistence. Guards are attached to standard Quote addProduct, CartRepository save, and QuoteManagement placeOrder/submit entry points; a storefront route observer supplements the service restrictions. |

## Retries and concurrency

The storefront generates a 256-bit token through `crypto.getRandomValues`. Its SHA-256 hash and a fingerprint of the normalized payload, store, customer ID, and a separate random session identity are stored in the database. Client-supplied internal fields are not copied into the model.

The unique `idempotency_key` index provides the final safeguard against two concurrent inserts. For a matching token, the fingerprint is checked through `hash_equals`; a different payload cannot receive confirmation for someone else's request. A verified retry returns only the request ID and a safe acknowledgment. It neither writes another request nor automatically sends another notification.

Deduplication remains effective while the record containing the key is retained; the idempotency key has no time-based TTL. Deleting the record ends protection by that key. Reopening or changing the form may generate a new token and is treated as a new request. Losing the session, switching accounts, or losing the client token does not guarantee deduplication of an earlier submission. The module does not promise to merge submissions made intentionally with different tokens.

Request acceptance status is separate from email status. A transport error does not delete an already saved request. A process may fail after committing the request but before starting notification delivery; that record remains pending. There is no automatic, guaranteed outbox worker. An administrator can inspect the request and use Send/Resend.

## Rate limiting and anti-spam

The honeypot is validated on the server. When rate limiting is enabled, a fixed window and shared MySQL counter use atomic INSERT/UPDATE operations; workers/nodes do not maintain separate local counters. A client bucket includes the store, time window, and `REMOTE_ADDR`. The code does not trust an arbitrary `X-Forwarded-For` header. Expired buckets are removed after a bounded retention period.

Submission sequence: basic input validation → session/payload fingerprint → acknowledgment of an already saved, verified retry → quota consumption → catalogue/quantity resolution and validation → persistence. New requests referencing nonexistent products or invalid configurations therefore consume quota before expensive catalogue queries. A verified retry does not reload the catalogue or consume quota. Completely invalid fields rejected by the basic validator never reach the counter. Limits match the admin validation: 1–1000 requests per window of 1–86400 seconds.

IP limiting has operational constraints. Visitors behind NAT may share an address; behind a reverse proxy, `REMOTE_ADDR` may identify the proxy itself. Trusted forwarding of the actual client IP must be configured at the infrastructure level. Rate limiting does not replace HTTP body-size limits or distributed DoS protection. Web-node clocks must be synchronized. Changing window/store settings creates a different bucket.

## Concurrent email sending

`claimEmailAttempt` atomically claims a record for sending and obtains an attempt number within a transaction under a row lock. `completeEmailAttempt` updates the result only when both the attempt number and sending status still match. A late result from an obsolete attempt must not overwrite the state of a newer attempt.

The administrative `SaveStatus` action uses `updateRequestStatus`, which updates only the handling status and timestamp. It does not save an entire previously loaded record over a concurrently updated email status.

The lease expires 600 seconds after the separate `email_lease_started_at` timestamp. Claiming an attempt sets this field atomically; completing the current attempt clears it after checking the attempt/status. Single and bulk status updates change only `request_status`/`updated_at` and do not extend the lease. Legacy records in sending status with an empty new lease field can be claimed again after upgrade. Active sending processes running the old code should be allowed to finish before installing the new version.

If the previous transport continues running after lease expiry and a new attempt starts, two external messages may still be accepted. A failure after a successful SMTP handoff but before recording sent status also permits duplication during a manual resend. Compare-and-swap protects database state; it does not make SMTP transactional with the database.

The sent status means that `sendMessage()` completed successfully and the transport accepted the message. It does not prove delivery to an external inbox. **Exactly-once SMTP delivery is not guaranteed.** Customer confirmation is a separate, optional send; it has no independent persisted lease/status or guaranteed retry worker.

## Fixes following independent review

- Eliminated load-plugin recursion: a collection's `getItems()` may invoke an intercepted `load()` again. `ProductCollectionContext` now uses a per-object reentrancy guard with `try/finally`. An isolated regression check covers this behavior.
- Added a direct persisted quote-store lookup to protect against in-memory quote-store changes before purchasing checks.
- Removed use of a cached private profile before fresh data arrives and corrected handling of HTTP 400 JSON errors in the modal.
- Removed nested email `depend` blocks with the same name; a separate combined visibility flag controls the selected child ID.
- Made the form-key check explicitly read from the POST body, independently of a query-string token.
- Moved rate-quota consumption ahead of catalogue loading and aligned its configuration ranges with admin validation.
- Separated eligibility for new requests from administrative resending of historical snapshots. A deleted product or disabled storefront feature does not require replacing historical data.
- Mapped legacy Completed (status 2) to target closed during migration; missing legacy quantity remains unknown.

## Catalogue-mode limitations

Protection covers the standard core entry points to which the guards are attached. An arbitrary third-party REST/GraphQL endpoint, custom payment integration, or code that writes a quote/order directly through a resource model while bypassing these methods requires a separate adapter and verification. Third-party extensions were not installed or reviewed in this environment.

Adminhtml and crontab are exempt from PurchasingPolicy. Privileged REST calls use the webapi area and are not automatically treated as adminhtml. Cron or an external integration that intentionally creates orders may operate outside the storefront restriction. Enabling catalogue mode does not cancel external payments, redirects, or callbacks already in progress. The module does not cancel payment transactions or change payment credentials.

Cart Hidden, the global allow_add_to_cart setting, and the individual Allow Add to Cart setting control presentation. Only disable_purchasing enables the server guards. An individual Yes does not override that prohibition. Hide Prices and the individual Disclose Price setting change the HTML price renderer; APIs, third-party structured data, JavaScript configuration, and other price sources are not filtered. Hiding a price in HTML does not make it confidential.

Configuration and category rules invalidate module cache tags/full_page/block_html; HTML TTL is bounded by the nearest date boundary. However, Varnish purge/grace, CDN behavior, and custom PageCache environments were not verified in a live installation. The submission endpoint independently checks current eligibility even when the user sees a stale button.

## Additional independent review of 1.1.0

POST/CSRF, the input allowlist, server-side product/customer context, idempotency, rate limiting, email-template escaping, purchasing guards, and the private profile were reviewed again. The limitations described above still apply.

| Change | Reviewed logic |
| --- | --- |
| Individual label/cart/price settings | The shared `ProductDisplayPolicy` is applied to product detail pages/listings and the server's `ProductContextResolver`. Overrides are active only for a product with its own Enable Quote Request flag; store/group/date/category eligibility remains mandatory. |
| Hidden inquiry | A custom label that is empty after trimming removes the button and inquiry tab, and new public requests are rejected on the server. Static Label mode also does not open a form or allow a new public request. A saved, verified retry may acknowledge earlier acceptance without another record or email. |
| Customer groups | Restrict Customer Groups = No permits every group; Yes with an empty allowlist denies every group. Guest is group 0. The storefront uses public HTTP context; submission uses the actual customer session. A separate upgrade patch preserves previous nonempty/explicitly empty scope selections without overwriting a newly configured restriction switch. |
| Upgrade | New attributes are added by the new `AddProductDisplayAttributes` patch, which depends on the original patch. The changes do not rely on rerunning the already recorded 1.0.0 patch. The schema separately adds the email lease timestamp. |
| EAV and empty captions | The backend stores an explicitly empty caption as one ASCII space; the policy trims whitespace and treats it as a hidden inquiry. This protects different EAV persistence paths. Nullable/false Use Default sentinel values are retained without conversion to an explicit No. Actual native store-value reloading in an installed Magento instance is NOT RUN. One legacy EAV path does not establish a defect in the modern EntityManager UpdateHandler. |
| Fulltext search | The module's filter uses a fixed field list and OR LIKE conditions through the framework collection API. Values are not embedded in SQL; `%`, `_`, and backslash characters in search text are treated as literals. Query length is limited to 512 bytes. |
| Admin grid and CSV | Phone/Description use a text cell template. The CSV controller requires view ACL and POST/form-key validation. Every cell, including message/phone/name and product snapshots, passes through the formula sanitizer. A text apostrophe prefix is added before `=`, `+`, `-`, or `@`, including when preceded by Unicode separators, format characters, or control whitespace. Multiline CSV is written through a framework stream. Client-supplied arbitrary filenames/paths are not accepted. |
| Alternative export routes | Admin DI attaches `PreventUnsafeCoreExport` to standard ConvertToCsv/ConvertToXml services. The listing's namespace is rejected so that direct use of the stock exporter cannot bypass CSV protection. Other namespaces are unaffected by the plugin. |
| Selection and operation size | Only the exact listing namespace and a single selected/excluded protocol are accepted. IDs use canonical unsigned format. Provider count is checked before core Filter materialization, and the resulting collection size is checked again. The limit is 25,000 records; rows are read by request ID in batches of 500. |
| Statuses | Bulk actions require a separate manage ACL and POST/form-key validation. The resource accepts only new/processing/closed and canonical unsigned IDs. It updates status/time without saving a stale model over email/customer fields. |
| Migration panel | Viewing/executing migration requires a separate migrate ACL. Preview and execute require a POST-body form key, an explicitly selected active store, and a verified Darsh source structure. Execute also requires a random preview token bound to the session/store with a 600-second TTL, plus explicit confirmed input. Approval state is consumed before writing. |

Administrative migration uses only the verified Darsh adapter: source records are retained, no email is sent, and existing target values are not overwritten. Nonempty/empty legacy customer-group lists receive the corresponding restriction switch if the new switch has not already been set. Sparsh is displayed as a detected active module without automatic import. Its source code and schema were not supplied, so its data and commercial implementation are not inferred.

Preview displays the source's current state rather than a frozen snapshot; data may change before execution. Session-bound approval uses standard Magento session semantics; concurrent consumption depends on the selected session handler's locking. A unique legacy ID and no-op duplicate writes protect repeated import of known records. Neither migration nor bulk status updates promise a single transaction for the entire dataset. Successfully written batches remain committed if a later error occurs; a fresh preview allows processing to resume.

Selected APIs were checked against cached official source code from the target tags, including MassAction Filter, SearchResult, the export API, Config Form Field, Backend Template Context, and ModuleListInterface. This checks API declarations and expected semantics; it does not run DI or the UI of an installed Magento instance.

The additional review corrected bypass of safe CSV export through the stock exporter, simultaneous selected/excluded modes, omission of the new customer-group switch during a later legacy import, a product form field that did not match the actual attribute code, and the effect of unrelated status updates on the send lease. Product identities were added to the product detail page block for native product-tag cache invalidation. Operational verification of CSV in a real spreadsheet viewer, restricted admin roles/selection UI, native EAV inheritance/empty labels, schema upgrade of an existing installation, and cache invalidation for the new overrides remains NOT RUN.

## Checks not run in an installed Magento instance

Before production deployment, carry out the staging checks specified in `TEST_REPORT.md`: installation/DI/schema upgrade; actual restricted admin roles and mui rendering; POST/CSRF and mixed query/body tokens; XSS/template payloads; private content during logout/account switching; FPC/Varnish/CSP; REST/GraphQL and existing quotes during Store changes; concurrent MySQL inserts/sends and transport failure; actual email rendering and selected fields.

This document records implemented measures and the limits of analysis. It does not establish a post-compromise store investigation, the absence of unrelated code, or the security of third-party extensions.

## Independent review of 1.1.1

Date: 2026-10-09. Scope: the supplied 1.1.0 package, the English translation, and the 1.1.1 fixes and regression coverage described below. The earlier review is retained as history. No installed Magento runtime was available for this review; native integration, DI, browser, email-transport, and cache checks remain **NOT RUN**.

- Corrected an asynchronous private-profile race: an older customer-data response could arrive after a newer response and overwrite it. A generation guard prevents stale callbacks from replacing the current profile state.
- Restricted the exact quote-request admin data source's serialized output. The default data provider could include private `idempotency_key` and `payload_hash` values despite their absence from visible grid columns. `Plugin/Adminhtml/ProtectPrivateGridFields.php` applies only to `coresh_quote_request_listing_data_source` and strips those two internal fields from its response.
- Aligned `SaveStatus` and `ResendEmail` with the other administrative mutations by requiring a matching form key from the POST body, independently of any query-string token.
- Hardened date configuration handling in `DateRangePolicy` and `Config/Backend/Date`. An exact ISO-format regular-expression check rejects embedded NUL bytes and invalid date formats before `createFromFormat`, preventing a PHP `ValueError` from malformed configuration. This addresses robustness when processing privileged configuration; the review did not establish a public exploit through these settings.

Dedicated regression coverage includes `ProtectPrivateGridFieldsTest`, `AdminPostGuardTest`, asynchronous private-profile scenarios, and malformed-date checks. Executed results and any remaining verification limits are recorded in `TEST_REPORT.md`; no unexecuted Magento runtime check is represented as passed here.

## Review and compilation correction in 1.1.2

Date: 2026-10-09. The supplied 1.1.1 archive reproduced the reported fatal error when the native Backend Template parent chain was loaded: a child cannot redeclare its inherited mutable `formKey` property as readonly. Previous lint and isolated tests did not load this native block hierarchy and therefore missed this defect.

`Block/Adminhtml/RequestDetails.php` now stores its injected token service in a separate private readonly `requestFormKey` property. The constructor argument remains named `formKey`, and the parent constructor still initializes its own property. Form-key retrieval and the separate manage/send-email permissions are preserved.

All 74 production class declarations were loaded in separate PHP processes against actual, unmodified, hash-verified parent/interface/trait sources for both target tags. These checks detect declaration conflicts. They do not construct objects, compile Magento DI, generate interceptors, or run the installed application.

Individual request lookups in the block and shared admin controller now accept only canonical positive integer IDs up to 4294967295, matching the unsigned INT schema. Booleans, floats, leading zeros, control bytes, oversized values, and accidental integer coercion are rejected before loading a record. This hardens identifier handling; the review did not establish an ACL bypass through the previous behavior.

Legacy date migration now rejects malformed syntax, including NUL bytes, before calling PHP's date parser. Invalid date rows are skipped while other valid configuration remains eligible for import. This corrects malformed source-data handling rather than establishing a public storefront exploit.

Additional isolated regressions cover shared database rate-limit counters and their failure paths, original-store customer confirmation, injected addresses, sender validation, and translation restoration. No additional verified bypass was found in the reviewed CSRF/ACL, token ownership, product snapshots, purchasing guards, CSV export, or email lease logic. This remains a limited source review, not security certification.

The supplied baseline failure, corrected native declaration checks, executed suites, and remaining NOT RUN checks are recorded in `TEST_REPORT.md` and `COMPILATION_FIX.md`.

## 1.1.3 review — 2026-10-09

Scope: the uploaded 1.1.2 source, configuration render failure and button-driven controller/event/output paths. No live server, account, database or email delivery was accessed.

The missing native template IDs caused a configuration availability defect; no authorization bypass was established. The fix retains old stored IDs and custom templates. Date Save validation now rejects control suffixes and malformed sibling values before casting, and compares normalized bounds to reject padded reversed periods. Security-limit validation uses absolute string anchors so a final newline cannot pass. These are input-validation corrections, rather than evidence of a remote exploit.

The quote button handles malformed/null JSON without an uncaught exception, including purchase suppression. The admin submit guard no longer disables a form after an earlier validator has cancelled its submission. Response text still uses safe text insertion. The additional event tests exercise success, failure, retries, form state, product context and export button dispatch.

Source review and 82 new backend action tests found no further confirmed bypass of the existing POST/body form-key, ACL declaration, field/ID allowlist, migration approval, export sanitization or notification safeguards in the reviewed paths. The full PHP suite is 607 tests/1842 assertions; the focused count is already included. Actual ACL dispatch, escaping by the native renderer, Save Config persistence, SMTP, browser/CSP/FPC and concurrent database behavior remain NOT RUN. See TEST_REPORT.md for all scopes.
