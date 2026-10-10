# Coresh Quote Request configuration and behavior

Location: **Stores → Configuration → Coresh → Quote Request**. All settings support Default → Website → Store View inheritance. Changing the configuration requires the `Coresh_QuoteRequest::config` ACL permission.

## General and display

| Setting | Default | Behavior |
| --- | --- | --- |
| Enable Quote Request | No | Enables the storefront feature and its associated catalogue restrictions. Existing request history is retained. |
| Button / link text | Contact Us for a Quote | Plain text, up to 120 characters. An empty value uses the default caption. |
| Apply quote requests to | Product | Global covers the catalogue; Product uses EAV flags; Category also considers the selected categories. |
| Selected categories | Empty | In Category mode, includes the selected categories and their descendants within the current store's root category. |
| Apply to all products | No | An additional Global switch. Customer-group and date restrictions still apply. |
| Enable for selected customer groups | No | No allows all groups; Yes applies the selected list. |
| Allowed customer groups | Empty | When restrictions are enabled, an empty list denies access to every group. Group 0 represents guests. |
| Request display | Button | Button and Link open the form; Label displays static text without a form. |
| Show Product Inquiry tab | No | Displays an inline form in a product detail page tab for an eligible product. Unavailable when Label mode is selected or inquiries are hidden. |
| Use date range | No | Limits availability by calendar date in the store's time zone. |
| Start / End date | Empty | YYYY-MM-DD format; both boundaries are inclusive. An empty boundary leaves that end of the period unrestricted. Invalid or reversed ranges deny requests. |

The product edit page provides Inquiry Label Mode (Use store text / Custom), Label For Inquiry Button, Allow Add to Cart, and Disclose Price. The last two offer Use store configuration / No / Yes. Overrides apply to a product with Enable=Yes after customer-group, date, and eligibility checks. Custom mode with an empty Label disables inquiries while retaining the price and cart rules; a direct POST cannot create a new request. To preserve an intentionally empty value across EAV persistence paths rather than falling back to another store view's caption, the EAV backend stores a single space and the policy normalizes it to empty text. Use Default sentinel values remain unchanged.

A Store View-scoped boolean attribute, `coresh_quote_request_enabled`, is added to products and categories. A category flag applies to its descendants. With the module enabled, the product first passes the customer-group and date rules; eligibility then considers Global mode, the product's own flag, an ancestor category flag, or a selected category in Category mode. Categories outside the store's root category do not make a product eligible.

Standard product listings and product detail pages use public product context. The customer's name and email are populated through a private customer-data section after a fresh load; they are not included in shared HTML cache entries.

## Email notifications

| Setting | Default | Behavior |
| --- | --- | --- |
| Send store notification emails | Yes | Controls the initial notification and administrative Send / Resend actions. Requests are saved even when email sending is disabled. |
| Store recipient email | Empty | A single address; falls back to `contacts/email/recipient_email` in the request's original store view. |
| Sender identity | General | A standard store sender identity. The customer's email is used only as Reply-To. |
| Store notification template | Module template | A Magento email template with scalar variables and escaped text. |
| Include product name | Yes | Includes the product name in the store notification. |
| Include requested quantity | Yes | Includes the requested quantity. |
| Include product ID | Yes | Includes the parent ID and, when a configurable variant is selected, the selected child ID. |
| Send customer confirmation | No | Optional brief confirmation that excludes the customer's message. |
| Customer confirmation template | Module template | A separate confirmation template. |

The product SKU and URL are included in the store notification. The three name / quantity / ID switches apply to both the initial notification and resends. Disabled values are omitted from the template variables. This does not remove stored information from the request or conceal an ID that can be inferred from the SKU or URL.

The email uses the store view saved with the request. If that store has been deleted, the module does not automatically switch to another store; the administrator receives an error. The `sent` status means that the message was successfully handed to Magento's email transport, rather than confirming delivery to the recipient's inbox.

## Catalogue mode

| Setting | Default | Behavior |
| --- | --- | --- |
| Allow Add to Cart | Yes | No hides the standard purchase control for eligible products in Luma/Blank. |
| Cart Hidden | No | Hides the standard minicart, cart icon, and known cart links. |
| Disable online purchasing | No | Blocks standard cart changes and order placement on the server, including REST/GraphQL operations through core quote services. |
| Hide eligible product prices | No | Removes standard PriceBox HTML for eligible products. |

For a catalogue without online purchasing, enable **Cart Hidden = Yes** and **Disable online purchasing = Yes**. The server restriction checks the current store, the quote object's store, and the persisted store of an existing quote. Administrative order creation and cron are exempt; orders already placed are not cancelled.

Hide Prices affects storefront HTML. REST/GraphQL, custom themes, and third-party structured-data blocks may still expose prices. Hyvä/PWA and third-party checkout/payment modules require separate integration and runtime verification.

## Validation and anti-spam

| Setting | Default | Allowed values |
| --- | --- | --- |
| Enable server rate limit | Yes | Boolean |
| Maximum requests per window | 5 | 1–1000 |
| Rate-limit window | 600 seconds | 1–86400 seconds |
| Maximum requested quantity | 1000000 | Positive DECIMAL(12,4), up to 99999999.9999 |
| Maximum message characters | 5000 | 1–20000; also subject to the TEXT byte limit |

The database counter is partitioned by store ID, `REMOTE_ADDR`, and time window; multiple web nodes share the same limit. The `X-Forwarded-For` header is not accepted as an IP source. Behind a reverse proxy, correctly resolving `REMOTE_ADDR` through trusted-proxy handling is an infrastructure responsibility. Visitors may share a limit when they share a proxy address.

## Products and quantity

The form supplies the product ID; the server resolves the name, SKU, URL, type, and website context. Client-supplied product names, SKUs, and URLs are not treated as trusted data.

Simple / virtual / downloadable products support product-level requests. Configurable products support a selected child: allowed attributes, selection completeness, and the actual parent-child relationship are validated. If no variant is selected, the request is saved as an explicitly identified parent inquiry. Bundle / grouped products are submitted as parent inquiries without bundle configuration; this limitation is recorded in the saved options and email.

Quantity is normalized without floating-point rounding and allows up to four decimal places. Fractional values are accepted only for products with `is_qty_decimal`; inquiries without a specific configuration use integer quantities. A request does not reserve inventory, create a Magento quote/order, or guarantee availability of the requested quantity.

## Managing requests

The administrative grid supports keyword search, filters, column selection, opening requests, CSV export, and bulk handling-status changes. Phone and Description are available as separate columns. CSV cells neutralize spreadsheet formula injection, including formulas preceded by spaces or control characters. Bulk updates operate in bounded batches and change only status/time fields. The request detail page shows saved information, email status, attempt count, the date of the last successful transport handoff, and the handling status `new / processing / closed`. Handling status and email status are independent.

ACL permissions: `view` permits reading; `manage` permits status changes; `send_email` permits sending. Mutations use POST with FormKey; GET does not send email. Resending remains available for historical products regardless of their current storefront eligibility. Resends use the current template and switches from the original store view's configuration.

Automatic retry queues and automatic deletion of personal data are not implemented. Request retention and deletion policies must be included in the store owner's regular operational process.

The administrative migration notice and separate preview/import page require the `Coresh_QuoteRequest::migrate` ACL permission. See `MIGRATION.md` and `SCREENSHOT_FEATURES.md` for details.
