<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Console\Commands\Compose;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SharedDependencyPreservationTest extends TestCase
{
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
     * Verifies that Mozart still prefixes a shared dependency while
     * preserving the original vendor copy when an installed non-processed
     * package depends on it as well.
     *
     * @test
     */
    #[Test]
    public function it_preserves_shared_processed_dependencies_in_vendor(): void
    {
        copy(__DIR__ . '/composer.json', $this->testsWorkingDir . '/composer.json');

        chdir($this->testsWorkingDir);
        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();
        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $this->assertEquals(0, $result);
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/symfony/service-contracts');
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/psr/container');
        $this->assertFileExists(
            $this->testsWorkingDir . '/src/dependencies/Psr/Container/ContainerInterface.php'
        );

        $containerInterface = file_get_contents(
            $this->testsWorkingDir . '/src/dependencies/Psr/Container/ContainerInterface.php'
        );

        $this->assertStringContainsString(
            'namespace Mozart\\TestProject\\Dependencies\\Psr\\Container;',
            $containerInterface
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $dir = $this->testsWorkingDir;

        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

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
