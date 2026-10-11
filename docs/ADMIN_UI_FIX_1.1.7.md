# Admin product form and grid isolation correction — 1.1.7

Date: 2026-10-11, UTC. This release includes all 1.1.6 submission corrections. It changes two application XML files and extends the read-only installed-source checker. No server was accessed or modified during preparation.

## Observed errors and confirmed defects

| Supplied evidence | Coresh defect | Correction |
| --- | --- | --- |
| Product edit: required `formElement` for `coresh_quote_request_label_mode` | Four notice-only field overrides were declared directly inside the `quote-request` fieldset. Native Magento product EAV metadata places these fields inside `container_<attribute_code>`. Direct fields are separate orphan controls and do not inherit the native field configuration. | Put each override inside its matching native container, with the correct `select` or `input` form element. Keep native labels, options, defaults, visibility, scope and data sources. |
| `Not registered handle mpcfp_callforprice_requests_collection_listing_data_source`, plus the separately reported rules handle | `etc/adminhtml/di.xml` assigned a Coresh-only array to the shared `CollectionFactory.collections` argument. Loading an area-specific argument replaces an already loaded global array. Existing global collection registrations can therefore disappear. | Create `CoreshQuoteRequestGridCollectionFactory` as a private virtual type and inject it into `CoreshQuoteRequestGridReporting`. Do not configure the shared factory map. |

The attached Mageplaza trace identifies its actual data provider in `app/code/Mageplaza/CallForPrice`. Its source and installed DI files were not supplied. The shared-map defect in Coresh is established and reproduced; the actual Mageplaza registration location and any additional independent errors require server acceptance. Test fixture handles represent third-party registrations, not copied or inferred Mageplaza collection classes.

Adding only `formElement` to the old direct fields would leave duplicate controls without the native EAV option metadata. Adding guessed Mageplaza mappings would mask the configuration conflict. Neither approach is used.

## Runtime changes

- `view/adminhtml/ui_component/product_form.xml`: four notice overrides merge at native EAV container paths; three selects and one input have explicit form elements.
- `etc/adminhtml/di.xml`: a private collection factory supplies the Coresh grid handle; its reporting virtual type uses that factory. The shared `CollectionFactory` registration is untouched.
- `tools/check_installed_release.php`: the two admin fixes are required and their exact hashes are pinned, alongside the previous three submission correction files. Old or mixed XML is rejected even when a reference manifest is recreated.

No database schema, EAV patch, submitted field names, purchasing guard, request token protocol, ACL, CSRF check, email template or production JavaScript changes are introduced.

## Verification

Ordinary regression tests protect native field paths and input types, DI isolation and checker rejection of the exact old XML. Native probes use unmodified upstream Magento source from both 2.4.8-p5 and 2.4.9 to reproduce the prior failures and inspect corrected behavior. Exact counts, source hashes, commands, infrastructure doubles and untested boundaries are recorded in `TEST_REPORT.md` and the corresponding JSON reports.

This is a targeted source/runtime-contract check, not a completed installed Magento product-save or third-party module test. Installed DI generation, browser clicks, the real EAV/database lifecycle, actual Mageplaza grids and SMTP delivery remain deployment acceptance steps.

## Installation in the named Composer package

Prefer the project's normal Composer deployment with the fixed package published to its actual repository/reference. The following backed-up manual replacement is a temporary hotfix for the package named in the trace. Later Composer installs can overwrite it; preserve the same correction in the package source. Do not create a second module copy in `app/code`.

Upload `Coresh_QuoteRequest-1.1.7.zip` outside the web root, to `/home/1624335.cloudwaysapps.com/kutrkznpqw/`. Verify its archive SHA-256 against the value supplied with the download. Then run this Bash subshell; it stops on any failure and leaves maintenance active after a failed deployment. A backup of the canonical installed directory is kept outside the web root.

```bash
(
  set -eu
  quote_private_root='/home/1624335.cloudwaysapps.com/kutrkznpqw'
  quote_project_root="$quote_private_root/public_html"
  quote_archive="$quote_private_root/Coresh_QuoteRequest-1.1.7.zip"
  quote_module_root="$quote_project_root/vendor/coresh/module-quote-request"
  test -f "$quote_archive" || { printf 'Archive not found: %s\n' "$quote_archive"; exit 1; }
  test -f "$quote_module_root/registration.php"
  quote_stage_root="$(mktemp -d "$quote_private_root/quote-request-1.1.7.XXXXXX")"
  unzip -q "$quote_archive" -d "$quote_stage_root"
  quote_release_root="$quote_stage_root/Coresh_QuoteRequest"
  test -f "$quote_release_root/Observer/EnforcePurchasingPolicy.php"
  (cd "$quote_release_root"; sha256sum -c SHA256SUMS >/dev/null)
  cp -a "$(realpath "$quote_module_root")" "$quote_stage_root/installed-backup"
  printf 'Backup: %s\n' "$quote_stage_root/installed-backup"
  cd "$quote_project_root"
  php bin/magento maintenance:enable
  rsync -a "$quote_release_root/" "$quote_module_root/"
  composer dump-autoload --optimize
  php bin/magento setup:upgrade
  php bin/magento setup:di:compile
  php bin/magento cache:clean config layout block_html full_page translate
  php "$quote_release_root/tools/check_installed_release.php" --magento-root="$quote_project_root"
)
```

The checker must return `FILES_MATCH` with exit code 0. It verifies disk sources and CLI registration/autoload paths, not loaded web opcodes or successful application requests. It does not remove unknown files or intentional customizations. Resolve any mismatch before restoring traffic.

After successful compilation and source verification, refresh the relevant PHP-FPM service through the hosting panel. Cloudways documents Servers → selected server → Server Management → Manage Services → PHP-FPM → Restart. Then disable maintenance from the same project root:

```bash
cd /home/1624335.cloudwaysapps.com/kutrkznpqw/public_html || exit 1
php bin/magento maintenance:disable
```

## Required live acceptance

1. Open the previously failing product edit page. The Quote Request fields must render once, with populated select options and Store View inheritance controls.
2. Save a test product with each label mode and product display override. Reload the same Store View and confirm values/default inheritance. Preserve existing production values while testing.
3. Open the Coresh requests grid and verify loading, filters, search, view action, export and permitted mass actions with the appropriate admin role.
4. Open the Mageplaza rules and request grids. Verify their own data source AJAX responses; the shared handle error must no longer be introduced by Coresh.
5. Submit one intentional storefront test enquiry. Require HTTP 200 accepted JSON and one matching admin record; an identical retry with the original token/session must return the same ID without a second record or notification. Inspect notification status/mailbox separately.
6. Check fresh logs around the test time for new exceptions. Earlier saved reports are historical evidence.

If a Mageplaza handle is still absent after installing verified sources and refreshing the runtime, inspect its actual registration without modifying data:

```bash
cd /home/1624335.cloudwaysapps.com/kutrkznpqw/public_html || exit 1
rg -n -C 5 \
  'mpcfp_callforprice_(rules|requests_collection)_listing_data_source|DataProvider\\CollectionFactory' \
  app/code/Mageplaza/CallForPrice/etc \
  vendor/coresh/module-quote-request/etc \
  --glob '*.xml'
```

The file check plus fresh HTTP/admin results are required to confirm the live repair. No live success is claimed by this release.

Primary references: [Adobe DI configuration and stage merging](https://developer.adobe.com/commerce/php/development/build/dependency-injection-file), tagged Magento `Catalog/Ui/DataProvider/Product/Form/Modifier/Eav.php`, `Ui/Component/Form/Field.php`, `Framework/View/Element/UiComponentFactory.php`, ObjectManager configuration and `CollectionFactory.php`; source URLs/hashes are recorded in native reports. [Cloudways service controls](https://support.cloudways.com/en/articles/5120718-what-can-i-do-in-the-manage-services-section-on-cloudways).
