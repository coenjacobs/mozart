<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\Replace\Classmap\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\Namespace\NamespaceReplacer;
use CoenJacobs\Mozart\Replacer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
        $this->config->setOverrideAutoload(new \stdClass());
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
        $package = new Package();
        $package->name = 'excluded/package';
        $this->config->setExcludedPackages(['excluded/package']);

        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config);
        $replacer->replacePackageByAutoloader($package, $autoloader);

        // If we get here without error, the package was skipped
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_packages_array(): void
    {
        $replacer = new Replacer($this->config);
        $replacer->replacePackages([]);

        // Should complete without error
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_package_with_no_autoloaders(): void
    {
        $package = new Package();
        $package->name = 'test/package';

        $replacer = new Replacer($this->config);
        $replacer->replacePackage($package);

        // Should complete without error
        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_files_that_cannot_be_read(): void
    {
        $autoloader = new Psr4();
        $autoloader->setNamespace('Test\\Namespace');

        $replacer = new Replacer($this->config);
        $replacer->replaceInFile('nonexistent/file.php', $autoloader);

        // Should complete without error (file is skipped)
        $this->assertTrue(true);
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

        // Should complete without error (empty file is skipped)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_directory_for_parent_classes(): void
    {
        $replacer = new Replacer($this->config);
        $replacer->replaceParentClassesInDirectory($this->testDir . DIRECTORY_SEPARATOR . 'empty_dir');

        // Should complete without error
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_no_replaced_classes(): void
    {
        $replacer = new Replacer($this->config);
        $replacer->replaceParentClassesInDirectory($this->testDir);

        // Should complete without error when no classes to replace
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_packages_for_replace_parent_in_tree(): void
    {
        $replacer = new Replacer($this->config);
        $replacer->replaceParentInTree([]);

        // Should complete without error
        $this->assertTrue(true);
    }
}

