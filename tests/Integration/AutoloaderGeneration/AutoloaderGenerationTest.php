<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\AutoloaderGeneration;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for autoloader generation.
 *
 * Uses psr/container (PSR-4 namespaced) and mustangostang/spyc (files autoloader
 * with global-scope class) to verify the full autoloader generation flow.
 */
class AutoloaderGenerationTest extends IntegrationTestCase
{
    /**
     * Verifies that Mozart generates the autoload.php entry point in dep_directory.
     */
    #[Test]
    public function it_generates_autoload_entry_point(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);
        $this->assertFileExists($this->testsWorkingDir . '/src/dependencies/autoload.php');
    }

    /**
     * Verifies the composer/ directory is created with all expected files.
     */
    #[Test]
    public function it_generates_composer_directory_with_all_files(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $composerDir = $this->testsWorkingDir . '/src/dependencies/composer';
        $this->assertDirectoryExists($composerDir);
        $this->assertFileExists($composerDir . '/ClassLoader.php');
        $this->assertFileExists($composerDir . '/autoload_real.php');
        $this->assertFileExists($composerDir . '/autoload_static.php');
        $this->assertFileExists($composerDir . '/autoload_classmap.php');
        $this->assertFileExists($composerDir . '/autoload_psr4.php');
        $this->assertFileExists($composerDir . '/LICENSE');
    }

    /**
     * Verifies that the classmap contains prefixed class names for both
     * namespaced and global-scope prefixed classes.
     */
    #[Test]
    public function it_generates_classmap_with_prefixed_names(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $classmapFile = $this->testsWorkingDir . '/src/dependencies/composer/autoload_classmap.php';
        $content = file_get_contents($classmapFile);

        // Namespaced class should be in the classmap with prefixed namespace
        $this->assertStringContainsString(
            'Mozart\\\\TestProject\\\\Dependencies\\\\Psr\\\\Container\\\\ContainerInterface',
            $content,
            'Classmap should contain prefixed namespaced class'
        );

        // Global-scope class should be in the classmap with classmap prefix
        $this->assertStringContainsString(
            'MozartTest_Spyc',
            $content,
            'Classmap should contain classmap-prefixed global class'
        );
    }

    /**
     * Verifies PSR-4 maps the dep namespace to the dep directory.
     */
    #[Test]
    public function it_generates_psr4_mapping(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $psr4File = $this->testsWorkingDir . '/src/dependencies/composer/autoload_psr4.php';
        $content = file_get_contents($psr4File);

        $this->assertStringContainsString(
            'Mozart\\\\TestProject\\\\Dependencies\\\\',
            $content,
            'PSR-4 should map the dependency namespace'
        );
    }

    /**
     * Verifies files autoloader entries are generated for spyc files.
     */
    #[Test]
    public function it_generates_files_autoloader_entries(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $filesFile = $this->testsWorkingDir . '/src/dependencies/composer/autoload_files.php';
        $this->assertFileExists($filesFile, 'autoload_files.php should exist when files autoloader targets are present');

        $content = file_get_contents($filesFile);
        $this->assertStringContainsString('Spyc.php', $content);
    }

    /**
     * Gold test: verify the generated autoloader actually works by executing
     * a PHP subprocess that requires it and checks class_exists().
     */
    #[Test]
    public function it_produces_a_working_autoloader(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // Write a test script that uses the generated autoloader
        $testScript = <<<'PHP'
<?php
$loader = require __DIR__ . '/src/dependencies/autoload.php';

$checks = [
    'Mozart\TestProject\Dependencies\Psr\Container\ContainerInterface',
    'Mozart\TestProject\Dependencies\Psr\Container\ContainerExceptionInterface',
    'Mozart\TestProject\Dependencies\Psr\Container\NotFoundExceptionInterface',
    'MozartTest_Spyc',
];

$failures = [];
foreach ($checks as $class) {
    if (!class_exists($class) && !interface_exists($class)) {
        $failures[] = $class;
    }
}

if (!empty($failures)) {
    echo 'FAIL: ' . implode(', ', $failures);
    exit(1);
}

echo 'OK';
exit(0);
PHP;

        file_put_contents($this->testsWorkingDir . '/test_autoloader.php', $testScript);

        exec(
            'php ' . escapeshellarg($this->testsWorkingDir . '/test_autoloader.php') . ' 2>&1',
            $output,
            $exitCode
        );

        $outputStr = implode("\n", $output);
        $this->assertEquals(0, $exitCode, "Autoloader test failed: {$outputStr}");
        $this->assertStringContainsString('OK', $outputStr);
    }

    /**
     * Verifies the static autoloader contains proper prefix data.
     */
    #[Test]
    public function it_generates_static_autoloader_with_prefix_data(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $staticFile = $this->testsWorkingDir . '/src/dependencies/composer/autoload_static.php';
        $content = file_get_contents($staticFile);

        // Should contain PSR-4 prefix length for the first character of the namespace
        $this->assertStringContainsString('$prefixLengthsPsr4', $content);
        $this->assertStringContainsString("'M'", $content, 'Should have prefix length entry for M');

        // Should contain prefix directories
        $this->assertStringContainsString('$prefixDirsPsr4', $content);

        // Should contain classmap
        $this->assertStringContainsString('$classMap', $content);
    }
}
