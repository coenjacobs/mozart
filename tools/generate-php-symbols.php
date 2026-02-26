<?php

/**
 * PHP Built-in Symbol Database Generator
 *
 * Two modes:
 *   --version=X.Y   Collect mode: outputs JSON of all built-in symbols for the running PHP version.
 *   --merge DIR      Merge mode: reads per-version JSON files from DIR, unions all symbols,
 *                    and outputs a PHP data file to stdout.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$options = getopt('', ['version:', 'merge:']);

if (isset($options['version'])) {
    collectSymbols($options['version']);
} elseif (isset($options['merge'])) {
    mergeSymbols($options['merge']);
} else {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php generate-php-symbols.php --version=X.Y    Collect symbols (run inside target PHP version)\n");
    fwrite(STDERR, "  php generate-php-symbols.php --merge DIR       Merge per-version JSON files into PHP data file\n");
    exit(1);
}

/**
 * Collect mode: gather all built-in symbols from the running PHP runtime.
 */
function collectSymbols(string $version): void
{
    $classes = [];
    foreach (get_declared_classes() as $class) {
        $ref = new ReflectionClass($class);
        if ($ref->isInternal()) {
            $classes[] = $class;
        }
    }

    $interfaces = [];
    foreach (get_declared_interfaces() as $interface) {
        $ref = new ReflectionClass($interface);
        if ($ref->isInternal()) {
            $interfaces[] = $interface;
        }
    }

    $traits = [];
    foreach (get_declared_traits() as $trait) {
        $ref = new ReflectionClass($trait);
        if ($ref->isInternal()) {
            $traits[] = $trait;
        }
    }

    // Enums exist in PHP 8.1+; detect via ReflectionClass::isEnum()
    $enums = [];
    if (method_exists(ReflectionClass::class, 'isEnum')) {
        // Re-check classes — enums show up in get_declared_classes() on PHP 8.1+
        foreach (get_declared_classes() as $class) {
            $ref = new ReflectionClass($class);
            if ($ref->isInternal() && $ref->isEnum()) {
                $enums[] = $class;
                // Remove from classes list since it's an enum
                $classes = array_values(array_filter($classes, fn($c) => $c !== $class));
            }
        }
    }

    $functions = get_defined_functions()['internal'];

    $constants = [];
    $allConstants = get_defined_constants(true);
    foreach ($allConstants as $extension => $extensionConstants) {
        if ($extension === 'user') {
            continue;
        }
        foreach (array_keys($extensionConstants) as $name) {
            $constants[] = $name;
        }
    }

    $data = [
        'php_version' => $version,
        'classes'     => $classes,
        'interfaces'  => $interfaces,
        'traits'      => $traits,
        'enums'       => $enums,
        'functions'   => $functions,
        'constants'   => $constants,
    ];

    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

/**
 * Merge mode: read all per-version JSON files, union symbols, output PHP data file.
 */
function mergeSymbols(string $directory): void
{
    $directory = rtrim($directory, '/');

    $merged = [
        'classes'    => [],
        'interfaces' => [],
        'traits'     => [],
        'enums'      => [],
        'functions'  => [],
        'constants'  => [],
    ];

    $files = glob($directory . '/*.json');
    if (empty($files)) {
        fwrite(STDERR, "No JSON files found in {$directory}\n");
        exit(1);
    }

    sort($files);

    foreach ($files as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            fwrite(STDERR, "Failed to parse {$file}\n");
            exit(1);
        }

        foreach (['classes', 'interfaces', 'traits', 'enums', 'functions', 'constants'] as $category) {
            if (isset($data[$category]) && is_array($data[$category])) {
                foreach ($data[$category] as $symbol) {
                    $merged[$category][$symbol] = true;
                }
            }
        }
    }

    // Sort each category for stable output
    foreach ($merged as &$symbols) {
        ksort($symbols);
    }
    unset($symbols);

    // Output PHP data file
    echo "<?php\n";
    echo "\n";
    echo "/**\n";
    echo " * Auto-generated PHP built-in symbol database.\n";
    echo " *\n";
    echo " * Generated from PHP versions: " . implode(', ', array_map(
        fn($f) => pathinfo($f, PATHINFO_FILENAME),
        $files
    )) . "\n";
    echo " *\n";
    echo " * Do not edit manually. Regenerate with: bash tools/generate-php-symbols.sh\n";
    echo " */\n";
    echo "\n";
    echo "return [\n";

    foreach ($merged as $category => $symbols) {
        echo "    '{$category}' => [\n";
        foreach ($symbols as $name => $value) {
            $escapedName = addslashes($name);
            echo "        '{$escapedName}' => true,\n";
        }
        echo "    ],\n";
    }

    echo "];\n";
}
