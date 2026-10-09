# Magento 2 extension development prompt — Coresh_QuoteRequest

Use this prompt with the `Darshanmodi1427__Darsh_Callforprice.zip` archive. Complete the development work and deliver the finished extension as a ZIP, rather than only a description, plan, or code fragments.

This document preserves the original development brief. Its original 1.0.0 delivery filename and requirements describe that brief; the current release version and actual verification results are recorded in README, CHANGELOG, and TEST_REPORT.

## 1. Role and deliverable

Act as a Senior Magento / Adobe Commerce Architect, PHP developer, and security and automated-testing specialist.

Based on the attached `Darsh_Callforprice` source module, develop a modern **Coresh_QuoteRequest** module for sending product enquiries by email. Refactor its architecture, classes, methods, templates, and configuration. Retain useful source functionality, correct defects, and implement the requirements below.

Required identifiers:

- Magento module name: `Coresh_QuoteRequest`.
- PHP namespace: `Coresh\QuoteRequest`.
- Composer package name: `coresh/module-quote-request`.
- Manual installation directory: `app/code/Coresh/QuoteRequest`.
- Frontend route: `quoterequest`; for example, `quoterequest/request/submit`.
- Admin display name: `Coresh — Quote Request`.
- Default button text: **Contact Us for a Quote**.

A request is a customer's product enquiry. It is distinct from a Magento `Quote`, which represents the standard shopping cart. The basic workflow does not require a commercial B2B license, price negotiation, or request-to-order conversion.

## 2. Review of the attached source

Before making changes, read all source code and prepare a concise audit. The following issues have already been identified in the attached archive and must be verified and corrected:

| Original file | Observation | Required action |
| --- | --- | --- |
| `composer.json` | Package name lacks vendor/package format; PHP constraints target 5.5–7.0 | Create a valid, modern Composer manifest |
| `Controller/Form/Submit.php` | Uses `getParams()` and `setData($data)` for all input parameters | POST-only handling, explicit form-key validation, an allowed-field list, and a dedicated validator |
| `Controller/Form/Submit.php` | Product name and other fields come from the request; backend context is used in the frontend | Obtain product data on the server and correct controller dependencies |
| `view/frontend/templates/form.phtml` | `customer_id` is incorrectly populated with the product ID; quantity is missing | Determine customer ID from the authenticated context and add quantity |
| `Controller/Form/Submit.php` and email template | Email lacks quantity and product ID; selected templates/sender are not fully honored | Correct template variables and configuration handling |
| `Controller/Adminhtml/Callforprice/Edit.php` | `_isAllowed()` returns `true` | Implement module ACL resources for all admin actions |
| `etc/di.xml`, `Block/Callforprice.php`, `Block/Render.php` | Global preferences replace the core price renderer; direct ObjectManager usage and invalid HTML | Remove the global replacement and use narrowly scoped extension points |
| `Helper/Data.php` | Dates can enable a button independently of product selection; guest groups are treated differently; product flag is checked inside a category loop | Rewrite the eligibility policy and test boundary cases |
| `Setup/Install*`, `Setup/Upgrade*` | Legacy setup, EAV-attribute deletion, and different column-creation paths for fresh installation and upgrades | Declarative schema, idempotent data patches, preservation of existing data |
| `Model/CallforpriceFactory.php` | Handwritten factory with an incorrect model description | Use a generated Magento factory if required |
| `LICENSE`, `composer.json` | LICENSE contains GPL v3 while Composer declares Commercial | Verify the source terms, preserve copyright notices, and explain the final license metadata |

These are initial observations, rather than a completed audit or proof of exploitability. Review the remaining files, admin mass actions, email, and frontend as well. Distinguish confirmed defects, potential risks, and fixes actually verified. Do not assign an arbitrary MIT/Commercial license to reused code or remove original license notices. Record any ambiguity in the source terms explicitly.

## 3. Magento and PHP

Ensure source-code compatibility with Magento Open Source **2.4.8-p5** and **2.4.9**, and PHP **8.3 and later** within the verified PHP 8.x range.

Distinguish module source compatibility from supported platform combinations. At the time this brief was prepared, 2026-10-07, Adobe's official table listed PHP 8.3/8.4 for 2.4.8-p5 and PHP 8.5 for 2.4.9. Recheck that table and the actual Magento Open Source Composer package requirements before implementation. Do not claim Magento 2.4.9 works on PHP 8.3 based solely on the module's syntax.

Minimum verification matrix:

| Magento | PHP | Verification |
| --- | --- | --- |
| 2.4.8-p5 | 8.3 | Installation, DI compilation, integration and functional checks |
| 2.4.8-p5 | 8.4 | The same checks, with no deprecations introduced by module code |
| 2.4.9 | Version specified in current requirements; initial reference 8.5 | The same checks |
| Isolated module unit/static tests | 8.3, 8.4, 8.5 | Module-code compatibility without claiming support for an unsupported Magento/PHP combination |

Baseline frontend compatibility: Luma/Blank. Do not declare support for third-party themes, Hyvä, or PWA without adaptation and testing. Do not promise compatibility with future PHP or Magento versions without verification.

## 4. Clear naming and architecture

Replace opaque names and misspellings with names that describe purpose. Renaming must cover namespaces, XML, PSR-4, registration, routes, ACL, layout handles, UI components, email identifiers, configuration paths, JavaScript, and tests.

Recommended separation of responsibilities:

| Previous responsibility | New name |
| --- | --- |
| `Callforprice` model | `Model\QuoteRequest` |
| Resource model | `Model\ResourceModel\QuoteRequest` |
| Collection | `Model\ResourceModel\QuoteRequest\Collection` |
| General-purpose `Helper\Data` | `Model\Config`, `Model\Eligibility\QuoteRequestEligibility`, dedicated services |
| Form submission | `Controller\Request\Submit` |
| Input validation | `Model\Validation\QuoteRequestValidator` |
| Verified product-data resolution | `Model\Product\ProductContextResolver` |
| Request creation | `Model\Service\SubmitQuoteRequest` |
| Store notification | `Model\Email\AdminNotificationSender` |
| Customer confirmation | `Model\Email\CustomerConfirmationSender` |
| Form and button data | `ViewModel\ProductQuoteRequest` |
| Admin details and resend | `Controller\Adminhtml\Request\View`, `ResendEmail` |

Example methods: `isEnabled()`, `getButtonLabel()`, `getSubmitUrl()`, `canRequestQuote()`, `isCustomerGroupAllowed()`, `isWithinDateRange()`, `validate()`, `submit()`, `sendNotification()`, `isCartHidden()`, `isPurchasingDisabled()`.

These names are guidance, rather than a requirement to create unnecessary classes. Use constructor dependency injection, explicit properties, and types where compatible with core signatures. Do not rename framework callbacks such as `execute()`, `getDependencies()`, and `getAliases()`, or methods required by interfaces.

Do not use direct `ObjectManager::getInstance()` calls, modify core/vendor files, globally replace catalogue renderers, copy entire core product-list templates, or call `.load()` in catalogue loops. Prefer ViewModels, layout, targeted plugins, repositories/resource models, and standard UI components.

## 5. Admin configuration

Create **Stores → Configuration → Coresh → Quote Request**. Support Default / Website / Store View scopes and inheritance where applicable. The following paths are the agreed mandatory contract; also implement validation, labels, comments, and field dependencies.

| Configuration path | Purpose and default |
| --- | --- |
| `coresh_quote_request/general/enabled` | Enable the module; `0` |
| `coresh_quote_request/general/button_label` | Editable caption; `Contact Us for a Quote` |
| `coresh_quote_request/general/apply_to_all_products` | Show the button for all accessible products; `0` |
| `coresh_quote_request/general/customer_groups` | Permitted groups; an empty list means all groups, including guests |
| `coresh_quote_request/general/use_date_range` | Date restriction; `0` |
| `coresh_quote_request/general/from_date`, `to_date` | Date range in the store's timezone |
| `coresh_quote_request/email/enabled` | Send store notifications; `1` |
| `coresh_quote_request/email/recipient` | Recipient email; if empty, use `contacts/email/recipient_email` for the current store |
| `coresh_quote_request/email/sender_identity` | Standard Magento email identity; default `general` |
| `coresh_quote_request/email/admin_template` | Store notification template selection |
| `coresh_quote_request/email/include_product_name` | Include product name; `1` |
| `coresh_quote_request/email/include_quantity` | Include the requested number of product units; `1` |
| `coresh_quote_request/email/include_product_id` | Include the Magento product entity ID; `1` |
| `coresh_quote_request/email/customer_confirmation` | Customer confirmation; `0` |
| `coresh_quote_request/email/customer_template` | Customer confirmation template |
| `coresh_quote_request/catalog/hide_cart` | **Cart Hidden**: hide cart UI elements; `0` |
| `coresh_quote_request/catalog/disable_purchasing` | Server-side catalogue mode: disable purchasing; `0` |
| `coresh_quote_request/catalog/hide_prices` | Independently hide eligible products' prices; `0`, prices visible |

Add the product attribute `coresh_quote_request_enabled` through a data patch. Preserve category-based enablement through a separate, clearly named attribute. Add product attributes to all applicable attribute sets, rather than only Default. Do not remove legacy attributes during installation.

Display logic: the module is enabled **AND** the group is permitted **AND** the date is valid **AND** at least one condition is met: all products / product flag / category rule. A disabled date range imposes no restriction; an enabled range must not independently enable a button for every product. A product flag must also work for a product without categories. An empty group selection and guest group `0` must behave predictably.

Category rules must return consistent results on product, search, and category pages. Describe ancestor inheritance and handling of multiple categories within the current store root. Do not base eligibility solely on `current_category`. Add a truth table and tests. Cached group/store/date rules must not survive configuration changes or a date-range boundary.

## 6. Enquiry form and product data

Implement a button that opens a keyboard-accessible modal form with name, email, requested quantity, and message; telephone is optional. Default quantity is `1`, representing units of the selected product rather than the number of distinct line items.

Client-supplied fields: `product_id`, `quantity`, `customer_name`, `customer_email`, `message`, optional telephone, and explicitly permitted variant-selection parameters. Handle form key, anti-spam, and idempotency fields separately. Do not pass unexpected parameters to the model.

On the server:

1. Check module enablement, eligibility, and current store.
2. Resolve the product through a Magento repository in the store context. Verify website assignment, status, and permission to enquire about it. A hidden simple child is permitted only after verifying its relationship to an accessible configurable parent.
3. Obtain name, SKU, URL, ID, and product type from the verified catalogue. Do not trust submitted `product_name`, `sku`, URL, `customer_id`, `store_id`, status, or email flags.
4. Determine customer ID from the authenticated context; use `NULL` for guests. Do not associate a request with an account solely by the entered email.
5. Validate quantity for numeric format, finiteness, positivity, permitted precision, and an upper limit. Reject arrays, zero, negative numbers, NaN/INF, and excessive values. Handle integer/fractional quantities according to the documented product policy. A request does not reserve stock and need not satisfy purchase-time stock checks.
6. Support direct enquiries for simple / virtual / downloadable products. For configurable products, store parent ID and, when a variant is selected, its verified child ID/SKU and attributes; do not replace parent quantity with the number of variants. Bundle / grouped products may use a general parent enquiry explicitly indicating that no configuration was selected; if selected items are stored, verify their membership. Test each claimed product-type behavior.
7. Validate maximum field lengths, email validity, and absence of CR/LF in email headers. Treat messages as text, rather than HTML or template directives.
8. Save the request and build the email from verified data.

Do not use fixed DOM IDs for multiple products. Display buttons on category/search pages with the correct product context. Handle network errors, server errors, and repeated clicks. Insert response messages as text, rather than through uncontrolled `.html()` calls.

## 7. Email and admin actions

After successfully accepting a request, send a notification to the store's configured email. The standard template includes:

- Request number, date/time, and store.
- Customer name, email, telephone, and message.
- **Product name**, **requested quantity**, and **product ID**, controlled by the three include settings.
- SKU and a safe product link; parent/child or configuration details where applicable.

Name, quantity, and ID must also be available in the admin UI. Disabling an include flag must actually remove the field from the standard email while preserving it in storage. Do not pass an excluded field's actual value to a third-party template. Document the contract: name / quantity / ID variables and separate visibility booleans.

Use the standard Magento `TransportBuilder`, configured sender identity, and template identifier from store-scoped settings. From belongs to the store; the validated customer email is Reply-To. Template options must use the request's original store, rather than always the default/admin store. Pass scalar-data arrays compatible with strict email templates. If inline translation is suspended during sending, restore its state in `finally`.

Implement **Send / Resend Notification Email** on the admin request page: send to the configured recipient for the request's store with the selected product fields. Require POST, a form key, and a dedicated ACL; do not permit arbitrary frontend users to choose recipients. Apply template and field settings consistently to initial sends and manual resends.

Store email status, attempt count, timestamp, and an anonymized last-error code. An email failure must not destroy a saved request. Distinguish “request accepted” from “email handed to the mail transport.” Successful `sendMessage()` does not prove delivery to an external inbox; receipt requires a real mail-test environment.

Protect against accidental duplicate persistence/sending from double clicks and concurrent requests: an idempotency token, server-side checks, and a unique constraint or equivalent mechanism. Admin resend is a separately authorized attempt. Describe the deduplication window and transport-failure behavior. Do not promise exactly-once external email delivery.

## 8. Cart Hidden and catalogue mode

Describe and implement these two independent controls:

| hide_cart | disable_purchasing | Behavior |
| --- | --- | --- |
| 0 | 0 | Standard cart / checkout; enquiry button follows its rules |
| 1 | 0 | Cart icon, minicart, and cart links are hidden; purchasing remains permitted on the server; explain this in the admin field comment |
| 0 | 1 | Server-side purchasing restriction; purchase CTAs are replaced by a message/enquiry; cart elements must not lead to a working checkout |
| 1 | 1 | Full catalogue mode: cart hidden, checkout and purchase operations blocked, email enquiries available |

With `disable_purchasing=1`, block standard frontend cart add/update/checkout and REST / GraphQL purchase operations, including existing active carts and order placement. Implement a shared policy through verified extension points in both target versions; CSS or redirects alone are insufficient. List covered endpoint/service entry points and negative tests.

Check the existing cart/quote's store and current storefront context so a changed Store header or store parameter cannot bypass the restriction. Do not disable Magento modules or globally modify payment credentials/configuration. Preserve access to and processing of existing orders and normal administrative workflows. This mode cannot cancel an external payment transaction already in progress; document that boundary.

With `hide_prices=0`, prices remain visible independently of cart hiding. If prices are hidden, clearly describe and test the scope: HTML, structured data, and APIs. Do not claim price confidentiality when only markup is hidden.

Disabling the module must restore normal storefront behavior and preserve requests. Historical requests remain available to administrators; initial/resend notification behavior follows explicit email settings. Do not delete products, carts, orders, or secrets.

## 9. Storage, admin UI, and migration

Use `etc/db_schema.xml` for the `coresh_quote_request` table and data patches for EAV. Add a separate items/options table if necessary. Installation and repeated `setup:upgrade` must be idempotent.

Store at least: request ID, store ID, nullable customer ID, customer name/email/telephone, message, product ID, name/SKU/URL snapshot, quantity as DECIMAL with justified precision, parent/child data where necessary, request status, email status, attempts, created_at, updated_at, last_email_sent_at, and idempotency metadata.

Separate handling status from email status. Deleting a product/customer must not delete request history: choose FK policies and nullable relationships that preserve snapshots. Do not expose customer PII through frontend APIs or shared cache entries.

Add a modern admin grid with request details, filters, quantity, name, product ID, store, date, statuses, and resend. Define ACL resources for configuration, view, manage, and send_email; reads and mutations must check the appropriate resources. Any added delete/mass actions also require ACL and POST/CSRF protection.

Prepare a documented migration for legacy `darsh_callprice`, configuration paths, and EAV flags. Do not run it destructively or silently. Fresh installation must not require legacy data. Existing-request migration must be explicit and repeatable without duplicates, with a preliminary dry run and report. Missing legacy quantity must not be represented as known: preserve `NULL` or a documented fallback with an origin marker. Do not automatically delete the legacy table/attributes. Installation instructions must prevent simultaneous frontend activation of the old and new modules.

## 10. Security, cache, and quality

Required checks and corrections:

1. Frontend submit implements `HttpPostActionInterface`; explicitly validate the form key, including AJAX. Do not bypass CSRF with a `CsrfAwareActionInterface` implementation that always permits requests.
2. All admin mutations require POST, a form key, and the correct `ADMIN_RESOURCE`. Missing permission must also block direct URLs.
3. Use safe framework/resource APIs for SQL without concatenating input. Avoid mass assignment, arbitrary filesystem paths, uploads, `eval`, shell execution, and remote-code loading.
4. Escape HTML, attributes, URLs, and JS/JSON according to context; do not use `|raw` on customer messages. Test stored XSS in the admin grid, form, and email, including textarea/attribute terminators and template-directive payloads.
5. Use RequireJS / `data-mage-init` / standard Magento JS mechanisms. Do not add unsafe-inline or wildcard CSP to support the form. Verify behavior under applicable strict CSP.
6. Anti-spam: a honeypot and configurable server-side rate limit shared across workers/nodes, without trusting arbitrary X-Forwarded-For. Any CAPTCHA must be optional and must not become a mandatory external dependency.
7. Load authenticated customer data through private content or a form with the correct private context. FPC/Varnish must not expose it to other visitors. Do not embed a fixed form key/token in shared cache.
8. Do not make the catalogue globally `cacheable=false`. Do not load each product and all its categories separately for every card; use batch queries, collection attributes, and bounded caching.
9. Do not return stack traces, SQL, secrets, or raw exception text to visitors. Log technical context and a safe request reference without full customer messages or tokens.
10. Read configuration flags and email settings consistently in the relevant services. Check current eligibility, product/date/group rules, and frontend enablement when creating a request. Admin Send/Resend for historical requests must apply ACL, `email/enabled`, the persisted store, and product snapshot; current dates, customer groups, product deletion, or frontend disablement alone must not prevent sending. Interpret dates in the store timezone and persist timestamps in an agreed UTC format.

Correct discovered defects and add regression tests. The report must describe the verified security surface and remaining limitations; an unsupported claim that the module is “fully secure” is unacceptable.

## 11. composer.json and packaging

Create a fully valid `composer.json`:

- `name`: `coresh/module-quote-request`.
- `type`: `magento2-module`.
- `description`: a clear description of email quote requests and catalogue mode.
- PHP constraint of at least 8.3, for example `^8.3`, if actual checks support the claimed branch.
- PSR-4 `Coresh\\QuoteRequest\\` → module root.
- Autoload files: `registration.php`.
- Runtime dependencies on the Magento modules/framework actually used, with ranges verified against both versions.
- Correct, substantiated SPDX license metadata.
- Compatible development dependencies for the chosen tests and static analysis.

Do not add a full Magento project or product package as an unnecessary extension runtime dependency. A Composer constraint alone does not establish compatibility. Do not use `--ignore-platform-reqs` to obtain a misleading success result.

Deliver **`Coresh_QuoteRequest-1.0.0.zip`** with one top-level directory, `Coresh_QuoteRequest/`. It contains the Composer module root: `composer.json`, `registration.php`, `etc/`, PHP/JS/templates, tests, and docs. For manual installation, place its contents in `app/code/Coresh/QuoteRequest`.

Exclude `.git`, `vendor`, generated classes, Magento `env.php`, real credentials, PII-bearing logs, database dumps, and the original source archive. Include documentation, a changelog, license notices, and a SHA-256 manifest with clearly documented coverage; the manifest must not recursively include its own hash.

## 12. Actual testing and confirmation

Add and execute meaningful tests that verify behavior rather than merely the presence of classes:

| Level | Required scenarios |
| --- | --- |
| Unit | Configuration inheritance/fallback, eligibility truth table, guests/group restrictions, dates/timezone, quantity/email validation, selection resolution, template field inclusion |
| Integration | Fresh install and repeated upgrade, repositories/schema, product website restrictions, valid submission, persistence/email status, sender/recipient/template/store, migration dry run/re-run |
| Security / regression | GET submit, missing/invalid form key including AJAX, forged product name/ID/customer/store/status, scalar-versus-array inputs, XSS/template injection, header injection, direct ACL URLs, rate limit |
| Functional | Button/modal on product/category/search pages, different products, guest/authenticated, configurable and general bundle/grouped enquiry, caption changes, cart hiding, admin details/resend |
| Catalogue mode | Four hide_cart/disable_purchasing combinations; visible prices; frontend/REST/GraphQL add/update/place-order restrictions; existing cart and attempted Store switching; disabled mode |
| Cache/concurrency | Cold/warm FPC, no PII leaks or stale form keys, two-store configuration, double/concurrent submit, transport failure and admin resend |
| Static / packaging | PHP syntax on declared versions, Magento Coding Standard, applicable PHPStan/Psalm, Magento XSD validation, `composer validate --strict`, PSR-4 and residual legacy-name checks, ZIP checks |

Check `setup:upgrade`, `setup:di:compile`, `setup:static-content:deploy`, and frontend/email rendering on both target versions. Also check production mode and absence of new module errors/deprecations in logs.

Use the Magento test harness for integration tests and MFTF or a justified alternative for functional tests. Perform email checks with a test transport/mail catcher without sending to real customers. Verify subject, From, Reply-To, recipient, and body, including product name, quantity, and product ID.

If an environment or the required version is unavailable, create tests and reproducible commands, continue available work, and explicitly record **NOT RUN** with the reason. Do not describe written tests as passed, unit tests as integration tests, or a mock transport as evidence of external delivery. Do not modify a production store for this task.

For each suite, record Magento/PHP/Composer versions, environment, command, exit code, passed/failed/skipped counts, date, and log path. Distinguish module failures from third-party dependency warnings. Correct actual failures and rerun affected checks.

## 13. Documentation and acceptance criteria

Include in the final archive:

- `README.md`: purpose, dependencies, Composer/manual installation, enablement, upgrades, and rollback without request loss.
- `docs/CONFIGURATION.md`: settings, store/website/default precedence, email examples, button text, and catalogue mode.
- `docs/REFACTORING_MAP.md`: original → new classes, methods, XML/config/routes, and removed dependencies.
- `docs/SOURCE_AUDIT.md`: file/line evidence for original defects and corrections.
- `docs/SECURITY_REVIEW.md`: reviewed surface, results, and limitations.
- `docs/TEST_REPORT.md` and `docs/COMPATIBILITY_MATRIX.md`: actual results, PASS/FAIL/NOT RUN.
- `docs/MIGRATION.md`: backup, dry run, missing-quantity handling, repeat execution, and rollback.
- `CHANGELOG.md`, license/attribution, tests/configuration, and verification reports without secrets.

The task is complete when:

1. `Coresh_QuoteRequest` is correctly registered, clearly named, and has a valid Composer manifest.
2. Admin users can edit the button text; its default is `Contact Us for a Quote`.
3. The customer enters quantity; the server sets product name and product ID.
4. Store notifications contain all three product fields when enabled and omit disabled fields.
5. Administrators can view these fields and send/resend notifications with the appropriate ACL.
6. Cart Hidden works; the independent purchasing restriction is implemented and tested. Prices remain visible by default.
7. Discovered source defects are corrected, client parameters are not mass-assigned, and CSRF/ACL/XSS protection is verified.
8. Claimed Magento/PHP combinations are verified through actual checks or explicitly marked NOT RUN; all failures are explained.
9. The ZIP is intact, has an unambiguous installation structure, and contains complete source, tests, and documentation.

Final response in Russian: a link to the finished ZIP, a concise implementation description, a table of compatibility actually verified, a test summary, and material limitations. Do not substitute a plan for the finished module or claim verified testing without logs.

## 14. Primary verification sources

Recheck documentation currency when executing this brief:

- Adobe system requirements: https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements
- Magento Open Source source: https://github.com/magento/magento2
- Adobe Commerce PHP developer documentation: https://developer.adobe.com/commerce/php/development/
- Adobe Commerce frontend documentation: https://developer.adobe.com/commerce/frontend-core/

The actual installed target-version source is authoritative for signatures, dependency constraints, and extension points. Do not copy unverified methods from the legacy module or arbitrary examples.
