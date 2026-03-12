<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace;

use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\PhpSymbols\BuiltInSymbols;
use CoenJacobs\Mozart\Replace\GlobalScope\GlobalScopeReplacer;
use CoenJacobs\Mozart\Replace\Namespace\NamespaceReplacer;
use CoenJacobs\Mozart\Replace\Replacer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class ReplacerTest extends TestCase
{
    private string $testDir;
    private Mozart $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_replacer_test_' . uniqid();
        mkdir($this->testDir, 0777, true);

        $this->config = new Mozart();
        $this->config->setDepNamespace('Test\\Namespace\\');
        $this->config->setDepDirectory($this->testDir . DIRECTORY_SEPARATOR . 'deps' . DIRECTORY_SEPARATOR);
        $this->config->setClassmapDirectory($this->testDir . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR);
        $this->config->setClassmapPrefix('Test_');
        $this->config->setWorkingDir($this->testDir);
        $this->config->setOverrideAutoload(new stdClass());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    #[Test]
    public function it_creates_namespace_replacer_for_namespace_autoloader(): void
    {
        $autoloader = new Psr4();
        $autoloader->setNamespace('Original\\Namespace');

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $result = $replacer->getReplacerByAutoloader($autoloader);

        $this->assertInstanceOf(NamespaceReplacer::class, $result);
    }

    #[Test]
    public function it_creates_classmap_replacer_for_classmap_autoloader(): void
    {
        $autoloader = new Classmap();
        $autoloader->processConfig(['path/to/files']);

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $result = $replacer->getReplacerByAutoloader($autoloader);

        $this->assertInstanceOf(GlobalScopeReplacer::class, $result);
    }

    #[Test]
    public function it_skips_excluded_packages(): void
    {
        $this->expectNotToPerformAssertions();

        $package = new Package();
        $package->name = 'excluded/package';
        $this->config->setExcludedPackages(['excluded/package']);

        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replacePackageByAutoloader($package, $autoloader);
    }

    #[Test]
    public function it_handles_empty_packages_array(): void
    {
        $this->expectNotToPerformAssertions();

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replacePackages([]);
    }

    #[Test]
    public function it_handles_package_with_no_autoloaders(): void
    {
        $this->expectNotToPerformAssertions();

        $package = new Package();
        $package->name = 'test/package';

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replacePackage($package);
    }

    #[Test]
    public function it_skips_files_that_cannot_be_read(): void
    {
        $this->expectNotToPerformAssertions();

        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replaceInFile('nonexistent/file.php', $autoloader);
    }

    #[Test]
    public function it_skips_empty_file_contents(): void
    {
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'empty.php';
        file_put_contents($filePath, '');

        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replaceInFile($filePath, $autoloader);

        $this->assertSame('', file_get_contents($filePath));
    }

    #[Test]
    public function it_handles_classmap_package_with_nonexistent_directory(): void
    {
        $package = new Package();
        $package->name = 'test/package';

        $autoloader = new Classmap();
        $autoloader->processConfig(['src/']);

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replacePackageByAutoloader($package, $autoloader);

        $this->assertEmpty($replacer->getReplacedClasses());
    }

    #[Test]
    public function it_handles_namespace_autoloader_with_nonexistent_directory(): void
    {
        $this->expectNotToPerformAssertions();

        $autoloader = new Psr4();
        $autoloader->setNamespace('Nonexistent\\Namespace');

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replaceInDirectory($autoloader, $this->testDir . DIRECTORY_SEPARATOR . 'nonexistent');
    }

    #[Test]
    public function it_processes_each_package_only_once_in_diamond_dependency_graph(): void
    {
        // Diamond: A→B→D, A→C→D — D should only be processed once.
        $packageD = new Package();
        $packageD->name = 'vendor/d';

        $packageB = new Package();
        $packageB->name = 'vendor/b';
        $packageB->registerDependency($packageD);

        $packageC = new Package();
        $packageC->name = 'vendor/c';
        $packageC->registerDependency($packageD);

        $packageA = new Package();
        $packageA->name = 'vendor/a';
        $packageA->registerDependency($packageB);
        $packageA->registerDependency($packageC);

        $replacer = $this->getMockBuilder(Replacer::class)
            ->setConstructorArgs([$this->config, new BuiltInSymbols()])
            ->onlyMethods(['replacePackage'])
            ->getMock();

        $processed = [];
        $replacer->expects($this->exactly(4))
            ->method('replacePackage')
            ->willReturnCallback(function (Package $package) use (&$processed): void {
                $processed[] = $package->getName();
            });

        $replacer->replacePackages([$packageA]);

        $this->assertSame(['vendor/d', 'vendor/b', 'vendor/c', 'vendor/a'], $processed);
    }
}
