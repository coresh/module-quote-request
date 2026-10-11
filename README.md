# Coresh Quote Request 1.1.9

A Magento Open Source extension for product enquiries: **Contact Us for a Quote** → form → saved request → notification email to the store. It provides store-view configuration, an admin grid, request details and email resend, quantity and product ID fields, Cart Hidden, and a separate server-side purchasing restriction.

Target versions: Magento Open Source 2.4.8-p5 and 2.4.9. The module uses PHP 8.3+ and Luma/Blank-compatible storefront markup.

Features include per-product enquiry controls, configurable button text and display modes, category and customer-group restrictions, price visibility, an optional product information form, saved requests, email notifications, and administrative search, export and status management.

## Installation

Validate installation on a Magento staging environment first. No production site or production data was modified when preparing this package.

### Manual installation

The archive contains one directory, `Coresh_QuoteRequest`. Place its contents in `app/code/Coresh/QuoteRequest`, with `registration.php` directly inside that directory.

If replacing another storefront enquiry extension, disable it through the project's normal deployment procedure before enabling this module to avoid duplicate forms and conflicting price renderers. After copying the files, run the following from the Magento root:

```bash
php bin/magento module:enable Coresh_QuoteRequest
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US
php bin/magento cache:clean config layout block_html full_page translate
```

The examples deploy en_US. Add other configured store locales when required. The new module is disabled in its configuration by default; enable it under Stores → Configuration → Coresh → Quote Request.

### Composer

`composer.json` is located at the extension root. For a local installation, use a Composer path repository pointing to the extracted package root outside `app/code`, or your own Composer/artifact repository. Example for an existing directory at `packages/coresh/module-quote-request`:

```bash
composer config repositories.coresh-quote-request '{"type":"path","url":"packages/coresh/module-quote-request","options":{"versions":{"coresh/module-quote-request":"1.1.9"}}}'
composer require coresh/module-quote-request:1.1.9
php bin/magento module:enable Coresh_QuoteRequest
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US
php bin/magento cache:clean config layout block_html full_page translate
```

Do not install separate copies through Composer and `app/code` at the same time. Development dependencies are required only for development; testing instructions are in `tests/README.md`.

## Quick configuration

1. Enable General → Enable. Select Scope → Global to apply the rules to the entire catalogue.
2. Set Button Text, for example `Contact Us for a Quote`.
3. Set Admin Email → Recipient. If empty, the contact email configured for the current store view is used.
4. Enable product name, quantity, and product ID in notification emails. All three fields are enabled by default.
5. For a catalogue without online purchasing, enable **Cart Hidden** and **Disable Purchasing**. Leave **Hide Prices = No** if prices should remain visible.
6. Verify an actual email in a test mailbox. Emails use the installed Magento transport; SMTP configuration, DNS, and final delivery are outside the extension.

All settings, defaults, and product-type behavior are described in the [configuration guide](docs/CONFIGURATION.md).

## Monitoring and managing quote requests

### Open the request list

In the Magento Admin sidebar, go to **Sales → Coresh — Quote Requests**:

1. Sign in with an administrator account permitted to open **Sales** and view quote requests.
2. Open **Sales** in the main navigation.
3. Select **Coresh — Quote Requests** to display the saved enquiries.

This is the operational workspace for monitoring and processing enquiries submitted through the storefront popup or product information form. Module settings are configured separately under **Stores → Configuration → Coresh → Quote Request**. A successfully saved enquiry remains available in the request list even if its notification email fails.

### Read and filter the request list

The list initially sorts by **Request ID** in descending order. Use the search box and column filters to find a customer, product or request, and use **Columns** to choose which information is visible. Key columns are:

| Column | Purpose |
| --- | --- |
| Request ID | Unique identifier of the enquiry; distinct from the product ID. |
| Product Name, Product ID, SKU | Identify the product captured when the enquiry was submitted. |
| Quantity | Requested quantity recorded with the enquiry. |
| Customer Name, Customer Email, Phone | Contact details supplied by the customer; the phone field may be empty. |
| Description | The customer's enquiry message. |
| Store ID | Store View associated with the enquiry and its email configuration. |
| Request Status | Administrative processing status: `new`, `processing` or `closed`. |
| Email Status, Email Attempts, Last Email Sent | Monitor the store notification's sending state, attempt count and last successful transport handoff. |
| Inquiry Date | When the enquiry was created. |

For daily monitoring, filter **Request Status** by `new` to find enquiries awaiting review, or **Email Status** by `failed` to find notification failures. Reload the list to check for newly saved requests. Use **Actions → View** on a row to open the complete request details, including the stored product URL, selected variant/options where applicable, timestamps and the last email error code.

### Process an enquiry

In the request details, choose a **Request Status** and click **Save Status**:

| Status | Suggested use |
| --- | --- |
| New (`new`) | The enquiry has been received and awaits review. New storefront submissions start in this state. |
| Processing (`processing`) | An administrator is reviewing the request or preparing a response. |
| Closed (`closed`) | The administrator considers the enquiry handled. |

These statuses are managed by the administrator. Changing a status records the workflow state; it does not send a customer response or create an order. To update several requests, select their rows and use **Mark as New**, **Mark as Processing** or **Mark as Closed** from the grid's actions menu. **CSV** export is available from the grid's export control and uses its selection and filters.

### Monitor and resend notification emails

The **Email Status** describes the store notification independently of the enquiry's processing status:

| Email status | Meaning and next step |
| --- | --- |
| `pending` | The request is saved and has not completed a notification attempt. Inspect its details before sending manually. |
| `sending` | An attempt has been claimed for sending. The module prevents overlapping attempts; check again before retrying. |
| `sent` | Magento's configured mail transport completed without an exception. Confirm actual delivery in the recipient's mailbox when needed. |
| `failed` | The notification attempt failed. Review **Last Error Code**, the relevant Magento logs and the store's email settings before retrying. |
| `disabled` | Admin notifications were disabled when the request was submitted. Enable them for the request's original Store View before sending manually. |

To send or retry the store notification, open **Actions → View** and click **Send / Resend Notification Email**. This action uses the saved product snapshot and the original store's current recipient, sender and template settings. It resends the store notification, rather than generating a customer quotation. Inspect **Email Status**, **Email Attempts** and **Last Email Sent** after the action. Sending an email does not change **Request Status**.

### Diagnose notification failures

Version 1.1.9 corrects the native mail Reply-To API and uses a fresh mail builder for each store notification and customer confirmation. Saved requests remain available when sending fails. Known failures now produce a safe, actionable administrator message; **Last Error Code** and Magento logs identify the failure stage without recording SMTP credentials or customer messages.

1. Open the request under **Sales → Coresh — Quote Requests** and identify its original Store View. Select that Store View when checking **Stores → Configuration → Coresh → Quote Request → Admin Email**.
2. Enable store notification emails. Verify **Recipient**, or the store's contact recipient when this field is empty. Choose a valid **Sender Identity** and configure its sender name/address under **Stores → Configuration → General → Store Email Addresses**.
3. Select the module's store notification template or a valid custom template. Check custom templates and the store's email header/footer if the reported failure concerns template preparation.
4. If the reported failure concerns the transport, check the installed mail/SMTP service and its logs, then verify delivery in a test mailbox. A transport error can occur after a provider accepted the message; check delivery before retrying.
5. If a notice says the email was handed to the transport but its status could not be updated, check delivery and database availability before resending. The module does not mark a completed handoff as a failed send. A status or lease may remain stale when the database is unavailable.

An active attempt holds a ten-minute send lease. This reduces overlapping sends; it cannot guarantee exactly-once delivery through an external mail provider. Do not repeatedly click Resend while an attempt is active or delivery is uncertain. After correcting the reported issue, make one controlled resend and inspect its status and the recipient's mailbox. Configuration failures, transport handoff and final inbox delivery are separate checks.

### Grant administrator permissions

Go to **System → Permissions → User Roles**, open the relevant role and select **Role Resources**. For a restricted role, grant access to the **Sales** container (`Magento_Sales::sales`), then grant the required permissions under **Coresh Quote Request**. Magento checks the parent menu's permission before displaying its child entries. Access to the Sales container does not require granting Orders, Invoices or other sales operations; review those child permissions individually.

| Permission | Available actions |
| --- | --- |
| View Quote Requests | Open the list and request details; search, filter and export requests. Also grant the Sales container permission to display the menu. |
| Manage Quote Request Status | Change individual request statuses and use the bulk status actions. Also grant View Quote Requests so the administrator can open the workspace. |
| Send Notification Email | Use Send / Resend Notification Email. Also grant View Quote Requests to access request details. |

Configuration access is controlled separately by **Coresh Quote Request Configuration** under the Stores configuration resources. Save the role and sign out/in before checking access with an account assigned to that role.

If **Sales → Coresh — Quote Requests** is missing, confirm that `Coresh_QuoteRequest` and its `Magento_Sales` dependency are installed and enabled, verify the assigned role's **Sales** and **View Quote Requests** permissions, clean the configuration cache and sign out/in. If the menu opens but the list fails to load, check the current Magento logs and the module installation instructions. When the list loads successfully, an empty result means that no requests match the current filters. Clear filters before deciding that a saved enquiry is missing.

## Upgrade and rollback

Version 1.1.9 changes the mail builder dependency to a generated `TransportBuilderFactory`. After replacing the package through the existing installation method, run `setup:upgrade`, `setup:di:compile`, static-content deployment and cache cleaning as shown above. Refresh the web PHP workers through the hosting provider's supported procedure when old application code remains loaded. The request table and notification templates retain their existing structure.

Version 1.1.8 moves the request workspace to **Sales → Coresh — Quote Requests**. The menu label, request URL and permission identifiers remain the same. After updating through your existing installation method, run the installation commands above, including `setup:upgrade` to refresh module sequencing and `cache:clean config layout block_html full_page translate`. Sign out/in to refresh the administrator session. The legacy migration page remains under **Customers → Legacy Quote Request Migration**.

Before replacing the version, back up the files and database using your project's normal procedure. Version 1.1.1 removes the bundled Russian translation dictionary so the extension uses its English source messages in every locale unless another translation package overrides them. When upgrading by copying files over an existing installation, remove the obsolete `i18n/ru_RU.csv` from this module's directory; a file overlay does not delete it. Clean the translation cache and regenerate static content for all configured store locales. See `i18n/README.md`. Update through the same installation method, then run `setup:upgrade`, compilation, and cache cleaning on staging. Do not manually remove schema or EAV data.

To temporarily restore normal storefront behavior, disable General → Enable. To disable the extension entirely:

```bash
php bin/magento module:disable Coresh_QuoteRequest
php bin/magento cache:clean config block_html full_page translate
```

Disabling the extension preserves requests. Disabling its catalogue purchasing guard restores normal purchasing. Tables, attributes, and legacy data are not removed automatically. Legacy request migration is available under **Customers → Legacy Quote Request Migration** for administrators with the corresponding permission.

## Scope and limitations

This extension handles email enquiries; it does not implement a full B2B RFQ or negotiable-quote workflow. Bundle/grouped products can be submitted as a general parent-product enquiry with no selected configuration. Price hiding applies to the storefront and does not guarantee that prices are absent from REST/GraphQL responses. Payment credentials are not changed, and admin processing of existing orders remains available.

License and provenance: `LICENSE` and `NOTICE.md`.
