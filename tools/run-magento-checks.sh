#!/usr/bin/env bash
# Run only against a separate disposable Magento test deployment; never production.
set -euo pipefail
if [[ ${MAGENTO_TEST_ENVIRONMENT:-} != 1 ]]; then
    printf '%s\n' 'NOT RUN: set MAGENTO_TEST_ENVIRONMENT=1 for a disposable test deployment.' >&2
    exit 2
fi
: "${MAGENTO_TEST_ROOT:?Set the disposable Magento installation root.}"
module_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
test_root=$(cd -- "$MAGENTO_TEST_ROOT" && pwd)
[[ -f "$test_root/bin/magento" && -f "$test_root/vendor/autoload.php" ]] || { printf '%s\n' 'Missing Magento installation.' >&2; exit 2; }
cd -- "$test_root"
php bin/magento --version
php --version
composer --version
php bin/magento module:status Coresh_QuoteRequest
php bin/magento setup:upgrade
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US
# The Magento integration configuration must point to its own disposable integration DB.
vendor/bin/phpunit -c dev/tests/integration/phpunit.xml "$module_dir/tests/Integration"
