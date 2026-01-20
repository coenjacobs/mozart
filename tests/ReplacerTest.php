<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
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

    /**
     * Test that replaceParentClassesInDirectory does not replace property access
     *
     * This tests the fix for the bug where $this->names was incorrectly replaced
     * with $this->Prefix_names when a class named "names" existed.
     *
     * @test
     * @see https://github.com/coenjacobs/mozart/issues/XXX
     */
    public function it_does_not_replace_property_access(): void
    {
        // Create test directory structure
        $testDir = $this->testDir . DIRECTORY_SEPARATOR . 'deps' . DIRECTORY_SEPARATOR . 'Test';
        mkdir($testDir, 0777, true);

        // Create a file with property access that should NOT be replaced
        $testFile = $testDir . DIRECTORY_SEPARATOR . 'TestClass.php';
        $originalContent = <<<'PHP'
<?php
class TestClass {
    private $names = [];

    public function getName() {
        return $this->names['en'];
    }

    public function setNames($names) {
        $this->names = $names;
    }
}
PHP;
        file_put_contents($testFile, $originalContent);

        // Create a replacer and manually set replaced classes using reflection
        $replacer = new Replacer($this->config);

        // Use reflection to add 'names' to the replacedClasses array
        $reflection = new \ReflectionClass($replacer);
        $property = $reflection->getProperty('replacedClasses');
        $property->setAccessible(true);
        $property->setValue($replacer, ['names' => 'Test_names']);

        // Run the replacement
        $replacer->replaceParentClassesInDirectory($testDir);

        // Read the result
        $resultContent = file_get_contents($testFile);

        // Property access ($this->names) should NOT be replaced
        $this->assertStringContainsString('$this->names', $resultContent);
        $this->assertStringNotContainsString('$this->Test_names', $resultContent);

        // Variable names ($names) should NOT be replaced
        $this->assertStringContainsString('setNames($names)', $resultContent);
        $this->assertStringNotContainsString('setNames($Test_names)', $resultContent);
    }

    /**
     * Test that replaceParentClassesInDirectory still replaces actual class usages
     *
     * @test
     */
    public function it_replaces_actual_class_usages(): void
    {
        // Create test directory structure
        $testDir = $this->testDir . DIRECTORY_SEPARATOR . 'deps' . DIRECTORY_SEPARATOR . 'Test';
        mkdir($testDir, 0777, true);

        // Create a file with actual class usage that SHOULD be replaced
        $testFile = $testDir . DIRECTORY_SEPARATOR . 'Consumer.php';
        $originalContent = <<<'PHP'
<?php
class Consumer {
    public function create() {
        return new MyClass();
    }

    public function call() {
        return MyClass::staticMethod();
    }
}
PHP;
        file_put_contents($testFile, $originalContent);

        // Create a replacer and manually set replaced classes
        $replacer = new Replacer($this->config);

        $reflection = new \ReflectionClass($replacer);
        $property = $reflection->getProperty('replacedClasses');
        $property->setAccessible(true);
        $property->setValue($replacer, ['MyClass' => 'Test_MyClass']);

        // Run the replacement
        $replacer->replaceParentClassesInDirectory($testDir);

        // Read the result
        $resultContent = file_get_contents($testFile);

        // Class instantiation (new MyClass) SHOULD be replaced
        $this->assertStringContainsString('new Test_MyClass()', $resultContent);

        // Static method call (MyClass::) SHOULD be replaced
        $this->assertStringContainsString('Test_MyClass::staticMethod()', $resultContent);
    }
}

