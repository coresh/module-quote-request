# Admin block compilation fix — 1.1.2

The supplied 1.1.1 archive fails when PHP loads `RequestDetails` with the native
Magento Backend Template parent chain:

```text
Cannot redeclare non-readonly property Magento\Backend\Block\Template::$formKey
as readonly Coresh\QuoteRequest\Block\Adminhtml\RequestDetails::$formKey
```

The parent's protected `formKey` property is mutable. The child originally
declared a promoted private readonly property with the same name. This is a
class declaration error; cache cleaning cannot correct it.

The child now uses `requestFormKey` and leaves the inherited property intact.
The constructor still accepts `FormKey $formKey` under the same argument name,
and calls the parent constructor with its original arguments. The current
server token and independent administrative permissions are covered by
regression tests.

The unmodified attachment reproduces the exact fatal error with exit 255 using
actual parent source from both Magento 2.4.8-p5 and 2.4.9. After the correction,
all 74 production declarations load successfully for each target. The reports
include upstream source URLs and SHA-256 hashes.

A separate check executes the official FileClassScanner, DirectoryScanner and
PhpScanner for both tags. The native compiler excludes `Test` and `tests`; the
classless CLI declaration checker has no class or generated entity and is not
executed by those scanners. See `native-compiler-file-scope.json`.

These checks exercise native PHP inheritance. They do not establish successful
installed Magento DI compilation, object construction, interceptor generation,
or runtime behavior. Previous syntax and isolated tests omitted the native
parent property and therefore missed the original conflict.

After updating the package through the project's existing installation method,
run the deployment checks on staging from the Magento root:

```sh
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean config block_html full_page
```

Check the request-details page with view-only, status-management and email-send
permissions. Confirm that authorized POST forms use the current form key and
that stored customer text is escaped. Full integration and deployment checks
remain NOT RUN in the preparation environment; see `TEST_REPORT.md`.

The release also rejects invalid request identifiers before resource loading
and skips malformed legacy date rows without aborting migration. These changes
are documented in the changelog and security review.
