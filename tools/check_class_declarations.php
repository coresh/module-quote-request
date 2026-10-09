<?php
declare(strict_types=1);

/**
 * Loads declarations only, in one isolated PHP process per application class.
 * Dependencies are unmodified, hash-verified upstream sources from the manifest.
 * Does not construct objects, compile DI, generate interceptors or bootstrap Magento.
 */

$options = getopt('', ['module:', 'reference:', 'class:', 'list']);
$module = realpath((string) ($options['module'] ?? dirname(__DIR__)));
if ($module === false) {
    fwrite(STDERR, "A valid --module directory is required.\n");
    exit(2);
}

if (isset($options['list'])) {
    $classes = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($module));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($module) + 1);
        if (preg_match('~^(tests|tools)/~', $relative) || $relative === 'registration.php') {
            continue;
        }
        $tokens = token_get_all((string) file_get_contents($file->getPathname()));
        $namespace = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $count && $tokens[$j] !== ';' && $tokens[$j] !== '{'; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                        $namespace .= $tokens[$j][1];
                    }
                }
            }
            if (in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $classes[] = ['class' => $namespace . '\\' . $tokens[$j][1], 'file' => $relative];
                }
            }
        }
    }
    usort($classes, static fn (array $a, array $b): int => strcmp($a['class'], $b['class']));
    echo json_encode($classes, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

$reference = realpath((string) ($options['reference'] ?? ''));
$class = (string) ($options['class'] ?? '');
if ($reference === false || !preg_match('~^Coresh\\\\QuoteRequest\\\\[A-Za-z0-9_\\\\]+$~', $class)) {
    fwrite(STDERR, "A valid --reference directory and --class application name are required.\n");
    exit(2);
}
$manifest = json_decode((string) file_get_contents($reference . '/class_manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['schema'] ?? null) !== 1 || !is_array($manifest['classes'] ?? null)) {
    fwrite(STDERR, "Invalid source manifest.\n");
    exit(2);
}

$loaded = [];
spl_autoload_register(static function (string $name) use ($module, $reference, $manifest, &$loaded): void {
    if (str_starts_with($name, 'Coresh\\QuoteRequest\\')) {
        $relative = substr($name, strlen('Coresh\\QuoteRequest\\'));
        $path = $module . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
            return;
        }
    } elseif (isset($manifest['classes'][$name])) {
        $entry = $manifest['classes'][$name];
        $path = realpath($reference . '/' . $entry['file']);
        if ($path === false || !str_starts_with($path, $reference . '/')
            || !hash_equals($entry['sha256'], (string) hash_file('sha256', $path))) {
            throw new RuntimeException('Reference source failed path/hash validation: ' . $name);
        }
        $loaded[$name] = $entry;
        require $path;
        return;
    }
    fwrite(STDERR, 'MISSING_CLASS ' . json_encode($name, JSON_THROW_ON_ERROR) . "\n");
});

try {
    $exists = class_exists($class) || interface_exists($class) || trait_exists($class);
    if (!$exists) {
        throw new RuntimeException('Application declaration was not loaded.');
    }
    $reflection = new ReflectionClass($class);
    $parents = [];
    for ($parent = $reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
        $parents[] = $parent->getName();
    }
    echo json_encode(['class' => $class, 'status' => 'PASS', 'parents' => $parents,
        'interfaces' => $reflection->getInterfaceNames(), 'loaded_sources' => array_keys($loaded)],
        JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $exception) {
    echo json_encode(['class' => $class, 'status' => 'FAIL', 'error' => $exception->getMessage()],
        JSON_THROW_ON_ERROR) . "\n";
    exit(1);
}
