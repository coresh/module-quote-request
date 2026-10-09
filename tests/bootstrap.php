<?php
/** Isolated tests are not evidence of a Magento installation or DI compilation. */
declare(strict_types=1);

$magentoAutoload = getenv('MAGENTO_UNIT_AUTOLOAD');
if ($magentoAutoload !== false && $magentoAutoload !== '') {
    if (!is_file($magentoAutoload)) {
        throw new RuntimeException('MAGENTO_UNIT_AUTOLOAD must identify the target Magento vendor/autoload.php.');
    }
    require_once $magentoAutoload;
} else {
    require_once __DIR__ . '/Fixtures/isolated_framework.php';
    fwrite(STDERR, "ISOLATED UNIT MODE: minimal framework test doubles; Magento integration is NOT RUN.\n");
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Coresh\\QuoteRequest\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
