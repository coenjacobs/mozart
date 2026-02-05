<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace;

use CoenJacobs\Mozart\Replace\Classmap\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\Classmap\NameReplacer as ClassmapNameReplacer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests that verify AST processing works correctly for large files.
 */
class LargeFileHandlingTest extends TestCase
{
    /**
     * Generate a large PHP file content with the specified size.
     *
     * @param int $targetSize Target size in bytes
     * @param string $className Class name to use in the file
     * @return string The generated PHP content
     */
    protected function generateLargePhpFile(int $targetSize, string $className): string
    {
        $content = "<?php\n\nclass {$className} {\n";

        $methodTemplate = <<<'PHP'
    public function method%d(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
            'key4' => 'value4',
            'key5' => 'value5',
        ];
        foreach ($data as $key => $value) {
            echo $key . ': ' . $value . PHP_EOL;
        }
    }

PHP;

        $methodIndex = 0;
        while (strlen($content) < $targetSize - 10) {
            $content .= sprintf($methodTemplate, $methodIndex++);
        }

        $content .= "}\n";

        return $content;
    }

    #[Test]
    public function classmap_replacer_handles_file_larger_than_100kb(): void
    {
        // Generate a file larger than 100KB (the old threshold)
        $largeContent = $this->generateLargePhpFile(150000, 'LargeTestClass');

        $this->assertGreaterThan(100000, strlen($largeContent), 'Test file should be larger than 100KB');

        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($largeContent);

        // Verify the class was renamed
        $this->assertStringContainsString('class Mozart_LargeTestClass', $result);
        $this->assertStringNotContainsString('class LargeTestClass', $result);

        // Verify the replaced classes were tracked
        $replacedClasses = $replacer->getReplacedClasses();
        $this->assertArrayHasKey('LargeTestClass', $replacedClasses);
        $this->assertEquals('Mozart_LargeTestClass', $replacedClasses['LargeTestClass']);
    }

    #[Test]
    public function classmap_name_replacer_handles_file_larger_than_100kb(): void
    {
        // Generate a file larger than 100KB with class references
        $content = "<?php\n\nclass Consumer {\n";

        $methodTemplate = <<<'PHP'
    public function method%d(TargetClass $param): TargetClass
    {
        $obj = new TargetClass();
        if ($obj instanceof TargetClass) {
            return TargetClass::create();
        }
        return $param;
    }

PHP;

        $methodIndex = 0;
        while (strlen($content) < 150000) {
            $content .= sprintf($methodTemplate, $methodIndex++);
        }

        $content .= "}\n";

        $this->assertGreaterThan(100000, strlen($content), 'Test file should be larger than 100KB');

        $classMap = ['TargetClass' => 'Mozart_TargetClass'];
        $replacer = new ClassmapNameReplacer($classMap);
        $result = $replacer->replace($content);

        // Verify class references were replaced
        $this->assertStringContainsString('Mozart_TargetClass $param', $result);
        $this->assertStringContainsString('new Mozart_TargetClass()', $result);
        $this->assertStringContainsString('instanceof Mozart_TargetClass', $result);
        $this->assertStringContainsString('Mozart_TargetClass::create()', $result);
    }

    #[Test]
    public function classmap_replacer_handles_200kb_file(): void
    {
        $largeContent = $this->generateLargePhpFile(210000, 'VeryLargeClass');

        $this->assertGreaterThan(200000, strlen($largeContent), 'Test file should be larger than 200KB');

        $replacer = new ClassmapReplacer('Prefix_');
        $result = $replacer->replace($largeContent);

        $this->assertStringContainsString('class Prefix_VeryLargeClass', $result);
    }

    #[Test]
    public function classmap_replacer_maintains_file_structure(): void
    {
        $largeContent = $this->generateLargePhpFile(120000, 'StructureTestClass');

        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($largeContent);

        // Count methods in original and result
        $originalMethodCount = substr_count($largeContent, 'public function method');
        $resultMethodCount = substr_count($result, 'public function method');

        $this->assertEquals(
            $originalMethodCount,
            $resultMethodCount,
            'AST processing should preserve all methods'
        );
    }

    #[Test]
    public function classmap_name_replacer_handles_many_class_references(): void
    {
        // Generate content with many different class references
        $content = "<?php\n\nclass TestConsumer {\n";

        $classes = [];
        for ($i = 0; $i < 50; $i++) {
            $classes["Class{$i}"] = "Mozart_Class{$i}";
        }

        $methodIndex = 0;
        foreach ($classes as $original => $prefixed) {
            $content .= <<<PHP
    public function method{$methodIndex}({$original} \$param): {$original}
    {
        return new {$original}();
    }

PHP;
            $methodIndex++;
        }

        // Pad to exceed 100KB
        while (strlen($content) < 110000) {
            $content .= "    // Padding comment to increase file size\n";
        }

        $content .= "}\n";

        $replacer = new ClassmapNameReplacer($classes);
        $result = $replacer->replace($content);

        // Verify several class references were replaced
        $this->assertStringContainsString('Mozart_Class0 $param', $result);
        $this->assertStringContainsString('new Mozart_Class25()', $result);
        // Return type - AST may format with space before colon
        $this->assertStringContainsString('Mozart_Class49', $result);
    }
}
