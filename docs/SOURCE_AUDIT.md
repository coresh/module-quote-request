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
