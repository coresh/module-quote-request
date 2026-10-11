# Screenshot features — Coresh_QuoteRequest 1.1.0

Reference: six attached PNG files dated 2026-10-07, with filename timestamps from 19:03:07 to 19:04:44. The supplied 1.0.0 ZIP was reviewed; its SHA-256 is `c19fea30cc8313f07eb89f8219e81a5dc82b954a25ee6a9ee37ee2ca97329a81`. Development targets the previously agreed Magento Open Source 2.4.8-p5 / 2.4.9 and PHP 8.3+.

## Feature mapping

| Screenshot | Feature | Implementation in 1.1.0 |
| --- | --- | --- |
| 19:03:07, 19:03:22, 19:04:27 | Enable | General → Enable Quote Request |
| 19:03:07, 19:03:22, 19:04:27 | Allow Add To Cart | Global display setting; the independent server-side Disable online purchasing control is retained |
| 19:03:07, 19:03:22, 19:04:27 | Disclose Product Price | Inverse of the global Hide eligible product prices setting; prices are visible by default |
| 19:03:07, 19:03:22, 19:04:27 | Call for Price Scope | Global / Product / Category, with store-root and category-ancestor rules |
| 19:03:07, 19:03:22 | Enable Call for Price As | Button; Link retained from 1.0; added Label for static text without a form |
| 19:03:07, 19:03:22 | Button Text | Store-scoped caption and individual product label |
| 19:03:07, 19:03:22 | Enable for Selected Groups | New explicit switch. No permits all groups; Yes permits selected groups, and an empty selection with Yes denies all groups |
| 19:03:07, 19:03:22 | Show Inquiry Form In Product Information Tab | Inline enquiry form in an eligible product's tab; Label mode and an empty custom label do not expose the form |
| 19:03:07 | Admin Email, Email Sender | Configured recipient, store contact fallback, and sender identity; configurable email templates and fields are retained |
| 19:04:07 | Product Enable | Store-scoped `coresh_quote_request_enabled` |
| 19:04:07 | Product Label; blank hides inquiry | Label Mode → Custom with an empty Label hides the enquiry and rejects request creation through a direct POST. Use store text preserves inheritance |
| 19:04:07 | Product Allow Add To Cart | Use store configuration / No / Yes. A product-level Yes does not override the server-side purchasing restriction |
| 19:04:07 | Product Disclose Product Price | Use store configuration / No / Yes for the standard frontend HTML price renderer |
| 19:04:37, 19:04:44 | ID, Name, Phone, Email, SKU, Description, Inquiry Date grid | All fields added or retained; quantity, product ID, store, and handling/email statuses are also available |
| 19:04:37 | Search, Filters, Columns, Pagination | Keyword search over permitted columns, with standard filters, column controls, bookmarks, and pagination |
| 19:04:37, 19:04:44 | Selection checkboxes and Actions | Bulk handling statuses new/processing/closed, with POST FormKey validation and manage ACL; selected rows or all matching rows |
| 19:04:37 | Export | CSV export of the current filtered/selected rows, with spreadsheet formula-injection protection |
| 19:04:27 | Legacy extension detection and Migrate | Detection notice, separate admin preview/import page, store selection, ACL, and expiring approval token for the verified Darsh adapter |

## Exact scope of Sparsh migration

Screenshot 19:04:27 shows **migration from Sparsh Product Inquiry With Hide Price to Sparsh Call for Price**. This is another extension's migration workflow. The supplied archive contains Coresh, and the previously supplied, verified source is Darsh. The attachments do not include the original Sparsh package's SQL schema, EAV/config identifiers, or status values.

The vendor documentation confirms the module names `Sparsh_ProductInquiry` and `Sparsh_ProductInquiryPro`, but does not define their data structures. The package detects these modules and explains why an automatic adapter is unavailable. It **does not guess Sparsh table names or present a Darsh import as a Sparsh migration**. A real adapter requires the source archive or a verified schema for the installed source version. Migration of Sparsh data remains incomplete.

A working admin migration is implemented for the supplied Darsh source: preview counts, explicit original store, confirmation, import without email or deletion, repeatability, and preservation of existing destination data. Behavior and commands are documented in `MIGRATION.md`.

Primary documents: [Sparsh product inquiry installation guide](https://www.sparsh-technologies.com/images/extensions/product-inquiry-with-hide-price/installation-guide-1.0.1.pdf), [Call for Price installation guide](https://www.sparsh-technologies.com/images/extensions/call-for-price/installation-guide.pdf), [Call for Price user guide](https://www.sparsh-technologies.com/images/extensions/call-for-price/user-guide.pdf). These documents were used for feature descriptions; commercial code and branding were not copied.

## Inheritance and upgrades

The new product fields are created by a separate data patch, so they are added to a 1.0.0 installation even if its initial patch has already run. Caption/cart/price settings inherit by default, preserving existing buttons. To intentionally obtain the screenshot's empty-Label behavior, select Custom.

During an upgrade, a previous nonempty list of permitted groups becomes restrict=Yes; an explicit empty value at a child scope continues to permit all groups. An existing new restriction flag is not overwritten. A later legacy import also enables restriction for an imported group list while preserving explicit new configuration.

Store-scoped product overrides apply to a product with Product Enable=Yes after the common group/date/eligibility checks. Other products in Category/Global scope use store configuration. Hide Prices/Allow Add To Cart control presentation; they do not protect private price APIs or third-party checkout endpoints.

## Verification

Implemented code, isolated regressions, and both target XSD sets are checked separately. Full browser/DI/database/API/email runs in an installed Magento environment are not replaced by file-existence checks or mock tests. Actual PASS/FAIL/NOT RUN results are in `TEST_REPORT.md`; the platform matrix is in `COMPATIBILITY_MATRIX.md`.
