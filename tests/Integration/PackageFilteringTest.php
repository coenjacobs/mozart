<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Console\Commands\Compose;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PackageFilteringTest extends TestCase {
    private string $testsWorkingDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir';
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }
    }

    /**
     * Verifies that only packages specified in the `packages` config are processed,
     * and packages NOT in the config remain in the vendor directory.
     *
     * @test
     */
    #[Test]
    public function it_only_processes_packages_specified_in_config(): void
    {
        copy(__DIR__ . '/package-filtering.json', $this->testsWorkingDir . '/composer.json');

        chdir($this->testsWorkingDir);

        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);
        $this->assertEquals(0, $result);

        // pimple/pimple is in packages config, so it should be moved
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        // monolog/monolog is NOT in packages config, so it should remain in vendor
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/monolog/monolog');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/src/dependencies/Monolog');
    }

    /**
     * Verifies that when `packages` config is empty, all packages from require
     * are processed (backward compatibility behavior).
     *
     * @test
     */
    #[Test]
    public function it_processes_all_packages_when_config_empty(): void
    {
        copy(__DIR__ . '/package-filtering-empty.json', $this->testsWorkingDir . '/composer.json');

        chdir($this->testsWorkingDir);

        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);
        $this->assertEquals(0, $result);

        // When packages is empty, all packages should be processed
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        // monolog/monolog should also be processed when packages is empty
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/monolog/monolog');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Monolog');
    }

    /**
     * Verifies that excluded_packages still works correctly when packages config
     * is specified. Excluded packages should not be processed even if they're
     * dependencies of included packages.
     *
     * @test
     */
    #[Test]
    public function it_respects_excluded_packages_when_filtering(): void
    {
        copy(__DIR__ . '/package-filtering-with-excluded.json', $this->testsWorkingDir . '/composer.json');

        chdir($this->testsWorkingDir);

        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);
        $this->assertEquals(0, $result);

        // pimple/pimple is in packages config, so it should be moved
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        // psr/container is in excluded_packages, so it should remain in vendor
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/psr/container');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/src/dependencies/Psr');

        // Verify that excluded package classes are not replaced in included packages
        $testFile = file_get_contents($this->testsWorkingDir . '/src/dependencies/Pimple/Psr11/Container.php');
        $this->assertStringContainsString('namespace Mozart\TestProject\Dependencies\Pimple\Psr11;', $testFile);
        $this->assertStringContainsString('use Mozart\TestProject\Dependencies\Pimple\Container as PimpleContainer;', $testFile);
        $this->assertStringContainsString('use Psr\Container\ContainerInterface;', $testFile);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $dir = $this->testsWorkingDir;

        if (is_dir($dir)) {
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
        chdir(__DIR__);
    }
}
