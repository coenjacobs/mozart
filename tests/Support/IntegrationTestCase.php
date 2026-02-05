<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Support;

use CoenJacobs\Mozart\Console\Commands\Compose;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for integration tests that need to run the full Mozart flow.
 *
 * Provides:
 * - Isolated temp directory for each test
 * - Helper methods to copy fixtures, run composer, and run Mozart
 * - Automatic cleanup in tearDown
 */
abstract class IntegrationTestCase extends TestCase
{
    protected string $testsWorkingDir;
    protected string $originalWorkingDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->originalWorkingDir = getcwd();
        $this->testsWorkingDir = sys_get_temp_dir() . '/mozart_integration_' . uniqid();

        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir, 0777, true);
        }
    }

    /**
     * Copy all fixture files from the test's directory to the working directory.
     * Looks for composer.json and any other files in the same directory as the test class.
     */
    protected function copyFixtures(): void
    {
        $testClassFile = (new ReflectionClass($this))->getFileName();
        $fixtureDir = dirname($testClassFile);

        // Copy composer.json if it exists
        $composerJson = $fixtureDir . '/composer.json';
        if (file_exists($composerJson)) {
            copy($composerJson, $this->testsWorkingDir . '/composer.json');
        }

        // Copy any other fixture files that might exist
        $files = glob($fixtureDir . '/*.json');
        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename !== 'composer.json') {
                copy($file, $this->testsWorkingDir . '/' . $basename);
            }
        }
    }

    /**
     * Run composer install/update in the working directory.
     */
    protected function runComposerInstall(): void
    {
        chdir($this->testsWorkingDir);
        exec('composer update 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $this->fail('Composer update failed: ' . implode("\n", $output));
        }
    }

    /**
     * Run Mozart compose command in the working directory.
     *
     * @return int Exit code from Mozart
     */
    protected function runMozart(): int
    {
        chdir($this->testsWorkingDir);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        return $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);
    }

    /**
     * Delete the working directory after each test.
     */
    public function tearDown(): void
    {
        parent::tearDown();

        // Restore original working directory
        chdir($this->originalWorkingDir);

        // Clean up test directory
        if (is_dir($this->testsWorkingDir)) {
            $this->removeDirectory($this->testsWorkingDir);
        }
    }

    /**
     * Recursively remove a directory and its contents.
     */
    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator(
            $it,
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
