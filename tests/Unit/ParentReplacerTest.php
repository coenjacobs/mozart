<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit;

use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\ParentReplacer;
use CoenJacobs\Mozart\Replacer;
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

    /** @test */
    public function it_handles_empty_directory_for_parent_classes(): void
    {
        $replacer = new Replacer($this->config);
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->replaceParentClassesInDirectory($this->testDir . DIRECTORY_SEPARATOR . 'empty_dir');

        // Should complete without error
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_no_replaced_classes(): void
    {
        $replacer = new Replacer($this->config);
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->replaceParentClassesInDirectory($this->testDir);

        // Should complete without error when no classes to replace
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_packages_for_replace_parent_in_tree(): void
    {
        $replacer = new Replacer($this->config);
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->replaceParentInTree([]);

        // Should complete without error
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_parent_classes_in_nonexistent_directory(): void
    {
        // Create a real file with a class to populate replacedClasses
        $classmapDir = $this->testDir . DIRECTORY_SEPARATOR . 'classmap' . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'package';
        mkdir($classmapDir, 0777, true);
        file_put_contents($classmapDir . DIRECTORY_SEPARATOR . 'MyClass.php', '<?php class MyClass {}');

        $package = new Package();
        $package->name = 'test/package';

        $autoloader = new Classmap();
        $autoloader->processConfig(['src/']);

        $replacer = new Replacer($this->config);
        $replacer->replacePackageByAutoloader($package, $autoloader);

        // Now call replaceParentClassesInDirectory with a non-existent path
        $parentReplacer = new ParentReplacer($this->config, $replacer);
        $parentReplacer->setReplacedClasses($replacer->getReplacedClasses());
        $parentReplacer->replaceParentClassesInDirectory($this->testDir . DIRECTORY_SEPARATOR . 'nonexistent');

        // Should complete without error
        $this->assertTrue(true);
    }
}
