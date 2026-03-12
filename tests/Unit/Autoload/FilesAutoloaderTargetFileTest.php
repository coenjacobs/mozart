<?php

namespace CoenJacobs\Mozart\Tests\Unit\Autoload;

use CoenJacobs\Mozart\Config\Files;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\FilesHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilesAutoloaderTargetFileTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_files_target_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    #[Test]
    public function it_produces_distinct_paths_for_same_basename_global_files(): void
    {
        // Set up: vendor/acme/pkg/src/helpers.php and vendor/acme/pkg/lib/helpers.php
        $srcDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'src';
        $libDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'lib';

        mkdir($srcDir, 0777, true);
        mkdir($libDir, 0777, true);

        // Both files are global scope (no namespace)
        file_put_contents(
            $srcDir . DIRECTORY_SEPARATOR . 'helpers.php',
            "<?php\nfunction src_helper() {}\n"
        );
        file_put_contents(
            $libDir . DIRECTORY_SEPARATOR . 'helpers.php',
            "<?php\nfunction lib_helper() {}\n"
        );

        $config = new Mozart();
        $config->setDepDirectory('deps/');
        $config->setDepNamespace('MyPlugin\\Deps\\');
        $config->setClassmapDirectory('deps/classmap/');
        $config->setClassmapPrefix('MyPlugin_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'acme/pkg';

        $autoloader = new Files();
        $autoloader->setPackage($package);
        $autoloader->processConfig(['src/helpers.php', 'lib/helpers.php']);

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertCount(2, $files, 'Should find both files');

        $targetPaths = [];
        foreach ($files as $file) {
            $targetPaths[] = $autoloader->getTargetFilePath($file);
        }

        // Both paths must be distinct
        $this->assertCount(2, array_unique($targetPaths), 'Target paths must be unique');

        // Verify the relative directories are preserved
        $this->assertContains(
            'deps' . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR
                . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
                . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'helpers.php',
            $targetPaths
        );
        $this->assertContains(
            'deps' . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR
                . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
                . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php',
            $targetPaths
        );
    }

    #[Test]
    public function it_produces_distinct_paths_for_same_basename_namespaced_files(): void
    {
        // Set up: vendor/acme/pkg/src/bootstrap.php and vendor/acme/pkg/lib/bootstrap.php
        $srcDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'src';
        $libDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'lib';

        mkdir($srcDir, 0777, true);
        mkdir($libDir, 0777, true);

        // Both files have the same namespace
        file_put_contents(
            $srcDir . DIRECTORY_SEPARATOR . 'bootstrap.php',
            "<?php\nnamespace Acme\\Utils;\nfunction src_init() {}\n"
        );
        file_put_contents(
            $libDir . DIRECTORY_SEPARATOR . 'bootstrap.php',
            "<?php\nnamespace Acme\\Utils;\nfunction lib_init() {}\n"
        );

        $config = new Mozart();
        $config->setDepDirectory('deps/');
        $config->setDepNamespace('MyPlugin\\Deps\\');
        $config->setClassmapDirectory('deps/classmap/');
        $config->setClassmapPrefix('MyPlugin_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'acme/pkg';

        $autoloader = new Files();
        $autoloader->setPackage($package);
        $autoloader->processConfig(['src/bootstrap.php', 'lib/bootstrap.php']);

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertCount(2, $files, 'Should find both files');

        $targetPaths = [];
        foreach ($files as $file) {
            $targetPaths[] = $autoloader->getTargetFilePath($file);
        }

        // Both paths must be distinct
        $this->assertCount(2, array_unique($targetPaths), 'Target paths must be unique');

        // Verify relative directories are preserved in namespace path
        $this->assertContains(
            'deps' . DIRECTORY_SEPARATOR
                . 'Acme' . DIRECTORY_SEPARATOR . 'Utils'
                . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php',
            $targetPaths
        );
        $this->assertContains(
            'deps' . DIRECTORY_SEPARATOR
                . 'Acme' . DIRECTORY_SEPARATOR . 'Utils'
                . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'bootstrap.php',
            $targetPaths
        );
    }

    #[Test]
    public function it_does_not_add_extra_path_for_root_level_file(): void
    {
        // Set up: vendor/acme/pkg/helpers.php (at package root, no subdirectory)
        $pkgDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'pkg';
        mkdir($pkgDir, 0777, true);

        file_put_contents(
            $pkgDir . DIRECTORY_SEPARATOR . 'helpers.php',
            "<?php\nfunction root_helper() {}\n"
        );

        $config = new Mozart();
        $config->setDepDirectory('deps/');
        $config->setDepNamespace('MyPlugin\\Deps\\');
        $config->setClassmapDirectory('deps/classmap/');
        $config->setClassmapPrefix('MyPlugin_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'acme/pkg';

        $autoloader = new Files();
        $autoloader->setPackage($package);
        $autoloader->processConfig(['helpers.php']);

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertCount(1, $files);

        $file = reset($files);
        $targetPath = $autoloader->getTargetFilePath($file);

        // Should be classmap_dir/package/helpers.php with no extra directory segment
        $expected = 'deps' . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR
            . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'helpers.php';
        $this->assertEquals($expected, $targetPath);
    }

    #[Test]
    public function it_includes_subdirectory_for_single_file_in_subdirectory(): void
    {
        // Set up: vendor/acme/pkg/src/helpers.php
        $srcDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'src';
        mkdir($srcDir, 0777, true);

        file_put_contents(
            $srcDir . DIRECTORY_SEPARATOR . 'helpers.php',
            "<?php\nfunction my_helper() {}\n"
        );

        $config = new Mozart();
        $config->setDepDirectory('deps/');
        $config->setDepNamespace('MyPlugin\\Deps\\');
        $config->setClassmapDirectory('deps/classmap/');
        $config->setClassmapPrefix('MyPlugin_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'acme/pkg';

        $autoloader = new Files();
        $autoloader->setPackage($package);
        $autoloader->processConfig(['src/helpers.php']);

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertCount(1, $files);

        $file = reset($files);
        $targetPath = $autoloader->getTargetFilePath($file);

        // Should include the src/ subdirectory
        $expected = 'deps' . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR
            . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'helpers.php';
        $this->assertEquals($expected, $targetPath);
    }

    #[Test]
    public function it_handles_nested_subdirectories(): void
    {
        // Set up: vendor/acme/pkg/src/utils/deep/helpers.php
        $deepDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'utils'
            . DIRECTORY_SEPARATOR . 'deep';
        mkdir($deepDir, 0777, true);

        file_put_contents(
            $deepDir . DIRECTORY_SEPARATOR . 'helpers.php',
            "<?php\nfunction deep_helper() {}\n"
        );

        $config = new Mozart();
        $config->setDepDirectory('deps/');
        $config->setDepNamespace('MyPlugin\\Deps\\');
        $config->setClassmapDirectory('deps/classmap/');
        $config->setClassmapPrefix('MyPlugin_');
        $config->setWorkingDir($this->testDir . DIRECTORY_SEPARATOR);

        $package = new Package();
        $package->name = 'acme/pkg';

        $autoloader = new Files();
        $autoloader->setPackage($package);
        $autoloader->processConfig(['src/utils/deep/helpers.php']);

        $fileHandler = new FilesHandler($config);
        $files = $autoloader->getFiles($fileHandler);

        $this->assertCount(1, $files);

        $file = reset($files);
        $targetPath = $autoloader->getTargetFilePath($file);

        // Should preserve the full nested directory structure
        $expected = 'deps' . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR
            . 'acme' . DIRECTORY_SEPARATOR . 'pkg'
            . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'utils'
            . DIRECTORY_SEPARATOR . 'deep'
            . DIRECTORY_SEPARATOR . 'helpers.php';
        $this->assertEquals($expected, $targetPath);
    }

    /**
     * Recursively remove a directory and its contents.
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
