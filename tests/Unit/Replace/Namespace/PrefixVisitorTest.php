<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace\Namespace;

use CoenJacobs\Mozart\Replace\Namespace\PrefixVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PrefixVisitorTest extends TestCase
{
    protected const PREFIX = 'MyPlugin\\Dependencies';

    protected function processCode(string $code, array $targetNamespaces): string
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser();
        $visitor = new PrefixVisitor(self::PREFIX, $targetNamespaces);
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor($visitor);

        $ast = $parser->parse($code);
        $ast = $traverser->traverse($ast);

        $printer = new PrettyPrinter();
        return $printer->prettyPrintFile($ast);
    }

    #[Test]
    public function it_prefixes_namespace_declarations(): void
    {
        $code = '<?php namespace Invoker;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('namespace MyPlugin\\Dependencies\\Invoker;', $result);
    }

    #[Test]
    public function it_prefixes_nested_namespace_declarations(): void
    {
        $code = '<?php namespace Invoker\\ParameterResolver;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            'namespace MyPlugin\\Dependencies\\Invoker\\ParameterResolver;',
            $result
        );
    }

    #[Test]
    public function it_prefixes_use_statements(): void
    {
        $code = '<?php use Invoker\\Invoker;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use MyPlugin\\Dependencies\\Invoker\\Invoker;', $result);
    }

    #[Test]
    public function it_prefixes_use_statements_with_alias(): void
    {
        $code = '<?php use Invoker\\Invoker as DI;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use MyPlugin\\Dependencies\\Invoker\\Invoker as DI;', $result);
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespaces(): void
    {
        $code = '<?php namespace SomeOther;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('namespace SomeOther;', $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_does_not_double_prefix(): void
    {
        $code = '<?php namespace MyPlugin\\Dependencies\\Invoker;';
        $result = $this->processCode($code, ['Invoker']);

        // Should not add prefix again
        $this->assertStringContainsString('namespace MyPlugin\\Dependencies\\Invoker;', $result);
        $this->assertStringNotContainsString(
            'MyPlugin\\Dependencies\\MyPlugin\\Dependencies',
            $result
        );
    }

    #[Test]
    public function it_prefixes_fully_qualified_class_references(): void
    {
        $code = '<?php $x = new \\Invoker\\Invoker();';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('\\MyPlugin\\Dependencies\\Invoker\\Invoker', $result);
    }

    #[Test]
    public function it_prefixes_extends_clause(): void
    {
        $code = '<?php
namespace Invoker;
class Foo extends \\Invoker\\BaseClass {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('extends \\MyPlugin\\Dependencies\\Invoker\\BaseClass', $result);
    }

    #[Test]
    public function it_prefixes_implements_clause(): void
    {
        $code = '<?php
namespace Invoker;
class Foo implements \\Invoker\\SomeInterface {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('implements \\MyPlugin\\Dependencies\\Invoker\\SomeInterface', $result);
    }

    #[Test]
    public function it_prefixes_return_type_hint(): void
    {
        $code = '<?php
use Invoker\\Invoker;
class Foo {
    public function getInvoker(): \\Invoker\\Invoker {}
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(': \\MyPlugin\\Dependencies\\Invoker\\Invoker', $result);
    }

    #[Test]
    public function it_prefixes_parameter_type_hint(): void
    {
        $code = '<?php
class Foo {
    public function setInvoker(\\Invoker\\Invoker $invoker) {}
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('\\MyPlugin\\Dependencies\\Invoker\\Invoker $invoker', $result);
    }

    #[Test]
    public function it_prefixes_property_type_hint(): void
    {
        $code = '<?php
class Foo {
    private \\Invoker\\Invoker $invoker;
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('private \\MyPlugin\\Dependencies\\Invoker\\Invoker $invoker', $result);
    }

    #[Test]
    public function it_prefixes_instanceof_check(): void
    {
        $code = '<?php
$x = $obj instanceof \\Invoker\\Invoker;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('instanceof \\MyPlugin\\Dependencies\\Invoker\\Invoker', $result);
    }

    #[Test]
    public function it_prefixes_static_method_calls(): void
    {
        $code = '<?php
$x = \\Invoker\\Invoker::create();';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('\\MyPlugin\\Dependencies\\Invoker\\Invoker::create()', $result);
    }

    #[Test]
    public function it_prefixes_new_instantiation(): void
    {
        $code = '<?php
$x = new \\Invoker\\Invoker();';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('new \\MyPlugin\\Dependencies\\Invoker\\Invoker()', $result);
    }

    #[Test]
    public function it_does_not_modify_variable_names(): void
    {
        $code = '<?php
$invoker = "test";
$this->invoker = "test";';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('$invoker = "test"', $result);
        $this->assertStringContainsString('$this->invoker = "test"', $result);
        $this->assertStringNotContainsString('$MyPlugin', $result);
    }

    #[Test]
    public function it_does_not_modify_string_literals(): void
    {
        $code = '<?php
$x = "Invoker\\Invoker";
$y = \'Invoker\\Invoker\';';
        $result = $this->processCode($code, ['Invoker']);

        // String literals should remain unchanged
        $this->assertStringContainsString("'Invoker\\\\Invoker'", $result);
    }

    #[Test]
    public function it_handles_multiple_target_namespaces(): void
    {
        $code = '<?php
use Invoker\\Invoker;
use Psr\\Container\\ContainerInterface;';
        $result = $this->processCode($code, ['Invoker', 'Psr\\Container']);

        $this->assertStringContainsString('use MyPlugin\\Dependencies\\Invoker\\Invoker', $result);
        $this->assertStringContainsString('use MyPlugin\\Dependencies\\Psr\\Container\\ContainerInterface', $result);
    }

    #[Test]
    public function it_does_not_prefix_unrelated_use_statements(): void
    {
        $code = '<?php
use Invoker\\Invoker;
use Some\\Other\\Package;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use MyPlugin\\Dependencies\\Invoker\\Invoker', $result);
        $this->assertStringContainsString('use Some\\Other\\Package;', $result);
        $this->assertStringNotContainsString('MyPlugin\\Dependencies\\Some', $result);
    }

    #[Test]
    public function it_handles_catch_blocks(): void
    {
        $code = '<?php
try {
} catch (\\Invoker\\Exception $e) {
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('catch (\\MyPlugin\\Dependencies\\Invoker\\Exception $e)', $result);
    }

    #[Test]
    public function it_prefixes_function_exists_string_literal(): void
    {
        $code = '<?php
if (!function_exists(\'Invoker\\invoke\')) {
    function invoke() {}
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "function_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\invoke')",
            $result
        );
    }

    #[Test]
    public function it_prefixes_class_exists_string_literal(): void
    {
        $code = '<?php
if (class_exists(\'Invoker\\Invoker\')) {
    $x = new \\Invoker\\Invoker();
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "class_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker')",
            $result
        );
    }

    #[Test]
    public function it_prefixes_interface_exists_string_literal(): void
    {
        $code = '<?php
if (interface_exists(\'Invoker\\InvokerInterface\')) {
    // do something
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "interface_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\InvokerInterface')",
            $result
        );
    }

    #[Test]
    public function it_prefixes_trait_exists_string_literal(): void
    {
        $code = '<?php
if (trait_exists(\'Invoker\\InvokerTrait\')) {
    // do something
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "trait_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\InvokerTrait')",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_function_exists(): void
    {
        $code = '<?php
if (!function_exists(\'SomeOther\\func\')) {
    function func() {}
}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("function_exists('SomeOther\\\\func')", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_does_not_double_prefix_function_exists(): void
    {
        $code = '<?php
if (!function_exists(\'MyPlugin\\Dependencies\\Invoker\\func\')) {
    function func() {}
}';
        $result = $this->processCode($code, ['Invoker']);

        // Should not add prefix again
        $this->assertStringContainsString(
            "function_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\func')",
            $result
        );
        $this->assertStringNotContainsString(
            'MyPlugin\\\\Dependencies\\\\MyPlugin\\\\Dependencies',
            $result
        );
    }

    #[Test]
    public function it_handles_nested_namespace_in_function_exists(): void
    {
        $code = '<?php
if (!function_exists(\'DI\\value\')) {
    function value($value) { return $value; }
}';
        $result = $this->processCode($code, ['DI']);

        $this->assertStringContainsString(
            "function_exists('MyPlugin\\\\Dependencies\\\\DI\\\\value')",
            $result
        );
    }

    #[Test]
    public function it_prefixes_constant_string_literal(): void
    {
        $code = '<?php
$level = constant(\'Invoker\\Invoker::SOME_CONST\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "constant('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker::SOME_CONST')",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_constant(): void
    {
        $code = '<?php
$level = constant(\'SomeOther\\SomeClass::CONST_NAME\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("constant('SomeOther\\\\SomeClass::CONST_NAME')", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_does_not_double_prefix_constant(): void
    {
        $code = '<?php
$level = constant(\'MyPlugin\\Dependencies\\Invoker\\Invoker::SOME_CONST\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "constant('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker::SOME_CONST')",
            $result
        );
        $this->assertStringNotContainsString(
            'MyPlugin\\\\Dependencies\\\\MyPlugin\\\\Dependencies',
            $result
        );
    }

    #[Test]
    public function it_prefixes_constant_with_concatenation(): void
    {
        $code = '<?php
$level = constant(\'Invoker\\Invoker::\' . $constName);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "constant('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker::'",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_constant_concat(): void
    {
        $code = '<?php
$level = constant(\'SomeOther\\SomeClass::\' . $constName);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("constant('SomeOther\\\\SomeClass::'", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_defined_string_literal(): void
    {
        $code = '<?php
$check = defined(\'Invoker\\Invoker::SOME_CONST\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "defined('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker::SOME_CONST')",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_defined(): void
    {
        $code = '<?php
$check = defined(\'SomeOther\\SomeClass::CONST_NAME\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("defined('SomeOther\\\\SomeClass::CONST_NAME')", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_defined_with_concatenation(): void
    {
        $code = '<?php
$check = defined(\'Invoker\\Invoker::\' . $constName);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "defined('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker::'",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_defined_concat(): void
    {
        $code = '<?php
$check = defined(\'SomeOther\\SomeClass::\' . $constName);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("defined('SomeOther\\\\SomeClass::'", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_define_with_namespaced_constant(): void
    {
        $code = '<?php
define(\'Invoker\\MY_CONST\', 1);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "define('MyPlugin\\\\Dependencies\\\\Invoker\\\\MY_CONST'",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_define(): void
    {
        $code = '<?php
define(\'SomeOther\\MY_CONST\', 1);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("define('SomeOther\\\\MY_CONST'", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_does_not_double_prefix_define(): void
    {
        $code = '<?php
define(\'MyPlugin\\Dependencies\\Invoker\\MY_CONST\', 1);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringNotContainsString(
            'MyPlugin\\\\Dependencies\\\\MyPlugin\\\\Dependencies',
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_simple_define_constant(): void
    {
        $code = '<?php
define(\'MY_CONST\', 1);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("define('MY_CONST'", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_enum_exists_string_literal(): void
    {
        $code = '<?php
if (enum_exists(\'Invoker\\Status\')) {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "enum_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\Status')",
            $result
        );
    }

    #[Test]
    public function it_prefixes_method_exists_string_literal(): void
    {
        $code = '<?php
if (method_exists(\'Invoker\\Invoker\', \'someMethod\')) {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "method_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker'",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_method_exists(): void
    {
        $code = '<?php
if (method_exists(\'SomeOther\\SomeClass\', \'someMethod\')) {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("method_exists('SomeOther\\\\SomeClass'", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_property_exists_string_literal(): void
    {
        $code = '<?php
if (property_exists(\'Invoker\\Invoker\', \'someProp\')) {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "property_exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker'",
            $result
        );
    }

    #[Test]
    public function it_prefixes_is_a_string_literal(): void
    {
        $code = '<?php
$check = is_a($obj, \'Invoker\\Invoker\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "is_a(\$obj, 'MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker')",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_is_a(): void
    {
        $code = '<?php
$check = is_a($obj, \'SomeOther\\SomeClass\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("is_a(\$obj, 'SomeOther\\\\SomeClass')", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_is_subclass_of_string_literal(): void
    {
        $code = '<?php
$check = is_subclass_of($obj, \'Invoker\\Invoker\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "is_subclass_of(\$obj, 'MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker')",
            $result
        );
    }

    #[Test]
    public function it_prefixes_is_callable_string_literal(): void
    {
        $code = '<?php
if (is_callable(\'Invoker\\invoke\')) {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "is_callable('MyPlugin\\\\Dependencies\\\\Invoker\\\\invoke')",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_is_callable(): void
    {
        $code = '<?php
if (is_callable(\'SomeOther\\func\')) {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("is_callable('SomeOther\\\\func')", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_is_callable_with_double_colon(): void
    {
        $code = '<?php
$check = is_callable(\'Invoker\\Invoker::someMethod\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "is_callable('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker::someMethod')",
            $result
        );
    }

    #[Test]
    public function it_prefixes_is_callable_with_concatenation(): void
    {
        $code = '<?php
$check = is_callable(\'Invoker\\Invoker::\' . $method);';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "is_callable('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker::'",
            $result
        );
    }

    #[Test]
    public function it_prefixes_use_function_statements(): void
    {
        $code = '<?php use function Invoker\\invoke;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use function MyPlugin\\Dependencies\\Invoker\\invoke;', $result);
    }

    #[Test]
    public function it_prefixes_use_const_statements(): void
    {
        $code = '<?php use const Invoker\\SOME_CONSTANT;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use const MyPlugin\\Dependencies\\Invoker\\SOME_CONSTANT;', $result);
    }

    #[Test]
    public function it_does_not_prefix_non_target_use_function(): void
    {
        $code = '<?php use function SomeOther\\helper;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use function SomeOther\\helper;', $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_does_not_prefix_non_target_use_const(): void
    {
        $code = '<?php use const SomeOther\\SOME_CONSTANT;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use const SomeOther\\SOME_CONSTANT;', $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_use_function_with_alias(): void
    {
        $code = '<?php use function Invoker\\invoke as di_invoke;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use function MyPlugin\\Dependencies\\Invoker\\invoke as di_invoke;', $result);
    }

    #[Test]
    public function it_prefixes_use_const_with_alias(): void
    {
        $code = '<?php use const Invoker\\SOME_CONSTANT as MY_CONST;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use const MyPlugin\\Dependencies\\Invoker\\SOME_CONSTANT as MY_CONST;', $result);
    }

    #[Test]
    public function it_prefixes_class_alias_first_argument(): void
    {
        $code = '<?php
class_alias(\'Invoker\\Invoker\', \'Legacy_Invoker\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "class_alias('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker'",
            $result
        );
    }

    #[Test]
    public function it_does_not_prefix_non_target_namespace_in_class_alias(): void
    {
        $code = '<?php
class_alias(\'SomeOther\\Client\', \'Legacy_Client\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString("class_alias('SomeOther\\\\Client'", $result);
        $this->assertStringNotContainsString('MyPlugin', $result);
    }

    #[Test]
    public function it_prefixes_both_class_alias_arguments_when_both_are_namespaced(): void
    {
        $code = '<?php
class_alias(\'Invoker\\Invoker\', \'Invoker\\LegacyInvoker\');';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "class_alias('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker', 'MyPlugin\\\\Dependencies\\\\Invoker\\\\LegacyInvoker')",
            $result
        );
    }

    // --- Case-insensitive namespace matching tests ---

    #[Test]
    public function it_prefixes_namespace_with_different_casing(): void
    {
        $code = '<?php namespace invoker;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('namespace MyPlugin\\Dependencies\\invoker;', $result);
    }

    #[Test]
    public function it_prefixes_use_statement_with_different_casing(): void
    {
        $code = '<?php use INVOKER\\Invoker;';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString('use MyPlugin\\Dependencies\\INVOKER\\Invoker;', $result);
    }

    #[Test]
    public function it_recognizes_class_exists_with_mixed_case_function_name(): void
    {
        $code = '<?php
if (Class_Exists(\'Invoker\\Invoker\')) {}';
        $result = $this->processCode($code, ['Invoker']);

        $this->assertStringContainsString(
            "Class_Exists('MyPlugin\\\\Dependencies\\\\Invoker\\\\Invoker')",
            $result
        );
    }
}
