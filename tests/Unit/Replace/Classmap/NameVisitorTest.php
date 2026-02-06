<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace\Classmap;

use CoenJacobs\Mozart\Replace\Classmap\NameVisitor;
use CoenJacobs\Mozart\Tests\Support\AstProcessingTestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NameVisitorTest extends TestCase
{
    use AstProcessingTestTrait;

    /**
     * Helper to process PHP code through the NameVisitor.
     *
     * @param string $code The PHP code to process (without <?php tag)
     * @param array<string,string> $classMap Map of original => prefixed class names
     * @return string The processed PHP code (without <?php tag)
     */
    protected function processCode(string $code, array $classMap): string
    {
        $visitor = new NameVisitor($classMap);
        return $this->processCodeWithVisitor($code, $visitor);
    }

    #[Test]
    public function it_replaces_simple_class_name_in_type_hint(): void
    {
        $code = 'function test(MyClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('Prefix_MyClass $var', $result);
    }

    #[Test]
    public function it_replaces_nullable_type_hint(): void
    {
        $code = 'function test(?MyClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('?Prefix_MyClass $var', $result);
    }

    #[Test]
    public function it_does_not_replace_class_name_in_string_literal(): void
    {
        $code = '$name = "MyClass";';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        // String literals should remain unchanged
        $this->assertStringContainsString('"MyClass"', $result);
        $this->assertStringNotContainsString('Prefix_MyClass', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_extends(): void
    {
        $code = 'class ChildClass extends ParentClass {}';
        $classMap = ['ParentClass' => 'Prefix_ParentClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('extends Prefix_ParentClass', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_implements(): void
    {
        $code = 'class MyClass implements MyInterface {}';
        $classMap = ['MyInterface' => 'Prefix_MyInterface'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('implements Prefix_MyInterface', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_new_expression(): void
    {
        $code = '$obj = new MyClass();';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('new Prefix_MyClass()', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_instanceof(): void
    {
        $code = 'if ($obj instanceof MyClass) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('instanceof Prefix_MyClass', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_catch_block(): void
    {
        $code = 'try {} catch (MyException $e) {}';
        $classMap = ['MyException' => 'Prefix_MyException'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('catch (Prefix_MyException $e)', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_return_type(): void
    {
        $code = 'function test(): MyClass {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString(': Prefix_MyClass', $result);
    }

    #[Test]
    public function it_replaces_nullable_return_type(): void
    {
        $code = 'function test(): ?MyClass {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString(': ?Prefix_MyClass', $result);
    }

    #[Test]
    public function it_does_not_replace_namespaced_class_references(): void
    {
        $code = 'function test(Some\\Namespace\\MyClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        // Namespaced references should not be modified
        $this->assertStringContainsString('Some\\Namespace\\MyClass', $result);
        $this->assertStringNotContainsString('Prefix_MyClass', $result);
    }

    #[Test]
    public function it_does_not_modify_class_declaration_name(): void
    {
        // The NameVisitor should not modify the class declaration itself
        // That's handled by ClassmapReplacer
        $code = 'class MyClass {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        // The class declaration name should stay as-is (ClassmapReplacer handles this)
        $this->assertStringContainsString('class MyClass', $result);
    }

    #[Test]
    public function it_replaces_multiple_class_references(): void
    {
        $code = 'function test(ClassA $a, ClassB $b): ClassC {}';
        $classMap = [
            'ClassA' => 'Prefix_ClassA',
            'ClassB' => 'Prefix_ClassB',
            'ClassC' => 'Prefix_ClassC',
        ];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('Prefix_ClassA $a', $result);
        $this->assertStringContainsString('Prefix_ClassB $b', $result);
        $this->assertStringContainsString(': Prefix_ClassC', $result);
    }

    #[Test]
    public function it_does_not_replace_class_name_not_in_map(): void
    {
        $code = 'function test(MyClass $var) {}';
        $classMap = ['OtherClass' => 'Prefix_OtherClass'];

        $result = $this->processCode($code, $classMap);

        // MyClass should remain unchanged since it's not in the map
        $this->assertStringContainsString('MyClass $var', $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }

    #[Test]
    public function it_replaces_static_method_calls(): void
    {
        $code = 'MyClass::staticMethod();';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('Prefix_MyClass::staticMethod()', $result);
    }

    #[Test]
    public function it_replaces_class_constant_access(): void
    {
        $code = '$value = MyClass::CONSTANT;';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('Prefix_MyClass::CONSTANT', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_property_type(): void
    {
        $code = 'class Test { private MyClass $prop; }';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('private Prefix_MyClass $prop', $result);
    }

    #[Test]
    public function it_replaces_nullable_property_type(): void
    {
        $code = 'class Test { private ?MyClass $prop; }';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('private ?Prefix_MyClass $prop', $result);
    }

    #[Test]
    public function it_does_not_replace_in_use_statements(): void
    {
        // Use statements are namespaced code, handled by PrefixVisitor
        $code = 'use MyClass;';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        // Use statements should remain unchanged by NameVisitor
        $this->assertStringContainsString('use MyClass;', $result);
    }

    #[Test]
    public function it_replaces_trait_name_in_use_inside_class(): void
    {
        $code = 'class Test { use MyTrait; }';
        $classMap = ['MyTrait' => 'Prefix_MyTrait'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('use Prefix_MyTrait;', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_class_exists(): void
    {
        $code = "if (class_exists('MyClass')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("class_exists('Prefix_MyClass')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_function_exists(): void
    {
        $code = "if (function_exists('myFunc')) {}";
        $classMap = ['myFunc' => 'Prefix_myFunc'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("function_exists('Prefix_myFunc')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_interface_exists(): void
    {
        $code = "if (interface_exists('MyInterface')) {}";
        $classMap = ['MyInterface' => 'Prefix_MyInterface'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("interface_exists('Prefix_MyInterface')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_trait_exists(): void
    {
        $code = "if (trait_exists('MyTrait')) {}";
        $classMap = ['MyTrait' => 'Prefix_MyTrait'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("trait_exists('Prefix_MyTrait')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_constant(): void
    {
        $code = "\$val = constant('MyClass::FOO');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("constant('Prefix_MyClass::FOO')", $result);
    }

    #[Test]
    public function it_does_not_replace_non_mapped_class_in_existence_check(): void
    {
        $code = "if (class_exists('OtherClass')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("class_exists('OtherClass')", $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }

    #[Test]
    public function it_does_not_replace_namespaced_string_in_existence_check(): void
    {
        $code = "if (class_exists('Some\\\\Ns\\\\MyClass')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringNotContainsString('Prefix_MyClass', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_constant_concatenation(): void
    {
        $code = "\$val = constant('MyClass::' . \$constName);";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("constant('Prefix_MyClass::'", $result);
    }

    #[Test]
    public function it_does_not_replace_non_mapped_class_in_constant_concatenation(): void
    {
        $code = "\$val = constant('OtherClass::' . \$constName);";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("constant('OtherClass::'", $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_defined(): void
    {
        $code = "\$check = defined('MyClass::FOO');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("defined('Prefix_MyClass::FOO')", $result);
    }

    #[Test]
    public function it_does_not_replace_non_mapped_class_in_defined(): void
    {
        $code = "\$check = defined('OtherClass::FOO');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("defined('OtherClass::FOO')", $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_defined_concatenation(): void
    {
        $code = "\$check = defined('MyClass::' . \$constName);";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("defined('Prefix_MyClass::'", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_enum_exists(): void
    {
        $code = "if (enum_exists('MyEnum')) {}";
        $classMap = ['MyEnum' => 'Prefix_MyEnum'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("enum_exists('Prefix_MyEnum')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_method_exists(): void
    {
        $code = "if (method_exists('MyClass', 'myMethod')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("method_exists('Prefix_MyClass'", $result);
    }

    #[Test]
    public function it_does_not_replace_non_mapped_class_in_method_exists(): void
    {
        $code = "if (method_exists('OtherClass', 'myMethod')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("method_exists('OtherClass'", $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_property_exists(): void
    {
        $code = "if (property_exists('MyClass', 'myProp')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("property_exists('Prefix_MyClass'", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_is_a(): void
    {
        $code = "if (is_a(\$obj, 'MyClass')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("is_a(\$obj, 'Prefix_MyClass')", $result);
    }

    #[Test]
    public function it_does_not_replace_non_mapped_class_in_is_a(): void
    {
        $code = "if (is_a(\$obj, 'OtherClass')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("is_a(\$obj, 'OtherClass')", $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }

    #[Test]
    public function it_replaces_class_name_in_is_subclass_of(): void
    {
        $code = "if (is_subclass_of(\$obj, 'MyClass')) {}";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("is_subclass_of(\$obj, 'Prefix_MyClass')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_is_callable(): void
    {
        $code = "if (is_callable('myFunc')) {}";
        $classMap = ['myFunc' => 'Prefix_myFunc'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("is_callable('Prefix_myFunc')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_is_callable_with_double_colon(): void
    {
        $code = "\$check = is_callable('MyClass::myMethod');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("is_callable('Prefix_MyClass::myMethod')", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_is_callable_concatenation(): void
    {
        $code = "\$check = is_callable('MyClass::' . \$method);";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("is_callable('Prefix_MyClass::'", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_class_alias_first_argument(): void
    {
        $code = "class_alias('MyClass', 'Legacy_MyClass');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("class_alias('Prefix_MyClass'", $result);
    }

    #[Test]
    public function it_replaces_class_name_in_class_alias_second_argument(): void
    {
        $code = "class_alias('SomeClass', 'MyClass');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("'Prefix_MyClass')", $result);
    }

    #[Test]
    public function it_replaces_only_mapped_arguments_in_class_alias(): void
    {
        $code = "class_alias('MyClass', 'OtherClass');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("class_alias('Prefix_MyClass', 'OtherClass')", $result);
    }

    #[Test]
    public function it_does_not_replace_non_mapped_class_in_class_alias(): void
    {
        $code = "class_alias('UnknownClass', 'AnotherClass');";
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString("class_alias('UnknownClass', 'AnotherClass')", $result);
        $this->assertStringNotContainsString('Prefix_', $result);
    }
}
