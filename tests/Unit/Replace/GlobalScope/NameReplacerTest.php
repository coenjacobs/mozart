<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace\GlobalScope;

use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Replace\GlobalScope\NameReplacer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the NameReplacer class (GlobalScope\NameReplacer).
 *
 * This tests the orchestrator class that uses NameVisitor to replace
 * class name references in PHP code.
 */
class NameReplacerTest extends TestCase
{
    // Basic functionality tests

    #[Test]
    public function it_replaces_class_references(): void
    {
        $code = '<?php function test(MyClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        $this->assertStringContainsString('Prefix_MyClass $var', $result);
    }

    #[Test]
    public function it_returns_empty_content_unchanged(): void
    {
        $classMap = ['MyClass' => 'Prefix_MyClass'];
        $replacer = new NameReplacer($classMap);

        $result = $replacer->replace('');

        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_returns_content_unchanged_when_classmap_empty(): void
    {
        $code = '<?php function test(MyClass $var) {}';
        $replacer = new NameReplacer([]);

        $result = $replacer->replace($code);

        $this->assertEquals($code, $result);
    }

    // Error handling tests

    #[Test]
    public function it_throws_exception_on_syntax_error(): void
    {
        $code = '<?php function test(MyClass $var { invalid';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);

        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessageMatches('/Failed to parse PHP code:/');
        $replacer->replace($code);
    }

    #[Test]
    public function it_exception_includes_error_details(): void
    {
        $code = '<?php class { }'; // Missing class name
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);

        try {
            $replacer->replace($code);
            $this->fail('Expected FileOperationException was not thrown');
        } catch (FileOperationException $e) {
            $this->assertStringContainsString('Failed to parse PHP code:', $e->getMessage());
            // The error message should include some detail about the syntax error
            $this->assertGreaterThan(strlen('Failed to parse PHP code:'), strlen($e->getMessage()));
        }
    }

    // Edge case tests

    #[Test]
    public function it_handles_multiple_classes_in_map(): void
    {
        $code = '<?php
class Consumer {
    public function test(ClassA $a, ClassB $b): ClassC {
        return new ClassC();
    }
}';
        $classMap = [
            'ClassA' => 'Prefix_ClassA',
            'ClassB' => 'Prefix_ClassB',
            'ClassC' => 'Prefix_ClassC',
        ];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        $this->assertStringContainsString('Prefix_ClassA $a', $result);
        $this->assertStringContainsString('Prefix_ClassB $b', $result);
        $this->assertStringContainsString('Prefix_ClassC', $result);
        $this->assertStringContainsString('new Prefix_ClassC()', $result);
    }

    #[Test]
    public function it_does_not_replace_partial_matches(): void
    {
        $code = '<?php function test(MyClassExtended $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        // MyClassExtended should NOT be changed to Prefix_MyClassExtended
        $this->assertStringContainsString('MyClassExtended $var', $result);
        $this->assertStringNotContainsString('Prefix_MyClass', $result);
    }

    #[Test]
    public function it_handles_classes_with_underscores(): void
    {
        $code = '<?php function test(My_Old_Class $var) {}';
        $classMap = ['My_Old_Class' => 'Prefix_My_Old_Class'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        $this->assertStringContainsString('Prefix_My_Old_Class $var', $result);
    }

    #[Test]
    public function it_handles_code_with_no_matching_classes(): void
    {
        $code = '<?php function test(SomeOtherClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        // Should not modify non-matching classes
        $this->assertStringContainsString('SomeOtherClass $var', $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }

    #[Test]
    public function it_replaces_class_in_various_contexts(): void
    {
        $code = '<?php
class TestClass {
    private MyClass $prop;

    public function test(?MyClass $param): MyClass {
        $obj = new MyClass();
        if ($obj instanceof MyClass) {
            return MyClass::create();
        }
        return $param ?? $obj;
    }
}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        // Property type
        $this->assertStringContainsString('private Prefix_MyClass $prop', $result);
        // Nullable parameter
        $this->assertStringContainsString('?Prefix_MyClass $param', $result);
        // Return type
        $this->assertStringContainsString('Prefix_MyClass', $result);
        // new expression
        $this->assertStringContainsString('new Prefix_MyClass()', $result);
        // instanceof
        $this->assertStringContainsString('instanceof Prefix_MyClass', $result);
        // static call
        $this->assertStringContainsString('Prefix_MyClass::create()', $result);
    }

    #[Test]
    public function it_does_not_replace_in_strings(): void
    {
        $code = '<?php
$className = "MyClass";
$fqcn = \'MyClass\';
';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        // String contents should NOT be modified
        $this->assertStringContainsString('"MyClass"', $result);
        // Note: AST printer may normalize quotes
        $this->assertStringNotContainsString('Prefix_MyClass', $result);
    }

    #[Test]
    public function it_does_not_replace_in_comments(): void
    {
        $code = '<?php
/**
 * This uses MyClass for processing
 */
class Consumer {
    // MyClass is the dependency
    /* MyClass comment */
    public function test() {}
}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        // Comments should NOT be modified (they contain MyClass text)
        // Note: AST printer preserves comments
        $this->assertStringNotContainsString('Prefix_MyClass', $result);
    }

    #[Test]
    public function it_does_not_modify_namespaced_references(): void
    {
        $code = '<?php function test(Some\\Namespace\\MyClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        // Namespaced references should NOT be modified
        $this->assertStringContainsString('Some\\Namespace\\MyClass', $result);
        $this->assertStringNotContainsString('Prefix_MyClass', $result);
    }

    #[Test]
    public function it_uses_symbol_specific_maps_when_names_collide(): void
    {
        $code = "<?php\nhelpers();\n\$defined = defined('HELPERS');";

        $replacer = new NameReplacer(
            ['Helpers' => 'Mozart_Helpers'],
            ['HELPERS' => 'MOZART_HELPERS'],
            ['helpers' => 'mozart_helpers']
        );

        $result = $replacer->replace($code);

        $this->assertStringContainsString('mozart_helpers();', $result);
        $this->assertStringContainsString("defined('MOZART_HELPERS')", $result);
        $this->assertStringNotContainsString('Mozart_Helpers();', $result);
        $this->assertStringNotContainsString("defined('Mozart_Helpers')", $result);
    }

    #[Test]
    public function it_does_not_fall_back_for_suffix_based_class_contexts(): void
    {
        $code = "<?php\n\$defined = defined('HELPERS::CONST_NAME');\n\$callable = is_callable('helpers::method');";

        $replacer = new NameReplacer(
            [],
            ['HELPERS' => 'MOZART_HELPERS'],
            ['helpers' => 'mozart_helpers']
        );

        $result = $replacer->replace($code);

        $this->assertStringContainsString("defined('HELPERS::CONST_NAME')", $result);
        $this->assertStringContainsString("is_callable('helpers::method')", $result);
        $this->assertStringNotContainsString("defined('MOZART_HELPERS::CONST_NAME')", $result);
        $this->assertStringNotContainsString("is_callable('mozart_helpers::method')", $result);
    }

    #[Test]
    public function it_handles_catch_blocks(): void
    {
        $code = '<?php
try {
    throw new MyException("error");
} catch (MyException $e) {
    echo $e->getMessage();
}';
        $classMap = ['MyException' => 'Prefix_MyException'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        $this->assertStringContainsString('new Prefix_MyException', $result);
        $this->assertStringContainsString('catch (Prefix_MyException $e)', $result);
    }

    #[Test]
    public function it_handles_trait_use_statements(): void
    {
        $code = '<?php class TestClass { use MyTrait; }';
        $classMap = ['MyTrait' => 'Prefix_MyTrait'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        $this->assertStringContainsString('use Prefix_MyTrait;', $result);
    }

    #[Test]
    public function it_handles_interface_implementation(): void
    {
        $code = '<?php class TestClass implements MyInterface {}';
        $classMap = ['MyInterface' => 'Prefix_MyInterface'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        $this->assertStringContainsString('implements Prefix_MyInterface', $result);
    }

    #[Test]
    public function it_handles_class_extending(): void
    {
        $code = '<?php class ChildClass extends ParentClass {}';
        $classMap = ['ParentClass' => 'Prefix_ParentClass'];

        $replacer = new NameReplacer($classMap);
        $result = $replacer->replace($code);

        $this->assertStringContainsString('extends Prefix_ParentClass', $result);
    }
}
