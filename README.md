# Coresh Quote Request 1.1.7

A Magento Open Source extension for product enquiries: **Contact Us for a Quote** → form → saved request → notification email to the store. It provides store-view configuration, an admin grid, request details and email resend, quantity and product ID fields, Cart Hidden, and a separate server-side purchasing restriction.

Target versions: Magento 2.4.8-p5 and 2.4.9. The code is written for PHP 8.3+. Checks actually performed and their limitations are documented in `docs/TEST_REPORT.md` and `docs/COMPATIBILITY_MATRIX.md`. Composer constraints do not demonstrate successful installation or DI compilation on these versions.

The settings cover the features shown in the supplied screenshots: Enable, Allow Add To Cart, Disclose Product Price (the inverse of Hide Prices), Global/Product/Category scope, selected categories, Button/Link, Button Text, customer groups, and Show Inquiry Form In Product Information Tab. The YouTube video could not be accessed; no claim of frame-by-frame equivalence is made. The extension uses its own Luma/Blank-compatible markup without copying a third-party commercial extension.

Version 1.1.0 added per-product settings, a static Label display mode, explicit customer-group restrictions, grid search/export/bulk status changes, and an admin preview/import page. A detailed mapping of the six additional screenshots is provided in `docs/SCREENSHOT_FEATURES.md`. Version 1.1.1 provides English documentation and text, with the review results recorded in `docs/TEST_REPORT.md` and `docs/SECURITY_REVIEW.md`.

Version 1.1.2 fixes the admin request-details block inheritance error reported during `setup:di:compile`. It preserves the constructor argument `formKey` while using a distinct child property, validates administrative request IDs, and skips malformed legacy dates safely. All production class declarations were loaded against actual parent/interface sources for both target tags; installed Magento DI compilation still requires the target deployment. See `docs/COMPILATION_FIX.md`.

Version 1.1.3 fixes the empty configuration form caused by missing path-based default email template IDs. The original template IDs remain usable aliases. It also corrects malformed quote-button context and admin submits cancelled by validation, and tightens configuration date/limit validation. See `docs/CONFIGURATION_FIX.md` and the current test report.

Version 1.1.4 fixes the predispatch observer that caused HTTP 500 before modern quote/report controllers could execute. It uses the event/current HTTP request and shared response, preserves purchase denial, and adds frontend-payload-to-backend-response regressions. Invalid reused-token guidance now requests a page refresh. See `docs/SUBMISSION_FIX.md` and the current test report.

Version 1.1.5 adds read-only installed-source verification and an explicit deployment recovery procedure. The supplied 1.1.4 archive already contains the corrected observer; the new server trace executes incompatible older instructions at line 34. Application code remains identical to that supplied fixed source. Use `tools/check_installed_release.php` from a separately extracted trusted package to compare runtime hashes and the actual Composer/registered module paths. A file match does not verify PHP-FPM bytecode or a successful live request. See `docs/DEPLOYMENT_VERIFICATION.md` before another deployment.

Version 1.1.6 explicitly disables the old global purchase observer and registers `EnforcePurchasingPolicy` only on checkout, multishipping and PayPal route predispatch events. Quote submission, Reports and customer section actions therefore do not invoke the purchase guard. The old observer class remains a compatibility subclass. Install the actual package files, rebuild Composer autoload/DI and refresh configuration/web PHP before live acceptance. See `docs/SUBMISSION_FIX_1.1.6.md` and `docs/DEPLOYMENT_VERIFICATION.md`.

Version 1.1.7 fixes product-edit fields missing `formElement` by merging their notices into the native EAV containers with explicit input types. It also isolates the Coresh grid collection factory so its admin DI configuration cannot replace the shared collection registrations used by Mageplaza and other extensions. The 1.1.6 submission correction is included. See `docs/ADMIN_UI_FIX_1.1.7.md` for root causes, verification boundaries and installation steps.

## Installation

Validate installation on a Magento staging environment first. No production site or production data was modified when preparing this package.

### Manual installation

The archive contains one directory, `Coresh_QuoteRequest`. Place its contents in `app/code/Coresh/QuoteRequest`, with `registration.php` directly inside that directory.

After copying the files, run the following from the Magento root:

```bash
# Disable the previous extension to avoid duplicate forms and conflicting price renderers.
php bin/magento module:disable Darsh_Callforprice
php bin/magento module:enable Coresh_QuoteRequest
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US
php bin/magento cache:clean config block_html full_page translate
```

Skip the first command if the previous module is not installed. The examples deploy en_US. Add other configured store locales when required. The new module is disabled in its configuration by default; enable it under Stores → Configuration → Coresh → Quote Request.

### Composer

`composer.json` is located at the extension root. For a local installation, use a Composer path repository pointing to the extracted package root outside `app/code`, or your own Composer/artifact repository. Example for an existing directory at `packages/coresh/module-quote-request`:

```bash
composer config repositories.coresh-quote-request '{"type":"path","url":"packages/coresh/module-quote-request","options":{"versions":{"coresh/module-quote-request":"1.1.7"}}}'
composer require coresh/module-quote-request:1.1.7
php bin/magento module:enable Coresh_QuoteRequest
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US
php bin/magento cache:clean config block_html full_page translate
```

Do not install separate copies through Composer and `app/code` at the same time. Development dependencies are required only for development; testing instructions are in `tests/README.md`.

## Quick configuration

1. Enable General → Enable. Select Scope → Global to apply the rules to the entire catalogue.
2. Set Button Text, for example `Contact Us for a Quote`.
3. Set Admin Email → Recipient. If empty, the contact email configured for the current store view is used.
4. Enable product name, quantity, and product ID in notification emails. All three fields are enabled by default.
5. For a catalogue without online purchasing, enable **Cart Hidden** and **Disable Purchasing**. Leave **Hide Prices = No** if prices should remain visible.
6. Verify an actual email in a test mailbox. Emails use the installed Magento transport; SMTP configuration, DNS, and final delivery are outside the extension.

Requests are available in a dedicated Coresh admin menu. ACL grants separate permissions to view requests, change their status, and send notification emails.

All settings, defaults, and product-type behavior are described in `docs/CONFIGURATION.md`. Security controls and their limits are documented in `docs/SECURITY_REVIEW.md`.

## Upgrade and rollback

Before replacing the version, back up the files and database using your project's normal procedure. Version 1.1.1 removes the bundled Russian translation dictionary so the extension uses its English source messages in every locale unless another translation package overrides them. When upgrading by copying files over an existing installation, remove the obsolete `i18n/ru_RU.csv` from this module's directory; a file overlay does not delete it. Clean the translation cache and regenerate static content for all configured store locales. See `i18n/README.md`. Update through the same installation method, then run `setup:upgrade`, compilation, and cache cleaning on staging. Do not manually remove schema or EAV data.

To temporarily restore normal storefront behavior, disable General → Enable. To disable the extension entirely:

```bash
php bin/magento module:disable Coresh_QuoteRequest
php bin/magento cache:clean config block_html full_page translate
```

Disabling the extension preserves requests. Disabling its catalogue purchasing guard restores normal purchasing. Tables, attributes, and legacy data are not removed automatically. Migration from the previous Darsh module is documented in `docs/MIGRATION.md`.

## Scope and limitations

This extension handles email enquiries; it does not implement a full B2B RFQ or negotiable-quote workflow. Bundle/grouped products can be submitted as a general parent-product enquiry with no selected configuration. Price hiding applies to the storefront and does not guarantee that prices are absent from REST/GraphQL responses. Payment credentials are not changed, and admin processing of existing orders remains available.

License and provenance: `LICENSE` and `NOTICE.md`. Full compatibility with the target Magento versions requires the checks listed in the compatibility matrix; checks not performed are explicitly marked.
