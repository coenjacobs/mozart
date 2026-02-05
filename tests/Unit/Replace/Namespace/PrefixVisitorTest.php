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
}
