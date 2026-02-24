<?php

namespace CoenJacobs\Mozart\Tests\Unit\Autoload;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\FilesHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class NamespaceAutoloaderTargetFileTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_autoloader_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    /** @test */
    public function it_strips_src_path_from_psr4_target_file(): void
    {
        // Set up directory structure: vendor/php-di/php-di/src/Attribute/Inject.php
        $vendorDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'php-di' . DIRECTORY_SEPARATOR . 'php-di'
            . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Attribute';
        mkdir($vendorDir, 0777, true);
        file_put_contents($vendorDir . DIRECTORY_SEPARATOR . 'Inject.php', '<?php' . PHP_EOL);

        $config = new Mozart();
        $config->setDepDirectory('src/Dependencies/');
        $config->setDepNamespace('MyPlugin\\Dependencies\\');
        $config->setClassmapDirectory('src/Dependencies/classmap/');
        $config->setClassmapPrefix('MyPlugin_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'php-di/php-di';

        $autoloader = new Psr4();
        $autoloader->setPackage($package);
        $autoloader->setNamespace('DI\\');
        $autoloader->processConfig('src/');

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertNotEmpty($files, 'Should find files in vendor directory');

        $file = reset($files);
        $targetPath = $autoloader->getTargetFilePath($file);

        // The src/ directory should be stripped — the target path should NOT contain /src/
        $this->assertStringNotContainsString(
            DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Attribute',
            $targetPath,
            'The src/ path should be stripped from the target file path'
        );

        // Should end with the namespace-based path
        $this->assertStringEndsWith(
            DIRECTORY_SEPARATOR . 'DI' . DIRECTORY_SEPARATOR . 'Attribute' . DIRECTORY_SEPARATOR . 'Inject.php',
            $targetPath
        );
    }

    /** @test */
    public function it_handles_empty_psr4_path(): void
    {
        // Set up directory structure: vendor/some/package/Foo/Bar.php
        $vendorDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'some' . DIRECTORY_SEPARATOR . 'package'
            . DIRECTORY_SEPARATOR . 'Foo';
        mkdir($vendorDir, 0777, true);
        file_put_contents($vendorDir . DIRECTORY_SEPARATOR . 'Bar.php', '<?php' . PHP_EOL);

        $config = new Mozart();
        $config->setDepDirectory('lib/Dependencies/');
        $config->setDepNamespace('Plugin\\Deps\\');
        $config->setClassmapDirectory('lib/Dependencies/classmap/');
        $config->setClassmapPrefix('Plugin_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'some/package';

        $autoloader = new Psr4();
        $autoloader->setPackage($package);
        $autoloader->setNamespace('Some\\Namespace\\');
        $autoloader->processConfig('');

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertNotEmpty($files, 'Should find files in vendor directory');

        $file = reset($files);
        $targetPath = $autoloader->getTargetFilePath($file);

        // Vendor path components should not be in the target
        $this->assertStringNotContainsString('vendor', $targetPath);
        $this->assertStringNotContainsString('some' . DIRECTORY_SEPARATOR . 'package', $targetPath);

        // Should contain namespace path and the file
        $this->assertStringContainsString(
            'Some' . DIRECTORY_SEPARATOR . 'Namespace',
            $targetPath
        );
        $this->assertStringEndsWith('Bar.php', $targetPath);
    }

    /** @test */
    public function it_strips_path_with_array_of_psr4_paths(): void
    {
        // Set up directory structure with two source paths
        $srcDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'multi'
            . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Core';
        mkdir($srcDir, 0777, true);
        file_put_contents($srcDir . DIRECTORY_SEPARATOR . 'Engine.php', '<?php' . PHP_EOL);

        $libDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'multi'
            . DIRECTORY_SEPARATOR . 'lib';
        mkdir($libDir, 0777, true);
        file_put_contents($libDir . DIRECTORY_SEPARATOR . 'Helper.php', '<?php' . PHP_EOL);

        $config = new Mozart();
        $config->setDepDirectory('deps/');
        $config->setDepNamespace('App\\Deps\\');
        $config->setClassmapDirectory('deps/classmap/');
        $config->setClassmapPrefix('App_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'test/multi';

        $autoloader = new Psr4();
        $autoloader->setPackage($package);
        $autoloader->setNamespace('Test\\Multi\\');
        $autoloader->processConfig(['src/', 'lib/']);

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertCount(2, $files, 'Should find files from both source paths');

        foreach ($files as $file) {
            $targetPath = $autoloader->getTargetFilePath($file);

            // Neither src/ nor lib/ should remain in the target path
            $this->assertStringNotContainsString(
                DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Core',
                $targetPath,
                'The src/ path should be stripped from target: ' . $targetPath
            );
            $this->assertStringNotContainsString(
                DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Helper',
                $targetPath,
                'The lib/ path should be stripped from target: ' . $targetPath
            );
        }
    }

    /**
     * @param string $dir
     */
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
}
