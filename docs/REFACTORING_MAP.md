# Refactoring map

| Original component | New responsibility |
| --- | --- |
| `Darsh_Callforprice` | `Coresh_QuoteRequest`, namespace `Coresh\QuoteRequest` |
| `Helper\Data` | `Model\Config`, `Model\Eligibility\QuoteRequestEligibility`, `CategoryRuleResolver`, `DateRangePolicy` |
| `Controller\Form\Submit` | `Controller\Request\Submit`, `Model\Service\SubmitQuoteRequest`, validator/resolver |
| `Model\Callforprice` | `Model\QuoteRequest`, resource/collection |
| Handwritten `CallforpriceFactory` | Magento-generated `QuoteRequestFactory` |
| Email logic inside controllers | `Model\Email\AdminNotificationSender`, `CustomerConfirmationSender`, template variable builder |
| Core price preferences | Scoped frontend plugin that preserves the standard renderer |
| Copies of core list/compare/wishlist templates | PDP layout, listing details plugin, reusable modal JavaScript |
| Legacy Install/Upgrade setup | `db_schema.xml`, idempotent data patch |
| `darsh_callprice` | `coresh_quote_request`, explicit CLI migration |
| Product/category `call_for_price` flag | `coresh_quote_request_enabled` |
| Incorrect `customer_id=product_id` assignment | Customer session for new requests; NULL for guests and legacy requests |
| `submitformurl()` | `getSubmitUrl()` |
| `isEnable()` | `isEnabled()` |
| `getAllData()`, `showCallForPriceButton()` | `canRequestQuote()` with AND/OR logic |
| Status/email changes through mass assignment | Dedicated admin status action with an allowlist and ACL |

Framework callbacks and required interfaces retain their prescribed Magento method names. Legacy module names are permitted only in documentation, explicit migration code, and attribution.
