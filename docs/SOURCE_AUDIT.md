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
