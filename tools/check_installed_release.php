<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

/**
 * Read-only source verification. Run this file from the trusted release archive.
 *
 * --module-root compares source files only. --magento-root additionally requires
 * the installation's vendor/autoload.php: Composer registration files execute.
 * Magento's application bootstrap, ObjectManager and configuration are not used.
 * This CLI process cannot establish what a web worker or its OPcache has loaded.
 *
 * ComponentRegistrar's instance getPath() contract was checked against:
 * https://github.com/magento/magento2/blob/2.4.8-p5/lib/internal/Magento/Framework/Component/ComponentRegistrar.php
 * https://github.com/magento/magento2/blob/2.4.9/lib/internal/Magento/Framework/Component/ComponentRegistrar.php
 */

function coreshQuoteReleaseIsWithin(string $path, string $root): bool
{
    return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
}

function coreshQuoteReleaseIsRuntime(string $path): bool
{
    $first = explode('/', $path, 2)[0];
    if (in_array($first, ['tests', 'tools', 'docs', '.git', '.idea', '.vscode'], true)) {
        return false;
    }
    return !in_array($path, [
        'SHA256SUMS', 'README.md', 'CHANGELOG.md', 'LICENSE', 'LICENSE.txt',
        'NOTICE', 'NOTICE.md', 'NOTICE.txt', 'phpunit.xml', 'phpunit.xml.dist',
        'i18n/README.md',
    ], true);
}

/** @return array{files: array<string, string>, unsafe: list<string>} */
function coreshQuoteReleaseInventory(string $root): array
{
    $files = [];
    $unsafe = [];
    $walk = function (string $directory, string $prefix, array $ancestors) use (&$walk, &$files, &$unsafe, $root): void {
        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException('inventory_failed');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $relative = $prefix . $entry;
            if (!coreshQuoteReleaseIsRuntime($relative)) {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $canonical = realpath($path);
            if ($canonical === false || !coreshQuoteReleaseIsWithin($canonical, $root)) {
                $unsafe[] = $relative;
                continue;
            }
            if (is_dir($canonical)) {
                if (in_array($canonical, $ancestors, true)) {
                    $unsafe[] = $relative;
                    continue;
                }
                $walk($canonical, $relative . '/', [...$ancestors, $canonical]);
            } elseif (is_file($canonical)) {
                $files[$relative] = $canonical;
            } else {
                $unsafe[] = $relative;
            }
        }
    };
    $walk($root, '', [$root]);
    ksort($files);
    sort($unsafe);
    return ['files' => $files, 'unsafe' => $unsafe];
}

/** @return array<string, string> */
function coreshQuoteReleaseManifest(string $root): array
{
    $manifestPath = realpath($root . '/SHA256SUMS');
    if ($manifestPath === false || !coreshQuoteReleaseIsWithin($manifestPath, $root) || !is_file($manifestPath)) {
        throw new RuntimeException('reference_manifest_missing_or_unsafe');
    }
    $text = file_get_contents($manifestPath);
    if ($text === false) {
        throw new RuntimeException('reference_manifest_unreadable');
    }
    $manifest = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        if ($line === '') {
            continue;
        }
        if (!preg_match('/\A([a-f0-9]{64})  ([A-Za-z0-9_.\/-]+)\z/D', $line, $match)) {
            throw new RuntimeException('reference_manifest_invalid');
        }
        $path = $match[2];
        $parts = explode('/', $path);
        if (str_starts_with($path, '/') || in_array('', $parts, true)
            || in_array('.', $parts, true) || in_array('..', $parts, true)
            || $path === 'SHA256SUMS' || isset($manifest[$path])) {
            throw new RuntimeException('reference_manifest_invalid');
        }
        $canonical = realpath($root . '/' . $path);
        if ($canonical !== false && !coreshQuoteReleaseIsWithin($canonical, $root)) {
            throw new RuntimeException('reference_manifest_invalid');
        }
        $manifest[$path] = $match[1];
    }
    return $manifest;
}

/** @return array<string, mixed> */
function coreshQuoteReleaseInitialReport(): array
{
    return [
        'schema' => 1,
        'status' => 'CHECK_FAILED',
        'scope' => 'CLI source files and optional installed Composer/ComponentRegistrar resolution',
        'mode' => null,
        'paths' => [
            'expected_root' => null, 'module_root' => null, 'registered_module_root' => null,
            'autoload_observer_file' => null, 'known_installed_copies' => [],
        ],
        'composer_registration_files_executed' => false,
        'checked_runtime_files' => 0,
        'errors' => [],
        'not_verified' => [
            'web_document_root_and_virtual_host', 'PHP_FPM_loaded_classes_and_OPcache',
            'generated_DI_and_interceptors', 'Magento_bootstrap_and_configuration',
            'database_persistence', 'SMTP_delivery', 'real_browser_HTTP_submission',
        ],
        'notice' => 'FILES_MATCH means source files match the trusted release. It does not confirm that the web server is fixed.',
    ];
}

/** @return array{int, array<string, mixed>} */
function coreshQuoteReleaseCheck(array $arguments): array
{
    $report = coreshQuoteReleaseInitialReport();
    $options = [];
    foreach ($arguments as $argument) {
        if (!is_string($argument) || !preg_match('/\A--(module-root|magento-root|expected-root)=(.+)\z/D', $argument, $match)
            || isset($options[$match[1]])) {
            $report['errors'][] = ['code' => 'invalid_arguments'];
            return [2, $report];
        }
        $options[$match[1]] = $match[2];
    }
    if (isset($options['module-root']) === isset($options['magento-root'])) {
        $report['errors'][] = ['code' => 'exactly_one_target_root_required'];
        return [2, $report];
    }
    $report['mode'] = isset($options['module-root']) ? 'module_source_only' : 'magento_autoload_resolution';
    $expectedRoot = realpath($options['expected-root'] ?? dirname(__DIR__));
    if ($expectedRoot === false || !is_dir($expectedRoot)) {
        $report['errors'][] = ['code' => 'reference_root_missing'];
        return [2, $report];
    }
    $report['paths']['expected_root'] = $expectedRoot;
    try {
        $manifest = coreshQuoteReleaseManifest($expectedRoot);
        $reference = coreshQuoteReleaseInventory($expectedRoot);
        $runtimeManifest = array_filter($manifest, 'coreshQuoteReleaseIsRuntime', ARRAY_FILTER_USE_KEY);
        $required = [
            'Observer/EnforcePurchasingPolicy.php', 'Observer/BlockPurchasingRoutes.php', 'Controller/Request/Submit.php',
            'Model/Service/SubmitQuoteRequest.php', 'etc/events.xml', 'registration.php', 'composer.json',
            'etc/frontend/events.xml',
        ];
        foreach ($required as $file) {
            if (!isset($runtimeManifest[$file])) {
                $report['errors'][] = ['code' => 'reference_manifest_missing_required_file', 'file' => $file];
            }
        }
        // Pin the executable observer, its compatibility shim and the event wiring.
        // A manifest rebuilt around an older release must not become a trusted fix.
        $verifiedSource = [
            'Observer/EnforcePurchasingPolicy.php' => ['5a0feacbc173df0c53dd9d0bcfd897e76167d8676c9f9f24c4a442c5ec3562b8', 'reference_observer_not_verified_fix'],
            'Observer/BlockPurchasingRoutes.php' => ['abba995ebfda20466d790e0d97004d2222fb4af44a4fc6b173e248017096febb', 'reference_legacy_observer_not_verified_shim'],
            'etc/frontend/events.xml' => ['37a79a4359b37cb45eee828f8f2ee8be268af39974000e9c0cba0e1127bf1d00', 'reference_route_observer_registration_not_verified_fix'],
        ];
        foreach ($verifiedSource as $file => [$hash, $code]) {
            if (($runtimeManifest[$file] ?? null) !== $hash) {
                $report['errors'][] = ['code' => $code, 'file' => $file];
            }
        }
        foreach ($reference['unsafe'] as $file) {
            $report['errors'][] = ['code' => 'reference_unsafe_file', 'file' => $file];
        }
        foreach ($reference['files'] as $file => $canonical) {
            if (!isset($runtimeManifest[$file])) {
                $report['errors'][] = ['code' => 'reference_unlisted_runtime_file', 'file' => $file];
            }
        }
        foreach ($runtimeManifest as $file => $hash) {
            if (!isset($reference['files'][$file]) || hash_file('sha256', $reference['files'][$file]) !== $hash) {
                $report['errors'][] = ['code' => 'reference_file_missing_or_changed', 'file' => $file];
            }
        }
    } catch (Throwable $error) {
        $code = $error instanceof RuntimeException ? $error->getMessage() : 'reference_read_failed';
        $report['errors'][] = ['code' => $code];
    }
    if ($report['errors'] !== []) {
        return [2, $report];
    }
    $targetRoot = null;
    if (isset($options['module-root'])) {
        $targetRoot = realpath($options['module-root']);
    } else {
        $magentoRoot = realpath($options['magento-root']);
        if ($magentoRoot === false || !is_dir($magentoRoot)) {
            $report['errors'][] = ['code' => 'magento_root_missing'];
            return [2, $report];
        }
        $copies = [];
        foreach (['app/code/Coresh/QuoteRequest', 'vendor/coresh/module-quote-request'] as $knownPath) {
            if (file_exists($magentoRoot . '/' . $knownPath) || is_link($magentoRoot . '/' . $knownPath)) {
                $canonical = realpath($magentoRoot . '/' . $knownPath);
                $report['paths']['known_installed_copies'][$knownPath] = $canonical === false ? null : $canonical;
                if ($canonical === false || !is_dir($canonical)) {
                    $report['errors'][] = ['code' => 'known_module_copy_unreadable', 'file' => $knownPath];
                } else {
                    $copies[] = $canonical;
                }
            }
        }
        if (count(array_unique($copies)) > 1) {
            $report['errors'][] = ['code' => 'duplicate_installed_module_copies'];
        }
        if ($report['errors'] !== []) {
            return [1, $report];
        }
        $autoload = $magentoRoot . '/vendor/autoload.php';
        if (!is_file($autoload) || !is_readable($autoload)) {
            $report['errors'][] = ['code' => 'composer_autoload_missing'];
            return [2, $report];
        }
        $level = ob_get_level();
        ob_start();
        try {
            $report['composer_registration_files_executed'] = true;
            $loader = require $autoload;
            if (!$loader instanceof \Composer\Autoload\ClassLoader) {
                $report['errors'][] = ['code' => 'composer_loader_unavailable'];
            } elseif (!class_exists(\Magento\Framework\Component\ComponentRegistrar::class)) {
                $report['errors'][] = ['code' => 'component_registrar_unavailable'];
            } else {
                $registrar = new \Magento\Framework\Component\ComponentRegistrar();
                $registered = $registrar->getPath(\Magento\Framework\Component\ComponentRegistrar::MODULE, 'Coresh_QuoteRequest');
                $targetRoot = is_string($registered) ? realpath($registered) : false;
                $observer = $loader->findFile('Coresh\\QuoteRequest\\Observer\\EnforcePurchasingPolicy');
                $observerFile = is_string($observer) ? realpath($observer) : false;
                $report['paths']['registered_module_root'] = $targetRoot === false ? null : $targetRoot;
                $report['paths']['autoload_observer_file'] = $observerFile === false ? null : $observerFile;
                if ($targetRoot === false || !is_dir($targetRoot)) {
                    $report['errors'][] = ['code' => 'registered_module_missing'];
                } elseif ($observerFile === false || $observerFile !== realpath($targetRoot . '/Observer/EnforcePurchasingPolicy.php')) {
                    $report['errors'][] = ['code' => 'registered_and_autoload_paths_differ'];
                } elseif ($copies !== [] && !in_array($targetRoot, $copies, true)) {
                    $report['errors'][] = ['code' => 'known_and_registered_module_paths_differ'];
                }
            }
        } catch (Throwable $error) {
            $report['errors'][] = ['code' => 'composer_registration_or_resolution_failed'];
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        if ($report['errors'] !== []) {
            return [1, $report];
        }
    }
    if (!is_string($targetRoot) || !is_dir($targetRoot)) {
        $report['errors'][] = ['code' => 'module_root_missing'];
        return [2, $report];
    }
    $report['paths']['module_root'] = $targetRoot;
    if ($targetRoot === $expectedRoot) {
        $report['errors'][] = ['code' => 'reference_and_target_roots_must_differ'];
        return [2, $report];
    }
    try {
        $target = coreshQuoteReleaseInventory($targetRoot);
        foreach ($target['unsafe'] as $file) {
            $report['errors'][] = ['code' => 'installed_unsafe_file', 'file' => $file];
        }
        foreach ($target['files'] as $file => $canonical) {
            if (!isset($runtimeManifest[$file])) {
                $report['errors'][] = ['code' => 'installed_unlisted_runtime_file', 'file' => $file];
            }
        }
        foreach ($runtimeManifest as $file => $hash) {
            $report['checked_runtime_files']++;
            if (!isset($target['files'][$file])) {
                $report['errors'][] = ['code' => 'installed_runtime_file_missing', 'file' => $file];
            } elseif (hash_file('sha256', $target['files'][$file]) !== $hash) {
                $report['errors'][] = ['code' => 'installed_runtime_file_changed', 'file' => $file];
            }
        }
    } catch (Throwable $error) {
        $report['errors'][] = ['code' => 'installed_file_read_failed'];
    }
    if ($report['errors'] !== []) {
        return [1, $report];
    }
    if (isset($options['module-root'])) {
        $report['not_verified'][] = 'installed_Composer_and_ComponentRegistrar_resolution';
    }
    $report['status'] = 'FILES_MATCH';
    return [0, $report];
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    set_error_handler(static function (int $severity): never {
        throw new RuntimeException('filesystem_or_autoload_warning');
    });
    try {
        if ($argc === 2 && $argv[1] === '--help') {
            $exitCode = 0;
            $report = coreshQuoteReleaseInitialReport();
            $report['status'] = 'USAGE';
            $report['usage'] = [
                'php tools/check_installed_release.php --module-root=/installed/module',
                'php tools/check_installed_release.php --magento-root=/Magento/root',
                'Optional: --expected-root=/trusted/extracted/Coresh_QuoteRequest',
            ];
        } else {
            [$exitCode, $report] = coreshQuoteReleaseCheck(array_slice($argv, 1));
        }
    } catch (Throwable $error) {
        $exitCode = 2;
        $report = coreshQuoteReleaseInitialReport();
        $report['errors'][] = ['code' => 'checker_failed'];
    } finally {
        restore_error_handler();
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
    exit($exitCode);
}
