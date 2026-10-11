# Quote submission predispatch correction — 1.1.6

Date: 2026-10-11, UTC. This release changes the purchase observer registration, not the quote form protocol or database schema. No production server was accessed or modified during preparation.

## Confirmed failure and source mismatch

The supplied new trace at `2026-10-11T03:59:06.096864+00:00` reports:

```text
Call to undefined method Coresh\QuoteRequest\Controller\Request\Submit\Interceptor::getRequest()
Observer/BlockPurchasingRoutes.php:34
```

The trace fails in the global `controller_action_predispatch` observer before the submission controller executes. Modern Magento actions implement `ActionInterface` or `HttpPostActionInterface` without inheriting the legacy controller's request/response getters. The observer must obtain the request from the event/current HTTP request and use the shared response instead.

The uploaded `Coresh_QuoteRequest-1.1.5(1).zip` has SHA-256 `aaddfc3881732d307dd895d002677d288df5c618597c71f87f70ffecf350d86f` and is byte-identical to the previously delivered 1.1.5 ZIP. Its observer already uses the corrected event/injected request; line 34 is blank. The new trace therefore executes instructions different from that attached source. An outdated deployment, another active release or loaded PHP-FPM bytecode are possible explanations, not established facts about the current server.

The supplied POST uses the expected field names and quantity format. The separate successful `customer/section/load` response with an empty messages list is normal section data and does not confirm that the quote submission reached its controller or was saved. Production session cookies, form keys, request tokens and customer addresses are not included in regression fixtures or this package.

## Concrete 1.1.6 application changes

| Change | Result |
| --- | --- |
| `Observer/EnforcePurchasingPolicy.php` | A logically named observer retains the corrected event/current request, shared response and purchasing checks. It never calls controller request/response getters. |
| Legacy global observer `coresh_quote_request_block_purchase_routes` | Explicitly disabled by name in frontend event configuration, including Magento's merged observer configuration. |
| New route-specific registrations | The purchasing guard runs on `controller_action_predispatch_checkout`, `_multishipping` and `_paypal`, the same three routes previously checked inside the global observer. Quote, Reports and customer section requests do not invoke it. |
| `Observer/BlockPurchasingRoutes.php` | A deprecated compatibility subclass of the new implementation, without its own properties, constructor or execution code. Extensions referencing the old class still receive the corrected behavior. |
| Installed-source checker | Resolves the new observer and pins the new observer, compatibility subclass and event configuration, rejecting a legacy or mixed trusted reference. |

Current and existing quote-store purchasing checks, checkout success/failure exemptions, native no-dispatch flag, safe AJAX HTTP 403 JSON and normal browser redirect remain in place. Global quote add/save/place/submit server guards remain unchanged. No new public controller getters, unauthenticated diagnostics, CSRF bypass or purchasing bypass is introduced.

Fresh event configuration is necessary: a new class name does not override old cached XML or an old active deployment. Rebuild autoload/DI and clean the configuration cache after installing the package, then refresh the relevant web PHP service. Follow `DEPLOYMENT_VERIFICATION.md`; changing the ZIP filename or root Composer requirement alone does not update a locked Git/package reference.

## Verification boundaries

The isolated PHP suite executes the actual module classes with explicit framework, persistence and mail doubles. JavaScript suites execute the actual AMD handlers with DOM/jQuery/AJAX doubles. Native-source probes exercise the real tagged Magento event converter, manager, invoker and FrontController methods against the new configuration with explicit infrastructure dependencies. These checks protect registration, modern action compatibility and purchase denial as well as form payloads, response outputs and retries.

The current executed results are in `TEST_REPORT.md`. Live Magento bootstrap, web requests, generated DI, PHP-FPM/OPcache state, real database persistence and email delivery remain unverified. Confirmation on the target server requires a fresh HTTP 200 accepted response, exactly one matching admin record, an identical-token retry returning that same ID and separate notification inspection after deployment.

Primary references: [Adobe events and observers](https://developer.adobe.com/commerce/php/development/components/events-and-observers/), and tagged Magento `Framework/App/FrontController.php`, `Framework/Event/Config/Converter.php` and `Framework/Event/Invoker/InvokerDefault.php` whose URLs and source hashes are recorded in the native report.
