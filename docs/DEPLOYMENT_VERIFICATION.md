# Installed source and web execution verification — 1.1.6

Date: 2026-10-11, UTC. The uploaded 1.1.5 archive is the already corrected delivered package, but the fresh web trace still executes the old controller getter. Version 1.1.6 explicitly disables that global observer registration and moves the corrected purchasing policy to a new class on the three purchasing routes. No live server was accessed or modified.

## What the current evidence establishes

| Evidence | Observed result |
| --- | --- |
| Uploaded `Coresh_QuoteRequest-1.1.5(1).zip` | SHA-256 `aaddfc3881732d307dd895d002677d288df5c618597c71f87f70ffecf350d86f`; byte-identical to delivered 1.1.5, with 237 files. |
| Observer in that uploaded package | Its fixed body has no controller getter calls; line 34 is blank. |
| Fresh trace at `2026-10-11T03:59:06.096864+00:00` | Modern Submit Interceptor's nonexistent `getRequest()` is called from old `BlockPurchasingRoutes.php:34`, before Submit executes. |
| Separate customer messages response | Valid section JSON containing an empty messages list; not a quote acceptance response. |
| 1.1.6 change | New EnforcePurchasingPolicy class; old global observer disabled; checkout/multishipping/paypal route observers activated; compatibility subclass retained. |

The logged executable instructions differ from the attached source. This does not establish the present installed disk file, active document root/node, deployment timing or OPcache as the specific cause. A changed archive filename or Composer requirement does not update a locked repository reference or an old active release. Inspect the registered and Composer-resolved paths, install the actual files there and refresh the web runtime.

The new event scope preserves the same purchasing route allowlist and policy behavior while eliminating invocation by quote, Reports and customer section routes. It requires freshly loaded event configuration; class renaming alone cannot bypass stale configuration. Details and test boundaries are in `SUBMISSION_FIX_1.1.6.md` and `TEST_REPORT.md`.

## Read-only installed-source checker

Extract this archive to a private directory outside the Magento web document root and keep that extracted reference separate from the installed module. Run its checker through SSH using the intended PHP binary. The reference hashes of EnforcePurchasingPolicy, the legacy compatibility class and frontend event configuration are pinned to the 1.1.6 correction; the reference `SHA256SUMS` must cover all runtime files with valid safe paths/hashes.

For a project with its Composer dependencies installed:

```sh
quote_release_root='/absolute/private/path/Coresh_QuoteRequest'
magento_project_root='/absolute/path/to/magento'
php "$quote_release_root/tools/check_installed_release.php" \
  --magento-root="$magento_project_root"
```

Replace both example paths with real paths. `--magento-root` loads that project's Composer autoloader and registration files to inspect the registered `Coresh_QuoteRequest` path and the file resolved for the exact observer class. It does not create Magento's ObjectManager, connect to the database, read `env.php`, submit a form, send email, recompile DI or flush caches. Composer registration files are executable PHP from the selected project; run this mode only against the intended trusted deployment. The checker itself performs no writes or network operations.

For an explicit installed module directory without Composer/registry resolution:

```sh
php "$quote_release_root/tools/check_installed_release.php" \
  --module-root="$magento_project_root/vendor/coresh/module-quote-request"
```

Use `app/code/Coresh/QuoteRequest` instead if that is the actual installation. Source-only mode cannot verify whether Magento autoloads that directory. The full mode also checks the known `app/code` and standard Composer package locations for distinct copies. A symlink path repository resolving to the same canonical directory is not counted as a second installation. Custom locations are checked through the active registry/autoload result; this is not an exhaustive filesystem search for every unused copy.

The tool checks runtime PHP/PHTML, XML, JavaScript, styles, email HTML templates, translation CSV and package metadata against the extracted reference. Missing docs/tests do not invalidate a runtime source match. Different, missing, extra or unlisted runtime files require review; the checker never deletes customizations. It rejects unsafe manifest paths, uncovered runtime files and files escaping their canonical module root. Known documentation, including `i18n/README.md`, is excluded; translation CSV remains checked. Other unexpected package files also require review. It emits JSON with resolved paths, findings and `not_verified` boundaries; exit 0 with `FILES_MATCH` means the inspected disk files match the trusted reference in that mode. It does **not** mean the web server is fixed.

The tool rejects using the target installation as its own trusted reference, including a symlink alias. A source-only result does not certify Composer/registration selection, and even a full CLI result cannot prove the web worker's active release/node, loaded opcodes, generated DI, database acceptance or email delivery. Keep the report private because it contains local filesystem paths.

## Applying the 1.1.6 correction to the active deployment

Use the project's existing deployment process and one installation method. For a Composer-managed module, update its actual repository/reference to contain the fixed files; ensure the root requirement permits the intended release and perform the normal locked-package update. A changed local archive filename does not update a locked remote Git commit. The README path-repository example applies only when that repository is actually configured. For an `app/code` installation, replace the registered source there. Retain backups and intended local customizations through the project's normal procedure.

### Controlled manual vendor hotfix when the repository update has not arrived

The trace names `/home/1624335.cloudwaysapps.com/kutrkznpqw/public_html/vendor/coresh/module-quote-request`. Verify that it is the active registered package before using it. If the intended Composer repository cannot yet deliver this release, a backed-up manual replacement of that package can provide a temporary hotfix; a later Composer install/update can overwrite it, so commit/publish the same correction to the actual package repository afterward.

Use the project's controlled maintenance/deployment window. Set the private extraction and backup paths below; the reference must be outside the web root, distinct from the target and writable/readable by the deployment account. Run the commands in a Bash subshell. They fail before copying if the paths are unset/missing, and stop on any failure. Leave maintenance active on a failed deployment until corrected; disable it only after successful compilation, source verification and the PHP-FPM refresh below. Preserve any intentional customizations in the backup and review mismatches rather than deleting extra files automatically.

```bash
(
  set -eu
  magento_project_root='/home/1624335.cloudwaysapps.com/kutrkznpqw/public_html'
  quote_release_root='/absolute/private/path/Coresh_QuoteRequest'
  quote_backup_root='/absolute/private/path/quote-request-backups'
  quote_module_root="$magento_project_root/vendor/coresh/module-quote-request"
  test -f "$quote_release_root/Observer/EnforcePurchasingPolicy.php"
  test -f "$quote_release_root/SHA256SUMS"
  test -f "$quote_module_root/registration.php"
  test -d "$quote_backup_root"
  quote_module_canonical="$(realpath "$quote_module_root")"
  test "$(realpath "$quote_release_root")" != "$quote_module_canonical"
  quote_backup_path="$quote_backup_root/module-quote-request-$(date -u +%Y%m%dT%H%M%SZ)"
  test ! -e "$quote_backup_path"
  cp -a "$quote_module_canonical" "$quote_backup_path"
  cd "$magento_project_root"
  php bin/magento maintenance:enable
  rsync -a "$quote_release_root/" "$quote_module_root/"
  composer dump-autoload --optimize
  php bin/magento setup:di:compile
  php bin/magento cache:clean config block_html full_page
  php "$quote_release_root/tools/check_installed_release.php" --magento-root="$magento_project_root"
  # Keep maintenance active until the PHP-FPM refresh below succeeds.
)
```

The subshell's source checker uses a separate trusted reference. It must return exit 0 and `FILES_MATCH`; an unresolved old/new class or mixed runtime files stops the sequence. `rsync` deliberately performs no deletion. These instructions are provided for the named package and have not been executed on the user's server. After this sequence, refresh PHP-FPM as described below. Then run `php bin/magento maintenance:disable` from the same Magento root and perform fresh web acceptance. A symlinked Composer package is backed up by its canonical directory, not merely by copying the symlink. Use the normal deployment pipeline instead where available; the manual hotfix is not a substitute for correcting the package repository.

Verify runtime source hashes and path resolution after installation. Fix mismatches before treating the file step as complete. Version 1.1.6 adds a new observer class and changes event registration. Refresh the actual Composer autoloader and rebuild generated DI on the selected project rather than editing generated interceptors. The compatibility class retains the eight-argument corrected constructor. Run the normal Magento deployment commands from that project root:

```sh
cd "$magento_project_root"
composer dump-autoload --optimize
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean config block_html full_page
```

No schema, production JavaScript or email template changes are introduced by 1.1.6. Fresh configuration is required to disable the old global observer and activate the three route-specific observers; a stale configuration cache can continue to invoke the old class even when the new class file exists. Use the project's normal static deployment procedure when also installing earlier changes. The checker does not execute these commands, and installed Magento compilation was NOT RUN during preparation.

After the fixed files and generated dependencies are deployed, refresh the relevant PHP-FPM service through the hosting provider's supported control. For Cloudways, the documented navigation is **Servers → selected server → Server Management → Manage Services → PHP-FPM → Restart**. Use the service for the actual application and verify each serving node if there are several. Coordinate the operation with the project's deployment window because active web requests may be interrupted. Cloudways supplies this control without SSH root access.

The PHP manual documents that disabling `opcache.validate_timestamps` requires explicit invalidation/reset or a web-server restart before file changes take effect. Magento data-cache cleaning and CLI source inspection do not establish that web PHP has refreshed its loaded instructions. The supplied trace does not reveal the actual OPcache settings. No public diagnostic/reset endpoint is added by this release.

Official references, checked 2026-10-11:

- [PHP OPcache configuration](https://www.php.net/manual/en/opcache.configuration.php).
- [Cloudways Manage Services](https://support.cloudways.com/en/articles/5120718-what-can-i-do-in-the-manage-services-section-on-cloudways).

## Required web acceptance and confirmation

Record the test time after deployment/runtime refresh. Use a designated test recipient and one intentional new enquiry so the resulting record/email can be identified. Inspect the browser Network response and Magento logs around that new time, rather than relying on earlier failures retained in the log.

| Test | Required result |
| --- | --- |
| Product page / modern Reports action | Loads without the observer controller-getter fatal. |
| Valid popup Send Quote Request | HTTP 200 JSON with `success: true`, `status: accepted` and a positive request ID; safe success output and unlocked submit; one matching admin row with trusted product name/ID and exact quantity. |
| Identical replay with the original token and session | HTTP 200 `duplicate`, same request ID and no second row/notification. A newly opened form after success has a new token and is an intentional new enquiry. |
| Invalid body form key or malformed input | Safe HTTP 400; no accepted ID, new row or notification; form remains usable. |
| Current module rate limit | Safe HTTP 400 from the localized rate exception; no new row. Independent HTTP 429 client tests cover handling of external/proxy responses. |
| Purchase restrictions enabled | Quote submission remains available; prohibited checkout/multishipping/PayPal actions remain denied. |
| Notification failure | Accepted request remains saved; admin email status supports inspection/resend. Successful transport does not prove inbox delivery; inspect the test mailbox separately. |
| Fresh logs after tests | No new old-observer `getRequest()` fatal. Any new exception is assessed by its own trace. |

The application correction is reproduced and verified in controlled PHP/AMD/native-source execution. This release's checker is tested against explicit temporary filesystem/autoload/registration fixtures. The live server, actual PHP-FPM/OPcache state, HTTP/browser, real database, SMTP and installed DI remain **NOT RUN**. The supplied files alone cannot confirm successful deployment or a live request; completion on the user's server requires the file, runtime-refresh and web-acceptance steps above.

## Current release: 1.1.7

The 1.1.7 package includes the preceding submission correction and additionally fixes native product form paths/input types and isolates the admin grid collection factory. Its checker pins these two corrected admin configuration files as well as the three verified submission files. Use the separately extracted 1.1.7 package as the trusted reference; the older 1.1.6 package cannot certify this admin correction. Installation and admin acceptance instructions are in `ADMIN_UI_FIX_1.1.7.md`. Historical source comparisons earlier in this document describe the evidence available when 1.1.6 was prepared.
