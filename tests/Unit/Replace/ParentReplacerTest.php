<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace;

use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\PhpSymbols\BuiltInSymbols;
use CoenJacobs\Mozart\Replace\ParentReplacer;
use CoenJacobs\Mozart\Replace\Replacer;
use PHPUnit\Framework\TestCase;
use stdClass;

class ParentReplacerTest extends TestCase
{
    private string $testDir;
    private Mozart $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_parent_replacer_test_' . uniqid();
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

    private function createRelativeConfig(): Mozart
    {
        $config = new Mozart();
        $config->setDepNamespace('Test\\Namespace\\');
        $config->setDepDirectory('deps' . DIRECTORY_SEPARATOR);
        $config->setClassmapDirectory('classmap' . DIRECTORY_SEPARATOR);
        $config->setClassmapPrefix('Test_');
        $config->setWorkingDir($this->testDir);
        $config->setOverrideAutoload(new stdClass());

        return $config;
    }

    private function createPsr4Package(string $name, string $namespace, array $paths): Package
    {
        $package = new Package();
        $package->name = $name;

        $autoload = new stdClass();
        $psr4 = new stdClass();
        $psr4->{$namespace} = $paths;
        $autoload->{'psr-4'} = $psr4;

        $package->setAutoload($autoload);

        return $package;
    }

    private function createClassmapPackage(string $name, array $paths): Package
    {
        $package = new Package();
        $package->name = $name;

        $autoload = new stdClass();
        $autoload->classmap = $paths;

        $package->setAutoload($autoload);

        return $package;
    }

    private function createFilesPackage(string $name, array $files): Package
    {
        $package = new Package();
        $package->name = $name;

        $autoload = new stdClass();
        $autoload->files = $files;

        $package->setAutoload($autoload);

        return $package;
    }

    private function replaceParentPackageInWorkingDirectory(
        ParentReplacer $parentReplacer,
        Package $child,
        Package $parent
    ): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        try {
            $parentReplacer->replaceParentPackage($child, $parent);
        } finally {
            chdir($originalCwd);
        }
    }

    /** @test */
    public function it_handles_empty_directory_for_parent_classes(): void
    {
        $this->expectNotToPerformAssertions();

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->replaceParentClassesInDirectory($this->testDir . DIRECTORY_SEPARATOR . 'empty_dir');
    }

    /** @test */
    public function it_handles_no_replaced_classes(): void
    {
        $this->expectNotToPerformAssertions();

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->replaceParentClassesInDirectory($this->testDir);
    }

    /** @test */
    public function it_handles_empty_packages_for_replace_parent_in_tree(): void
    {
        $this->expectNotToPerformAssertions();

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->replaceParentInTree([]);
    }

    /** @test */
    public function it_handles_parent_classes_in_nonexistent_directory(): void
    {
        $this->expectNotToPerformAssertions();

        // Create a real file with a class to populate replacedClasses
        $classmapDir = $this->testDir . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'package';
        mkdir($classmapDir, 0777, true);
        file_put_contents($classmapDir . DIRECTORY_SEPARATOR . 'MyClass.php', '<?php class MyClass {}');

        $package = new Package();
        $package->name = 'test/package';

        $autoloader = new Classmap();
        $autoloader->processConfig(['src/']);

        $replacer = new Replacer($this->config, new BuiltInSymbols());
        $replacer->replacePackageByAutoloader($package, $autoloader);

        // Now call replaceParentClassesInDirectory with a non-existent path
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->setReplacedClasses($replacer->getReplacedClasses());
        $parentReplacer->replaceParentClassesInDirectory($this->testDir . DIRECTORY_SEPARATOR . 'nonexistent');
    }

    /**
     * Regression test for #215: replaceParentPackage() must process all
     * autoloader combinations, not just the first one.
     *
     * @test
     */
    public function it_processes_all_autoloader_combinations_for_parent_replacement(): void
    {
        // Use relative dep/classmap directories (matching production config)
        $config = new Mozart();
        $config->setDepNamespace('Test\\Namespace\\');
        $config->setDepDirectory('deps' . DIRECTORY_SEPARATOR);
        $config->setClassmapDirectory('classmap' . DIRECTORY_SEPARATOR);
        $config->setClassmapPrefix('Test_');
        $config->setWorkingDir($this->testDir);
        $config->setOverrideAutoload(new stdClass());

        // Child package with BOTH PSR-4 and classmap autoloaders
        $child = new Package();
        $child->name = 'child/package';
        $childAutoload = new stdClass();
        $childPsr4 = new stdClass();
        $childPsr4->{'Child\\Ns\\'} = ['src/'];
        $childAutoload->{'psr-4'} = $childPsr4;
        $childAutoload->classmap = ['lib/'];
        $child->setAutoload($childAutoload);

        // Parent package with PSR-4 autoloader
        $parent = new Package();
        $parent->name = 'parent/package';
        $parentAutoload = new stdClass();
        $parentPsr4 = new stdClass();
        $parentPsr4->{'Parent\\Lib\\'} = ['src/'];
        $parentAutoload->{'psr-4'} = $parentPsr4;
        $parent->setAutoload($parentAutoload);

        // Create parent's namespace directory with a PHP file that references
        // both the child's namespace and a global class
        $parentDir = $this->testDir . DIRECTORY_SEPARATOR . 'deps' . DIRECTORY_SEPARATOR
                     . 'Parent' . DIRECTORY_SEPARATOR . 'Lib';
        mkdir($parentDir, 0777, true);

        $parentFile = $parentDir . DIRECTORY_SEPARATOR . 'ParentClass.php';
        file_put_contents($parentFile, <<<'PHP'
<?php

namespace Parent\Lib;

use Child\Ns\ChildClass;

class ParentClass
{
    public function foo(): ChildClass
    {
        $helper = new \OrigHelper();
        return new ChildClass();
    }
}
PHP);

        $replacer = new Replacer($config, new BuiltInSymbols());
        $parentReplacer = new ParentReplacer($config, $replacer);
        $parentReplacer->setReplacedClasses(['OrigHelper' => 'Test_OrigHelper']);

        $this->replaceParentPackageInWorkingDirectory($parentReplacer, $child, $parent);

        $result = file_get_contents($parentFile);

        // PSR-4 replacement applied: child namespace was prefixed
        $this->assertStringContainsString('use Test\Namespace\Child\Ns\ChildClass', $result);

        // Classmap replacement applied: global class was prefixed
        $this->assertStringContainsString('Test_OrigHelper', $result);
    }

    /**
     * Regression test for #325: parent files-autoloader entries that declare a
     * namespace are moved into dep_directory and must be updated there during
     * pass 2.
     *
     * @test
     */
    public function it_replaces_namespaced_parent_files_autoloader_targets_in_dep_directory(): void
    {
        $config = $this->createRelativeConfig();
        $child = $this->createClassmapPackage('child/package', ['lib/']);
        $parent = $this->createFilesPackage('parent/package', ['src/helpers.php']);

        $vendorParentDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'parent' . DIRECTORY_SEPARATOR . 'package'
            . DIRECTORY_SEPARATOR . 'src';
        mkdir($vendorParentDir, 0777, true);

        $contents = <<<'PHP'
<?php

namespace Parent\Lib;

function parent_helper(): void
{
    $helper = new \OrigHelper();
}
PHP;

        file_put_contents($vendorParentDir . DIRECTORY_SEPARATOR . 'helpers.php', $contents);

        $targetDir = $this->testDir . DIRECTORY_SEPARATOR . 'deps'
            . DIRECTORY_SEPARATOR . 'Parent' . DIRECTORY_SEPARATOR . 'Lib'
            . DIRECTORY_SEPARATOR . 'src';
        mkdir($targetDir, 0777, true);

        $targetFile = $targetDir . DIRECTORY_SEPARATOR . 'helpers.php';
        file_put_contents($targetFile, $contents);

        $replacer = new Replacer($config, new BuiltInSymbols());
        $parentReplacer = new ParentReplacer($config, $replacer);
        $parentReplacer->setReplacedClasses(['OrigHelper' => 'Test_OrigHelper']);

        $this->replaceParentPackageInWorkingDirectory($parentReplacer, $child, $parent);

        $result = file_get_contents($targetFile);

        $this->assertStringContainsString('Test_OrigHelper', $result);
        $this->assertStringNotContainsString('\OrigHelper', $result);
    }

    /**
     * @test
     */
    public function it_propagates_constant_and_function_replacements_to_parent_packages(): void
    {
        $config = $this->createRelativeConfig();
        $child = $this->createClassmapPackage('child/package', ['lib/']);
        $parent = $this->createPsr4Package('parent/package', 'Parent\\Lib\\', ['src/']);

        $parentDir = $this->testDir . DIRECTORY_SEPARATOR . 'deps'
            . DIRECTORY_SEPARATOR . 'Parent' . DIRECTORY_SEPARATOR . 'Lib';
        mkdir($parentDir, 0777, true);

        $parentFile = $parentDir . DIRECTORY_SEPARATOR . 'ParentClass.php';
        file_put_contents($parentFile, <<<'PHP'
<?php

namespace Parent\Lib;

class ParentClass
{
    public function boot(): void
    {
        legacy_helper();

        if (defined('LEGACY_FLAG')) {
            echo constant('LEGACY_FLAG');
        }
    }
}
PHP);

        $replacer = new Replacer($config, new BuiltInSymbols());
        $parentReplacer = new ParentReplacer($config, $replacer);
        $parentReplacer->setReplacedConstants(['LEGACY_FLAG' => 'TEST_LEGACY_FLAG']);
        $parentReplacer->setReplacedFunctions(['legacy_helper' => 'test_legacy_helper']);

        $this->replaceParentPackageInWorkingDirectory($parentReplacer, $child, $parent);

        $result = file_get_contents($parentFile);

        $this->assertStringContainsString('test_legacy_helper();', $result);
        $this->assertStringContainsString("defined('TEST_LEGACY_FLAG')", $result);
        $this->assertStringContainsString("constant('TEST_LEGACY_FLAG')", $result);
    }

}
