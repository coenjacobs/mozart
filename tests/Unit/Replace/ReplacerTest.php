<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace;

use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\Replace\Classmap\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\Namespace\NamespaceReplacer;
use CoenJacobs\Mozart\Replace\Replacer;
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

    /** @test */
    public function it_creates_namespace_replacer_for_namespace_autoloader(): void
    {
        $autoloader = new Psr4();
        $autoloader->setNamespace('Original\\Namespace');

        $replacer = new Replacer($this->config);
        $result = $replacer->getReplacerByAutoloader($autoloader);

        $this->assertInstanceOf(NamespaceReplacer::class, $result);
    }

    /** @test */
    public function it_creates_classmap_replacer_for_classmap_autoloader(): void
    {
        $autoloader = new Classmap();
        $autoloader->processConfig(['path/to/files']);

        $replacer = new Replacer($this->config);
        $result = $replacer->getReplacerByAutoloader($autoloader);

        $this->assertInstanceOf(ClassmapReplacer::class, $result);
    }

    /** @test */
    public function it_skips_excluded_packages(): void
    {
        $this->expectNotToPerformAssertions();

        $package = new Package();
        $package->name = 'excluded/package';
        $this->config->setExcludedPackages(['excluded/package']);

        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config);
        $replacer->replacePackageByAutoloader($package, $autoloader);
    }

    /** @test */
    public function it_handles_empty_packages_array(): void
    {
        $this->expectNotToPerformAssertions();

        $replacer = new Replacer($this->config);
        $replacer->replacePackages([]);
    }

    /** @test */
    public function it_handles_package_with_no_autoloaders(): void
    {
        $this->expectNotToPerformAssertions();

        $package = new Package();
        $package->name = 'test/package';

        $replacer = new Replacer($this->config);
        $replacer->replacePackage($package);
    }

    /** @test */
    public function it_skips_files_that_cannot_be_read(): void
    {
        $this->expectNotToPerformAssertions();

        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config);
        $replacer->replaceInFile('nonexistent/file.php', $autoloader);
    }

    /** @test */
    public function it_skips_empty_file_contents(): void
    {
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'empty.php';
        file_put_contents($filePath, '');

        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config);
        $replacer->replaceInFile($filePath, $autoloader);

        $this->assertSame('', file_get_contents($filePath));
    }

    /** @test */
    public function it_handles_classmap_package_with_nonexistent_directory(): void
    {
        $package = new Package();
        $package->name = 'test/package';

        $autoloader = new Classmap();
        $autoloader->processConfig(['src/']);

        $replacer = new Replacer($this->config);
        $replacer->replacePackageByAutoloader($package, $autoloader);

        $this->assertEmpty($replacer->getReplacedClasses());
    }

    /** @test */
    public function it_handles_namespace_autoloader_with_nonexistent_directory(): void
    {
        $this->expectNotToPerformAssertions();

        $autoloader = new Psr4();
        $autoloader->setNamespace('Nonexistent\\Namespace');

        $replacer = new Replacer($this->config);
        $replacer->replaceInDirectory($autoloader, $this->testDir . DIRECTORY_SEPARATOR . 'nonexistent');
    }
}
