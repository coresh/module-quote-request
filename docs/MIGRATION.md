# Migrating from Darsh_Callforprice

Migration is initiated manually and sends no email. Legacy tables, attributes, and configuration rows are retained. Existing target data is not overwritten.

1. Prepare a database backup and a staging copy. Disable `Darsh_Callforprice` before enabling the new storefront module.
2. Install the Coresh module and run `setup:upgrade`; the target schema and attributes must exist before migration.
3. Identify the source store ID. Legacy requests do not contain a store, so the operator must supply it; the program does not guess. If legacy records originate from several stores, first prepare a separate, verified import.
4. Run the preview:

```bash
# Example: 1 is the verified source storefront store ID.
php bin/magento coresh:quote-request:migrate-legacy --store-id=1
```

5. Review the `requests`, `already_imported`, `invalid_rows`, `attribute_values`, and `config_values` counters.
6. Explicitly add `--execute` to write the data:

```bash
php bin/magento coresh:quote-request:migrate-legacy --store-id=1 --execute
php bin/magento cache:clean config block_html full_page
php bin/magento indexer:reindex catalog_product_attribute
```

The command imports `darsh_callprice`, product/category `call_for_price` flags, and compatible general/email configuration values. Legacy custom email template identifiers are not copied automatically because they belong to the old module; select the new templates in the admin panel. Existing target configuration and attribute values always take precedence. A unique `legacy_id` prevents duplicate request insertion. The command can be rerun after a partial failure.

Legacy status `2` (Completed) maps to `closed`; all other source statuses map to `new`. The legacy module did not have a reliable separate email status. Invalid configuration dates are skipped rather than normalized to a different date.

Historical quantity is stored as `NULL` with `quantity_origin=legacy`, because the legacy module did not record it. Customer ID is not imported because the original form populated it with the product ID. Omitting it avoids a false account association. Rows with invalid email formats are skipped and counted in `invalid_rows`; their source records remain unchanged.

The new row's `created_at` reflects the import time, because the source schema did not contain a reliable timestamp. Email status `legacy` means that historical delivery cannot be verified. Name and message are retained as legacy snapshots and escaped on output. Where possible, URL/SKU/type are enriched from the current catalogue; these are current values, rather than reconstructed historical data.

EAV import supports Magento Open Source tables using `entity_id`. Adobe Commerce staging tables using `row_id` are not supported by this importer. Migration does not use one transaction for the entire dataset: progress is retained, and successfully imported rows are not overwritten on reruns.

To roll back, disable the new storefront feature or restore a consistent backup. There is no automatic deletion of migrated rows. Verification of actual database/EAV import in an installed Magento environment is reported separately in `TEST_REPORT.md`.

## Administrative interface in 1.1.0

Configuration → Coresh → Quote Request → Legacy migration displays detected sources and the availability of a verified adapter. The Legacy Quote Request Migration menu requires the separate `Coresh_QuoteRequest::migrate` ACL permission. Administrators can explicitly select the source store, run Preview without writes or email, inspect the counters, and confirm Execute. Approval is bound to the selected store and current admin session, expires after ten minutes, and is consumed before writing. All Preview/Execute actions use POST with FormKey; GET does not import anything.

Prepare a backup and disable the legacy storefront module before Preview. The preview is not a frozen snapshot: final counters may differ if the source database changes. Session approval does not replace a database lock across multiple administrative sessions; unique legacy IDs and insert-if-missing semantics make the import repeatable. After a partial failure, run a fresh Preview and repeat Execute.

Importing a nonempty customer-group list sets `restrict_customer_groups=Yes`; an empty list sets it to No. Existing target values are retained. The source table must contain all verified Darsh columns; a mismatched schema is rejected.

`Sparsh_ProductInquiry` / `Sparsh_ProductInquiryPro` are detected only. No automatic Sparsh adapter is provided: the supplied files do not contain its schema, configuration paths, or EAV identifiers. Darsh import is not presented as Sparsh data migration. A source package for the installed Sparsh version is required; a screenshot of a Migrate button does not define its data schema.
