<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\FilesAutoloader;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Composer 'files' autoloader support.
 *
 * Tests that files listed in composer.json's autoload.files are properly
 * processed by Mozart, including namespace replacement in function_exists()
 * and similar string literals.
 */
class FilesAutoloaderTest extends IntegrationTestCase
{
    /**
     * Verifies that Mozart processes the php-di/php-di package with files autoloader.
     */
    #[Test]
    public function it_processes_package_with_files_autoloader(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // DI directory is created
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/DI');
    }

    /**
     * Verifies that PHP-DI's functions.php file is moved correctly.
     *
     * Note: PHP-DI's functions.php is inside the src/ directory which is covered
     * by PSR-4, so it gets moved by the PSR-4 autoloader. This test verifies
     * that the file exists in the expected location after Mozart processing.
     */
    #[Test]
    public function it_moves_php_di_functions_file(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // functions.php should be in the DI namespace directory
        // (it's inside src/ which is covered by PSR-4)
        $functionsPath = $this->testsWorkingDir . '/src/dependencies/DI/functions.php';
        $this->assertFileExists($functionsPath);
    }

    /**
     * Verifies that function_exists() string literals in PHP-DI's functions.php
     * are correctly updated with the prefixed namespace.
     *
     * PHP-DI's functions.php uses guards like:
     *   if (!function_exists('DI\value')) { function value() {...} }
     *
     * These string literals must be updated to the prefixed namespace, otherwise
     * the guards will fail to detect already-defined functions.
     *
     * @see https://github.com/PHP-DI/PHP-DI/blob/master/src/functions.php
     */
    #[Test]
    public function it_replaces_function_exists_string_literals(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $functionsPath = $this->testsWorkingDir . '/src/dependencies/DI/functions.php';
        $this->assertFileExists($functionsPath);

        $content = file_get_contents($functionsPath);

        // Namespace declaration should be prefixed
        $this->assertStringContainsString(
            'namespace Mozart\\TestProject\\Dependencies\\DI;',
            $content,
            'Namespace should be prefixed'
        );

        // All function_exists() guards should have prefixed namespace strings
        // PHP-DI defines: value, create, autowire, factory, decorate, get, env, add, string
        $functionNames = ['value', 'create', 'autowire', 'factory', 'decorate', 'get', 'env', 'add', 'string'];

        foreach ($functionNames as $funcName) {
            // Check that prefixed version exists (with escaped backslashes in file)
            $this->assertStringContainsString(
                "function_exists('Mozart\\TestProject\\Dependencies\\DI\\{$funcName}')",
                $content,
                "function_exists() for {$funcName}() should use prefixed namespace"
            );

            // Check that unprefixed version does NOT exist
            $this->assertStringNotContainsString(
                "function_exists('DI\\{$funcName}')",
                $content,
                "Original unprefixed function_exists() for {$funcName}() should not remain"
            );
        }
    }

    /**
     * Verifies that the functions inside functions.php are also properly namespaced.
     */
    #[Test]
    public function it_keeps_functions_in_correct_namespace(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $functionsPath = $this->testsWorkingDir . '/src/dependencies/DI/functions.php';
        $content = file_get_contents($functionsPath);

        // The file should declare the prefixed namespace
        $this->assertStringContainsString(
            'namespace Mozart\\TestProject\\Dependencies\\DI;',
            $content
        );

        // Functions like value(), factory(), etc. should be defined
        $this->assertStringContainsString('function value(', $content);
    }
}
