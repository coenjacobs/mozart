<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Console\Commands\Compose;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExcludedPackagesTest extends TestCase {
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
     * Verifies that the explicitely excluded packages from the Mozart config
     * are _not_ being moved to the provided dependency directory and the files
     * will stay present in the vendor directory. At the same time, the other
     * package is being moved to the dependency directory and after that the
     * originating directory in the vendor directory is deleted (as the
     * `delete_vendor_directories` parameter is set to `true`).
     *
     * @test
     */
    #[Test]
    public function it_excludes_moving_specified_packages(): void
    {
        copy(__DIR__ . '/excluded-packages.json', $this->testsWorkingDir . '/composer.json');

        chdir($this->testsWorkingDir);

        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);
        $this->assertEquals(0, $result);

        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/psr/container');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/src/dependencies/Psr');
    }

    /**
     * Verifies that the excluded package `psr/container` is _not_ having its
     * classes replaced in the implementing `pimple/pimple` package when the
     * former is explicitely excluded and the latter is added to the list of
     * packages for Mozart to rewrite.
     *
     * @test
     */
    #[Test]
    public function it_excludes_replacing_classes_from_specified_packages(): void
    {
        copy(__DIR__ . '/excluded-packages.json', $this->testsWorkingDir . '/composer.json');

        chdir($this->testsWorkingDir);

        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);
        $this->assertEquals(0, $result);

        $testFile = file_get_contents($this->testsWorkingDir . '/src/dependencies/Pimple/Psr11/Container.php');
        $this->assertStringContainsString('namespace Mozart\TestProject\Dependencies\Pimple\Psr11;', $testFile);
        $this->assertStringContainsString('use Mozart\TestProject\Dependencies\Pimple\Container as PimpleContainer;', $testFile);
        $this->assertStringContainsString('use Psr\Container\ContainerInterface;', $testFile);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $dir = $this->testsWorkingDir;

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
        chdir(__DIR__);
    }
}
