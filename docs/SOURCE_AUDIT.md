# Darsh_Callforprice source audit

Reviewed archive: `Darshanmodi1427__Darsh_Callforprice(1).zip`.
SHA-256: `6b5d235cc2d7de33c7d01fef4607b59e7f46ab9469f928bfeeb9baf51b08a64c`.
It is byte-for-byte identical to the source archive supplied previously.

| Source file / lines | Defect | Replacement architecture |
| --- | --- | --- |
| Submit.php 34–43 | getParams followed by unrestricted setData | POST-only handling, form key, field allowlist/validator |
| Submit.php 45–56 | Product name taken from the client's POST | Server-side snapshot from ProductContextResolver |
| form.phtml 47–50,86 | Client-supplied status/customer_id trusted; customer ID set to product ID | Server-assigned internal fields |
| form.phtml / schema / admin email | No quantity field; product ID not emailed | Quantity field, DECIMAL storage, configurable email inclusion flags |
| Submit.php 68–71 | Exception echoed into JSON | Safe JSON result and an error code in the logger |
| Submit.php 83–113 | Hardcoded template and sender; store 0 | Configured identity/template and original store context |
| Edit.php 105–107; NewAction.php 42–44 | _isAllowed=true | Separate ADMIN_RESOURCE ACL permissions |
| MassStatus.php 25–28 | is_active updated instead of status | Dedicated request_status action |
| form.phtml 15–31,43,48 | Client data output without escaping | Context-aware escaping/private content |
| etc/di.xml 3–4 | Global preferences for core pricing | Scoped storefront plugin |
| Helper/Data.php 182–187,199–212,246–263 | Date logic includes everything; guest bypass; product flag checked inside the category loop | Centralized eligibility policy |
| list.phtml 118,172,219 | Repeated modal DOM IDs | One modal and a button carrying product context |
| Model/CallforpriceFactory.php 20–28 | Nonstandard manually implemented factory | Generated Magento factory |
| Setup/InstallData.php45 | Legacy EAV attribute deleted | Idempotent data patch and explicit migration |
| composer.json | Outdated PHP requirements, invalid name, conflicting license information | New manifest with retained LICENSE/NOTICE |

Cart Hidden and the server-side catalogue guard were absent from the source; they are new features. The source review did not establish direct SQL injection or evidence of exploitation. The absence of an explicit CSRF form key is a confirmed module defect; exploitability depends on the target Magento version's core validator and requires verification in a running test environment.

## Supplied 1.1.1 compilation regression

The 1.1.2 update baseline is the attached `Coresh_QuoteRequest-1.1.1(1).zip`, SHA-256 `fd79b58047f50a3f66fb065a5a7d7cc403d9ba3b883ac27385d3b39bc2f4bebe`. Its request-details block reproduces the user's readonly-property fatal against actual parent source from both target tags. The corrected block and every production class declaration pass the native source preflight. Previous syntax and isolated checks did not load this native ancestry. See `COMPILATION_FIX.md` and `TEST_REPORT.md` for the correction, evidence and remaining installed-Magento checks.

## 1.1.3 supplied-package review

Input: `Coresh_QuoteRequest-1.1.2(1).zip`, SHA-256 `5f606c292ce6c4da4e4dd48d0a965d3d1bf0271299daf4fdf3e6b0acbfd50c87`. Production sources match the preceding 1.1.2 release. The uploaded package omitted its referenced English docs and NOTICE; these are included again and the current reports are updated.

The native Email Template source requires IDs derived from field paths, while the input registered only different IDs. Actual tagged XML conversion and options lookup reproduce the exceptions and verify the corrected registration. Old identifiers remain equivalent aliases. Additional verified corrections cover malformed quote-button JSON, pre-cancelled admin submits, date sibling/type/order validation and numeric-limit end anchors. No production admin controller change was needed after the button action review.

## 1.1.4 supplied-package and log review

Input: `Coresh_QuoteRequest-1.1.3(2).zip`, SHA-256 `ab2985481969f3ae68712a7dff6e3b7b5e5265d22ea6859db17dd865e9c9fcfc`. Its 207 module files match the preceding 1.1.3 release. Referenced English documentation and NOTICE omitted from this upload are restored. The supplied submission text log has SHA-256 `14c03aa99c433097edfc84c61219186bb3e73e7a4b3dd6c76e5ada5a815f2d14`; it contains two complete Submit getter failures and an initial headerless Reports getter trace.

The generic predispatch observer calls action getters that modern ActionInterface controllers need not implement. The exact baseline fatal is reproduced under native FrontController/ActionFlag execution for both tags. The fix uses the event/current HTTP request and shared HTTP response without weakening purchase denial. A separate guidance correction requests a page refresh for a rejected reused token; reopening the same-product form does not reset it. These are the only two changed application files. No production JavaScript, controller, schema or email template change is needed for this correction.

Additional observer, actual-payload pipeline and AMD response regressions are included. The native/source/static reports identify provenance, doubles, repeated cases and NOT RUN boundaries. The raw server trace and external reference sources are not shipped. See `SUBMISSION_FIX.md` and `TEST_REPORT.md` for the root cause, validation and deployment acceptance.

## 1.1.5 attachment and active-source evidence

Input: Coresh_QuoteRequest-1.1.4(1).zip, SHA-256 `364c62eb650182f46f0a5ef44c800eec62cc751354113847a1e13ab3900dc547`. All 217 supplied module files match the preceding fixed release, including all 113 runtime files; omitted English documentation and NOTICE are restored. The new text log SHA-256 is `355f655a7c2f37a5e0720eb522532a7de8b337a1bed9409cd0250b37f3af3536`.

The fixed observer SHA-256 is `4dfb319d9c1d1d9e61a8763972191537691b266250545c017d3c034dad766772`; line 34 is blank and no controller request/response getter is called. The logged 17:23 Reports/Submit failures execute the old getter at line 34. This establishes different executed instructions, without determining the current deployed file or OPcache state. No application source changes are made in 1.1.5. It adds a standalone installed-source checker/tests and an explicit deployment/runtime/web acceptance procedure. The rate-limit outcome documentation is corrected to the actual HTTP 400 behavior.

Current tests, primary-source scanner evidence and attachment hashes are recorded in tests/reports. The checker uses a separate trusted reference, does not accept self-comparison and labels FPM/DI/HTTP/database/SMTP as unverified. Full server repair cannot be confirmed from the supplied archive and log alone. See DEPLOYMENT_VERIFICATION.md and TEST_REPORT.md.

## 1.1.6 attachment and fresh trace

Uploaded Coresh_QuoteRequest-1.1.5(1).zip SHA-256 is aaddfc3881732d307dd895d002677d288df5c618597c71f87f70ffecf350d86f, exactly the delivered 1.1.5 ZIP. At 2026-10-11T03:59:06.096864+00:00 the supplied web trace calls modern Submit Interceptor::getRequest() from BlockPurchasingRoutes.php:34. That attached fixed body has a blank line 34 and no controller getters. Executed instructions differ from the source; the specific deployment/FPM cause remains unproven.

Three runtime files change/add: Observer/EnforcePurchasingPolicy.php, its deprecated Observer/BlockPurchasingRoutes.php compatibility subclass and etc/frontend/events.xml. The old global key is disabled; the new guard uses the same three purchasing routes. Existing submission controller/service/database/mail/JS source is unchanged. The checker resolves/pins the new implementation, shim and event XML. Current regression and native proof: TEST_REPORT.md and native-event-registration.json.

## 1.1.7 supplied admin failure review

The new report identifies the actual Mageplaza requests grid data provider and an unregistered requests collection handle. The separate product-edit trace requires `formElement` for `coresh_quote_request_label_mode`. Both reveal defects in the supplied Coresh configuration: four notice overrides used direct field paths rather than Magento EAV container paths, and admin DI assigned only the Coresh handle to the shared collection factory. A later area configuration replaces an earlier global array under Magento's DI contract.

The release fixes both defects using native EAV paths and a private grid collection factory. Native baseline reproductions and corrected checks are reported separately from actual server execution. Mageplaza source files have not been supplied, so its installed registration and any additional independent defects cannot be certified. No guessed third-party collection registrations, module disabling or data deletion is included. See `ADMIN_UI_FIX_1.1.7.md`.
