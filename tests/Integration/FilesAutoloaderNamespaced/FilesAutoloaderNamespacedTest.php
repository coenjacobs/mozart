<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\FilesAutoloaderNamespaced;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Composer 'files' autoloader with namespaced files
 * that are NOT inside a PSR-4 path.
 *
 * By using override_autoload to replace php-di/php-di's autoloaders with only
 * a "files" entry, we force the file through the Files code path (not PSR-4).
 * This exercises the NamespaceReplacer with setSearchNamespace().
 */
class FilesAutoloaderNamespacedTest extends IntegrationTestCase
{
    /**
     * Verifies that Mozart completes without errors when processing a
     * namespaced file via the files autoloader (without PSR-4).
     */
    #[Test]
    public function it_processes_namespaced_files_autoloader_without_psr4(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);
    }

    /**
     * Verifies that the output file has the prefixed namespace declaration
     * and prefixed function_exists() guards.
     */
    #[Test]
    public function it_prefixes_namespace_in_files_autoloader_entry(): void
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

        // function_exists() guards should have prefixed namespace strings
        $this->assertStringContainsString(
            "function_exists('Mozart\\\\TestProject\\\\Dependencies\\\\DI\\\\value')",
            $content,
            "function_exists() for value() should use prefixed namespace"
        );
    }
}
