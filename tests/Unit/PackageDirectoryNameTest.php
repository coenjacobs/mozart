<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit;

use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\PackageFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PackageDirectoryNameTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_directory_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
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
    public function it_extracts_directory_name_from_vendor_path(): void
    {
        // Create a package where directory name differs from package name
        // Directory: vendor/vendor/package-path
        // Package name: vendor/package-name
        $vendorDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor';
        $packageDir = $vendorDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'package-path';
        mkdir($packageDir, 0777, true);

        $composerJson = [
            'name' => 'vendor/package-name',
            'require' => [],
        ];
        $composerJsonPath = $packageDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($composerJsonPath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($composerJsonPath);

        $this->assertInstanceOf(Package::class, $package);
        $this->assertEquals('vendor/package-name', $package->name);
        $this->assertEquals('vendor/package-path', $package->getDirectoryName());
        $this->assertEquals('vendor/package-path', $package->directoryName);
    }

    #[Test]
    public function it_falls_back_to_package_name_when_directory_name_not_set(): void
    {
        // Create a package without vendor path (e.g., root composer.json)
        $composerJson = [
            'name' => 'vendor/package-name',
            'require' => [],
        ];
        $composerJsonPath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($composerJsonPath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($composerJsonPath);

        $this->assertInstanceOf(Package::class, $package);
        $this->assertEquals('vendor/package-name', $package->name);
        // When directory name is not set, getDirectoryName() should fall back to package name
        $this->assertEquals('vendor/package-name', $package->getDirectoryName());
    }

    #[Test]
    public function it_uses_directory_name_for_path_construction(): void
    {
        // Create a package with autoload configuration
        $vendorDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor';
        $packageDir = $vendorDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'package-path';
        mkdir($packageDir, 0777, true);

        // Create a source file
        $srcDir = $packageDir . DIRECTORY_SEPARATOR . 'src';
        mkdir($srcDir, 0777, true);
        $srcFile = $srcDir . DIRECTORY_SEPARATOR . 'TestClass.php';
        file_put_contents($srcFile, '<?php class TestClass {}');

        $composerJson = [
            'name' => 'vendor/package-name',
            'require' => [],
            'autoload' => [
                'psr-4' => [
                    'Vendor\\PackageName\\' => 'src/',
                ],
            ],
        ];
        $composerJsonPath = $packageDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($composerJsonPath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($composerJsonPath);

        // Verify that the directory name is set correctly
        $this->assertEquals('vendor/package-path', $package->getDirectoryName());

        // The autoloaders should be able to find files using the directory name
        $autoloaders = $package->getAutoloaders();
        $this->assertNotEmpty($autoloaders);
    }
}

