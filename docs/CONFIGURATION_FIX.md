# Configuration rendering fix — 1.1.3

## Cause

Opening `admin/system_config/edit/section/coresh_quote_request/` builds the configuration form. The native email-template source derives a default ID from each field path; it does not derive that ID from the stored/default field value. In 1.1.2 both required IDs were missing, so options initialization raised `Email template is not defined.` and interrupted rendering. The customer-template field is initialized even when its Depends condition hides it.

| Configuration path | Required native default ID | Preserved earlier ID |
| --- | --- | --- |
| `coresh_quote_request/email/admin_template` | `coresh_quote_request_email_admin_template` | `coresh_quote_request_admin_notification` |
| `coresh_quote_request/email/customer_template` | `coresh_quote_request_email_customer_template` | `coresh_quote_request_customer_confirmation` |

Both canonical IDs are registered. The earlier IDs remain aliases with the same file, module, area, type and label. New XML defaults use the canonical IDs. Existing saved aliases and numeric custom-template IDs are not rewritten; mail lookup remains valid. The native dropdown presents the equivalent canonical default, which may be saved on the next configuration Save.

## Evidence

The original uploaded XML reproduces the exception for both fields on Magento 2.4.8-p5 and 2.4.9. The fixed XML passes all four field/tag checks. The probe uses the actual tagged Email XML converter, Config Field options, Email Template source and Email Config lookup, with explicit storage/collection adapters. The report records primary-source URLs and SHA-256 hashes. It does not run the complete form, HTTP controller, database, browser or SMTP.

`tests/Unit/ConfigTemplateContractTest.php` protects the file contract. `tests/Integration/ConfigSectionTest.php` prepares authenticated full-page rendering and ACL checks; all its tests remain NOT RUN until executed in a real Magento integration environment.

## Update and acceptance

Replace the installed module using the same Composer or manual method as before. Avoid installing duplicate Composer and app/code copies. Follow the existing deployment workflow for dependency injection and static content in every configured admin/store locale. The JavaScript assets changed in this release.

After deployment, clear the relevant Magento caches from the Magento root:

```sh
php bin/magento cache:clean config block_html full_page
```

Sign in with a role allowed `Coresh_QuoteRequest::config`, reopen Stores → Configuration → Coresh → Quote Request, and verify General, Email, Catalogue and Security groups. Confirm that both email-template lists contain their Default option and any custom templates. Save, reload and check the selected scope. A role without configuration permission must be denied.

Exercise quote modal/inline Send, Save Status, Send/Resend, grid Export, mass status, migration Preview and confirmed Import on staging using a captured mail transport and disposable migration data. Check user messages, redirects, retry controls and permissions. The event/controller tests cover isolated behavior; a real browser and Magento dispatch remain required.

If the page remains empty, inspect the actual server exception log: another deployment, ACL, source-model or dependency error could coexist. The preparation environment did not access the server logs or modify the live site.
