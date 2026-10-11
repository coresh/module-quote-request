# Frontend submission correction — 1.1.4

Current registration hardening is documented in [SUBMISSION_FIX_1.1.6.md](SUBMISSION_FIX_1.1.6.md). This document retains the historical 1.1.4 correction.

Date: 2026-10-10, UTC. This update addresses the HTTP 500 reported when clicking **Send Quote Request** in the popup. It updates the supplied 1.1.3 package; no production server was accessed or changed.

This document records the original 1.1.4 application correction. The subsequent 1.1.5 attachment/log comparison confirms that the uploaded source already contains this fix, while the supplied web trace executes incompatible older instructions. See `DEPLOYMENT_VERIFICATION.md` for installed-source verification and the remaining web acceptance steps.

## Confirmed cause

The supplied log contains two timestamped submission failures and an initial, headerless Magento Reports exception trace. The submission exception is:

```text
Call to undefined method Coresh\QuoteRequest\Controller\Request\Submit\Interceptor::getRequest()
Observer/BlockPurchasingRoutes.php:34
```

`BlockPurchasingRoutes` runs on the generic `controller_action_predispatch` event. The old implementation called `getRequest()` on the event's controller action before checking its route. `Submit` implements the modern `HttpPostActionInterface`; that interface does not provide the legacy controller's request/response getters. The fatal occurs before `Submit::execute()`, including its validation, persistence and safe exception handling. Adding another catch inside `Submit` cannot handle this predispatch failure. The generic observer also breaks unrelated modern controllers; the supplied Reports trace demonstrates this.

## Production changes

Only two application files change from the supplied 1.1.3 sources:

| File | Correction |
| --- | --- |
| `Observer/BlockPurchasingRoutes.php` | Read the event's HTTP request, falling back to the injected current HTTP request. Use the injected shared HTTP response for denial. Do not call controller request/response getters. Use `ActionInterface::FLAG_NO_DISPATCH`. |
| `Model/Service/SubmitQuoteRequest.php` | Tell the user to refresh the page when an existing token cannot authorize the current payload/session. Reopening the same-product popup retains that token and is therefore insufficient. |

The observer retains its original six constructor argument names and order and adds request/response dependencies. Recompile Magento DI after updating the installed source so generated constructor metadata matches it. There are no new schema fields or data patches in this release.

Quote, report and other routes outside the existing purchase allowlist leave the observer without opening the checkout session or evaluating purchasing policy. Denied checkout, multishipping and PayPal actions still receive no-dispatch and a no-cache shared response: HTTP 403 JSON for AJAX, or the existing storefront redirect/message for navigation. Current-store and saved-cart-store restrictions remain in force. The exact existing checkout success/failure exceptions remain available.

The storefront's HTTP 500 message remains appropriate for an uncertain transport result. Its retry keeps the original payload and token; a token must not be rotated automatically after an uncertain failure because a request may already have been committed. An owned, matching retry acknowledges the saved request and does not insert or send again. Token ownership checks remain in force when the private session or payload changes. Refreshing creates fresh client state for that rejected token; it does not guarantee deduplication across lost sessions or intentionally different tokens.

## Executed verification

| Check | Result and scope |
| --- | --- |
| Native predispatch contract on both Magento tags | 17 fixed scenarios per tag, all PASS. Actual unmodified core `FrontController`, `ActionFlag` and event payload methods execute with explicit infrastructure doubles. The original observer produces 34 expected failures across the same two runs. |
| Observer and purchasing policy | 37 isolated tests, 244 assertions. Modern actions without getters, event/fallback request selection, allow/deny paths, AJAX/redirect output and current/saved-store policy checks. |
| Actual frontend payload through PHP submission pipeline | 57 isolated tests, 3373 assertions. Eight payload captures produced by the real AMD module are decoded and passed to the real controller, service, validation, product/eligibility policies, rate limiter, idempotency lookup and both email senders. Framework/session/catalogue/database/transport boundaries are explicit doubles. |
| Button/output and response boundary | 34 additional JavaScript scenarios. Six actual PHP controller responses are passed through the real AMD popup and inline handlers. Existing JavaScript suites also pass. |
| Full regressions | 701 PHP tests, 5459 assertions; 90 unique JavaScript scenarios; 28 Python tool tests. See `TEST_REPORT.md` for static, XSD and declaration checks. |

The payload fixture's URL-encoded representation is derived by a documented test encoder; it is not captured from a browser or real jQuery HTTP transport. PHP response fixtures are generated by the actual controller under the explicit doubles. Normal PHP and Node runs verify those recorded payloads/responses against current execution. These boundary tests verify compatible fields, decisions and UI outputs, rather than a live HTTP connection.

The native report includes source URLs/hashes, the supplied log hash, production hashes, doubles and NOT RUN boundaries. Magento 2.4.9 uses its actual Reports View action with reporting disabled; the 2.4.8-p5 report-shaped action is a declared modern-action double because that source is absent from the fetched tag. The module's real Submit action executes on both tags, but its submission-service dependency is a double in the native dispatch probe. The separate PHP pipeline executes that real service. The native framework request validator is a permissive double; the module's own POST/body form-key checks are exercised, while the full Magento CSRF lifecycle remains unverified.

## Verified button and backend outcomes

| Situation | Expected and isolated result |
| --- | --- |
| Valid request | One saved row with authoritative product data; HTTP 200 `accepted`; safe success text; button unlocked and form reset. |
| Double click while pending | One AJAX call; the pending payload cannot be replaced by another product click. |
| Owned retry of a saved request | HTTP 200 `duplicate`, same request ID, no second row or notification. |
| Missing/wrong POST form key or invalid input | Safe HTTP 400; no persistence/email; inputs retained and retry available. Missing client form key prevents AJAX entirely. |
| Changed ownership/payload with an accepted token | HTTP 400 refresh guidance; no accepted request ID or new row. |
| Rate limit exceeded | The current controller returns HTTP 400 with safe text for the rate limiter's localized exception; no new row. Rate-limiter persistence errors fail closed. The separate JavaScript HTTP 429 scenario tests client handling of such a transport/proxy response, rather than the module's current rate-limit status. |
| Persistence error, HTTP 500 or timeout | Safe failure; no false success acknowledgment. Client fields/token remain available for an unchanged retry. |
| Notification transport error after persistence | Request stays accepted and saved; failed notification state/logging are handled without exposing transport details to the customer. External delivery is not proved by this test. |

Configured product name, quantity and product ID inclusion flags are exercised through both notification senders. Client-supplied SKU/name/store/customer/status fields cannot replace the server snapshot. Error output/log checks exclude submitted customer details and internal exception text. Existing ACL, CSV, migration, private-profile and purchasing regressions remain in the full suite.

## Deployment and remaining acceptance

Update through the existing Composer or `app/code` installation method. Do not create a second installed copy or modify generated interceptors as the fix. Back up and validate the update on the project's staging deployment first. The package is not published to a public Composer repository; a local path/artifact repository must supply version 1.1.4 as described in `README.md`.

After installing the new source, run from the Magento root:

```sh
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean config block_html full_page
```

No production JavaScript changes are introduced in 1.1.4. Apply the project's normal static deployment procedure if also installing earlier changes or rebuilding deployed assets. Full installation, DI compilation and these commands were NOT RUN here.

On a disposable installed test deployment, verify:

1. Open a product popup, submit valid name/email/quantity/message, and confirm HTTP 200 JSON, one admin request, trusted product ID/name/quantity and the intended email in a test mailbox.
2. Repeat the same POST with the original token and session; confirm the same request ID and no duplicate row/notification. Test input errors, invalid form keys and purchase restrictions separately.
3. Inspect the storefront Network response and Magento exception/system reports. Confirm the old observer `getRequest()` fatal is absent, including Reports actions, and that unrelated failures are investigated by their own traces.
4. With Disable Purchasing enabled, confirm quote submission still works while prohibited checkout/multishipping/PayPal entry points are denied. Check ordinary purchasing again with the restriction disabled.

Three additional integration methods in `FrontendSubmissionTest` prepare real Magento dispatch/DI/database verification with external email disabled: accepted popup submission, identical retry and changed-payload token rejection. All 26 prepared integration assets remain **NOT RUN**. An installed Magento server, real browser, MySQL, SMTP, concurrency, full core CSRF validation and PHP 8.4/8.5 were not exercised in this environment. The supplied fatal is reproduced and corrected within the explicitly tested scope; this report does not claim the user's live server has been updated.
