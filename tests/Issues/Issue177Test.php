<?php

declare(strict_types=1);

use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\Replace\AstNamespaceReplacer;
use CoenJacobs\Mozart\Replace\ClassNameVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression tests for Issue #177: Mozart incorrectly adds namespace prefix to PHP type hints.
 *
 * @see https://github.com/coenjacobs/mozart/issues/177
 *
 * The bug: When processing code like:
 *   private ?Invoker $invoker = null;
 *
 * It became:
 *   private ?MyPrefix\Invoker $invoker = null;
 *
 * This was because the regex-based replacement matched ? as a word boundary,
 * treating "?Invoker" as a valid match and prefixing only "Invoker".
 */
class Issue177Test extends TestCase
{
    protected const PREFIX = 'MyPlugin\\Dependencies';

    /**
     * Process PHP code using the AST-based visitor directly.
     */
    protected function processWithVisitor(string $code, array $targetNamespaces): string
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser();
        $visitor = new ClassNameVisitor(self::PREFIX, $targetNamespaces);
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor($visitor);

        $ast = $parser->parse($code);
        $ast = $traverser->traverse($ast);

        $printer = new PrettyPrinter();
        return $printer->prettyPrintFile($ast);
    }

    /**
     * Process PHP code using the AstNamespaceReplacer.
     */
    protected function processWithReplacer(string $code, string $namespace): string
    {
        $autoloader = new Psr4();
        $autoloader->setNamespace($namespace);

        $replacer = new AstNamespaceReplacer(self::PREFIX . '\\');
        $replacer->setAutoloader($autoloader);

        return $replacer->replace($code);
    }

    #[Test]
    public function it_correctly_handles_nullable_property_type_hint(): void
    {
        $code = '<?php
namespace Invoker;
class InvokerContainer {
    private ?Invoker $invoker = null;
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // The namespace declaration should be prefixed
        $this->assertStringContainsString('namespace MyPlugin\\Dependencies\\Invoker;', $result);

        // The property type hint should NOT be double-prefixed
        // It should remain as just "?Invoker" because it refers to the local class
        $this->assertStringNotContainsString('?MyPlugin\\Dependencies\\Invoker', $result);
    }

    #[Test]
    public function it_correctly_handles_nullable_parameter_type_hint(): void
    {
        $code = '<?php
namespace Invoker;
class Container {
    public function setInvoker(?Invoker $invoker): void {}
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // The parameter type hint should NOT be double-prefixed when referring to local class
        $this->assertStringNotContainsString('?MyPlugin\\Dependencies\\Invoker $invoker', $result);
    }

    #[Test]
    public function it_correctly_handles_nullable_return_type_hint(): void
    {
        $code = '<?php
namespace Invoker;
class Container {
    public function getInvoker(): ?Invoker {}
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // The return type hint should NOT be double-prefixed when referring to local class
        $this->assertStringNotContainsString(': ?MyPlugin\\Dependencies\\Invoker', $result);
    }

    #[Test]
    public function it_correctly_handles_fully_qualified_nullable_type_hint(): void
    {
        $code = '<?php
class Container {
    private ?\\Invoker\\Invoker $invoker = null;
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // Fully qualified nullable type hints SHOULD be prefixed
        $this->assertStringContainsString('?\\MyPlugin\\Dependencies\\Invoker\\Invoker $invoker', $result);
    }

    #[Test]
    public function it_does_not_modify_variable_names_matching_namespace(): void
    {
        $code = '<?php
namespace Invoker;
class Container {
    private $invoker;
    public function test() {
        $invoker = new Invoker();
        $this->invoker = $invoker;
    }
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // Variable names should NOT be modified
        $this->assertStringContainsString('$invoker', $result);
        $this->assertStringNotContainsString('$MyPlugin', $result);
        $this->assertStringContainsString('$this->invoker', $result);
    }

    #[Test]
    public function it_does_not_modify_property_access(): void
    {
        $code = '<?php
namespace Invoker;
class Container {
    private $invoker;
    public function test() {
        return $this->invoker;
    }
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // Property access should NOT be modified
        $this->assertStringContainsString('$this->invoker', $result);
    }

    #[Test]
    public function it_correctly_handles_instanceof_checks(): void
    {
        $code = '<?php
$x = $obj instanceof \\Invoker\\Invoker;';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // instanceof checks SHOULD be prefixed
        $this->assertStringContainsString('instanceof \\MyPlugin\\Dependencies\\Invoker\\Invoker', $result);
    }

    #[Test]
    public function it_correctly_handles_new_instantiation(): void
    {
        $code = '<?php
$x = new \\Invoker\\Invoker();';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // new instantiation SHOULD be prefixed
        $this->assertStringContainsString('new \\MyPlugin\\Dependencies\\Invoker\\Invoker()', $result);
    }

    #[Test]
    public function it_correctly_handles_static_method_calls(): void
    {
        $code = '<?php
$x = \\Invoker\\Invoker::create();';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // Static calls SHOULD be prefixed
        $this->assertStringContainsString('\\MyPlugin\\Dependencies\\Invoker\\Invoker::create()', $result);
    }

    #[Test]
    public function it_correctly_handles_union_types(): void
    {
        $code = '<?php
class Container {
    public function process(\\Invoker\\Invoker|null $invoker): \\Invoker\\Invoker|string {}
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // Union types SHOULD have their class components prefixed
        $this->assertStringContainsString('\\MyPlugin\\Dependencies\\Invoker\\Invoker|null', $result);
        $this->assertStringContainsString('\\MyPlugin\\Dependencies\\Invoker\\Invoker|string', $result);
    }

    #[Test]
    public function it_correctly_handles_intersection_types(): void
    {
        $code = '<?php
class Container {
    public function process(\\Invoker\\InvokerInterface&\\Invoker\\Configurable $invoker) {}
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // Intersection types SHOULD have their class components prefixed
        $this->assertStringContainsString(
            '\\MyPlugin\\Dependencies\\Invoker\\InvokerInterface&\\MyPlugin\\Dependencies\\Invoker\\Configurable',
            $result
        );
    }

    #[Test]
    public function it_correctly_handles_use_statement_aliases(): void
    {
        $code = '<?php
use Invoker\\Invoker;

class Container {
    private ?Invoker $invoker = null;

    public function __construct(Invoker $invoker) {
        $this->invoker = $invoker;
    }
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // The use statement SHOULD be prefixed
        $this->assertStringContainsString('use MyPlugin\\Dependencies\\Invoker\\Invoker;', $result);

        // The local references to Invoker (via alias) should NOT be prefixed again
        // because they resolve through the prefixed use statement
        $this->assertStringNotContainsString('private ?MyPlugin\\Dependencies\\Invoker', $result);
    }

    #[Test]
    public function it_works_with_ast_namespace_replacer(): void
    {
        $code = '<?php
namespace Invoker;

use Psr\\Container\\ContainerInterface;

class InvokerContainer {
    private ?Invoker $invoker = null;
    private ?ContainerInterface $container = null;
}';

        $result = $this->processWithReplacer($code, 'Invoker');

        // The namespace declaration should be prefixed
        $this->assertStringContainsString('namespace MyPlugin\\Dependencies\\Invoker;', $result);

        // The use statement for Psr\Container should NOT be prefixed (not in target namespace)
        $this->assertStringContainsString('use Psr\\Container\\ContainerInterface;', $result);
    }

    #[Test]
    public function it_correctly_handles_extends_clause(): void
    {
        $code = '<?php
namespace Invoker;
class ChildInvoker extends \\Invoker\\BaseInvoker {}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // The extends clause SHOULD be prefixed for fully qualified names
        $this->assertStringContainsString('extends \\MyPlugin\\Dependencies\\Invoker\\BaseInvoker', $result);
    }

    #[Test]
    public function it_correctly_handles_implements_clause(): void
    {
        $code = '<?php
namespace Invoker;
class ConcreteInvoker implements \\Invoker\\InvokerInterface {}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // The implements clause SHOULD be prefixed for fully qualified names
        $this->assertStringContainsString(
            'implements \\MyPlugin\\Dependencies\\Invoker\\InvokerInterface',
            $result
        );
    }

    #[Test]
    public function it_correctly_handles_catch_blocks(): void
    {
        $code = '<?php
try {
} catch (\\Invoker\\InvokerException $e) {
}';

        $result = $this->processWithVisitor($code, ['Invoker']);

        // Catch blocks SHOULD have class names prefixed
        $this->assertStringContainsString('catch (\\MyPlugin\\Dependencies\\Invoker\\InvokerException $e)', $result);
    }
}
