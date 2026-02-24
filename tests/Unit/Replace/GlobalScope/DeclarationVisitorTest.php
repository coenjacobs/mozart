<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace\GlobalScope;

use CoenJacobs\Mozart\PhpSymbols\BuiltInSymbols;
use CoenJacobs\Mozart\Replace\GlobalScope\DeclarationVisitor;
use CoenJacobs\Mozart\Tests\Support\AstProcessingTestTrait;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DeclarationVisitorTest extends TestCase
{
    use AstProcessingTestTrait;

    /**
     * Helper to process PHP code through the DeclarationVisitor.
     *
     * @param string $code The PHP code to process (without <?php tag)
     * @param string $prefix The prefix to apply to declarations
     * @return array{code: string, visitor: DeclarationVisitor}
     */
    private BuiltInSymbols $builtInSymbols;

    protected function setUp(): void
    {
        $this->builtInSymbols = new BuiltInSymbols();
    }

    /**
     * Helper to process PHP code through the DeclarationVisitor.
     *
     * @param string $code The PHP code to process (without <?php tag)
     * @param string $prefix The prefix to apply to declarations
     * @return array{code: string, visitor: DeclarationVisitor}
     */
    protected function processCode(string $code, string $prefix): array
    {
        $visitor = new DeclarationVisitor($prefix, $this->builtInSymbols);
        $result = $this->processCodeWithVisitor($code, $visitor);
        return ['code' => $result, 'visitor' => $visitor];
    }

    #[Test]
    public function it_renames_class_declaration_in_global_namespace(): void
    {
        $code = 'class Hello_World {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('class Mozart_Hello_World', $result['code']);
    }

    #[Test]
    public function it_renames_interface_declaration_in_global_namespace(): void
    {
        $code = 'interface MyInterface {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('interface Mozart_MyInterface', $result['code']);
    }

    #[Test]
    public function it_renames_trait_declaration_in_global_namespace(): void
    {
        $code = 'trait MyTrait {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('trait Mozart_MyTrait', $result['code']);
    }

    #[Test]
    public function it_does_not_rename_declarations_inside_namespace_block(): void
    {
        $code = 'namespace MyNamespace; class Hello_World {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('class Hello_World', $result['code']);
        $this->assertStringNotContainsString('Mozart_Hello_World', $result['code']);
    }

    #[Test]
    public function it_does_not_rename_anonymous_classes(): void
    {
        $code = '$obj = new class { public function test() {} };';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('new class', $result['code']);
        $this->assertStringNotContainsString('Mozart_', $result['code']);
    }

    #[Test]
    public function it_tracks_all_replaced_classes(): void
    {
        $code = 'class ClassA {} interface InterfaceB {} trait TraitC {} enum EnumD { case One; }';

        $result = $this->processCode($code, 'Mozart_');

        $replaced = $result['visitor']->getReplacedClasses();
        $this->assertArrayHasKey('ClassA', $replaced);
        $this->assertArrayHasKey('InterfaceB', $replaced);
        $this->assertArrayHasKey('TraitC', $replaced);
        $this->assertArrayHasKey('EnumD', $replaced);
        $this->assertEquals('Mozart_ClassA', $replaced['ClassA']);
        $this->assertEquals('Mozart_InterfaceB', $replaced['InterfaceB']);
        $this->assertEquals('Mozart_TraitC', $replaced['TraitC']);
        $this->assertEquals('Mozart_EnumD', $replaced['EnumD']);
    }

    #[Test]
    public function it_handles_multiple_declarations_in_one_file(): void
    {
        $code = 'class ClassOne {} class ClassTwo {}';

        $result = $this->processCode($code, 'Prefix_');

        $this->assertStringContainsString('class Prefix_ClassOne', $result['code']);
        $this->assertStringContainsString('class Prefix_ClassTwo', $result['code']);
    }

    #[Test]
    public function it_handles_abstract_classes(): void
    {
        $code = 'abstract class AbstractClass {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('abstract class Mozart_AbstractClass', $result['code']);
    }

    #[Test]
    public function it_handles_class_extending_another_class(): void
    {
        $code = 'class ChildClass extends ParentClass {}';

        $result = $this->processCode($code, 'Mozart_');

        // Only the declaration should be renamed, not the extends reference
        $this->assertStringContainsString('class Mozart_ChildClass extends ParentClass', $result['code']);
    }

    #[Test]
    public function it_handles_class_implementing_interface(): void
    {
        $code = 'class MyClass implements MyInterface {}';

        $result = $this->processCode($code, 'Mozart_');

        // Only the declaration should be renamed, not the implements reference
        $this->assertStringContainsString('class Mozart_MyClass implements MyInterface', $result['code']);
    }

    #[Test]
    public function it_handles_explicit_global_namespace(): void
    {
        $code = 'namespace { class GlobalClass {} }';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('class Mozart_GlobalClass', $result['code']);
    }

    #[Test]
    public function it_handles_mixed_namespaced_and_global(): void
    {
        $code = <<<'PHP'
namespace MyNamespace {
    class NamespacedClass {}
}
namespace {
    class GlobalClass {}
}
PHP;

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('class NamespacedClass', $result['code']);
        $this->assertStringNotContainsString('Mozart_NamespacedClass', $result['code']);
        $this->assertStringContainsString('class Mozart_GlobalClass', $result['code']);
    }

    #[Test]
    public function it_handles_final_class(): void
    {
        $code = 'final class FinalClass {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('final class Mozart_FinalClass', $result['code']);
    }

    #[Test]
    public function it_handles_readonly_class(): void
    {
        $code = 'readonly class ReadonlyClass {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('readonly class Mozart_ReadonlyClass', $result['code']);
    }

    #[Test]
    public function it_renames_enum_declaration_in_global_namespace(): void
    {
        $code = 'enum Status { case Active; case Inactive; }';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('enum Mozart_Status', $result['code']);
    }

    #[Test]
    public function it_renames_backed_enum_declaration_in_global_namespace(): void
    {
        $code = 'enum Color: string { case Red = \'red\'; case Blue = \'blue\'; }';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('enum Mozart_Color', $result['code']);
    }

    #[Test]
    public function it_does_not_rename_enum_inside_namespace_block(): void
    {
        $code = 'namespace MyNamespace; enum Status { case Active; }';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('enum Status', $result['code']);
        $this->assertStringNotContainsString('Mozart_Status', $result['code']);
    }

    #[Test]
    public function it_tracks_replaced_enum_in_class_map(): void
    {
        $code = 'enum Status { case Active; }';

        $result = $this->processCode($code, 'Mozart_');

        $replaced = $result['visitor']->getReplacedClasses();
        $this->assertArrayHasKey('Status', $replaced);
        $this->assertEquals('Mozart_Status', $replaced['Status']);
    }

    #[Test]
    public function it_resets_replaced_classes_on_each_traversal(): void
    {
        $visitor = new DeclarationVisitor('Mozart_', $this->builtInSymbols);

        // First traversal
        $code1 = $this->wrapCode('class FirstClass {}');
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast1 = $parser->parse($code1);
        $traverser1 = new NodeTraverser();
        $traverser1->addVisitor(new ParentConnectingVisitor());
        $traverser1->addVisitor($visitor);
        $traverser1->traverse($ast1);

        $this->assertArrayHasKey('FirstClass', $visitor->getReplacedClasses());

        // Second traversal should reset
        $code2 = $this->wrapCode('class SecondClass {}');
        $ast2 = $parser->parse($code2);
        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor(new ParentConnectingVisitor());
        $traverser2->addVisitor($visitor);
        $traverser2->traverse($ast2);

        $this->assertArrayNotHasKey('FirstClass', $visitor->getReplacedClasses());
        $this->assertArrayHasKey('SecondClass', $visitor->getReplacedClasses());
    }

    #[Test]
    public function it_does_not_prefix_built_in_class(): void
    {
        $code = 'class ValueError {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('class ValueError', $result['code']);
        $this->assertStringNotContainsString('Mozart_ValueError', $result['code']);
        $this->assertArrayNotHasKey('ValueError', $result['visitor']->getReplacedClasses());
    }

    #[Test]
    public function it_does_not_prefix_built_in_interface(): void
    {
        $code = 'interface Stringable { public function __toString(): string; }';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('interface Stringable', $result['code']);
        $this->assertStringNotContainsString('Mozart_Stringable', $result['code']);
        $this->assertArrayNotHasKey('Stringable', $result['visitor']->getReplacedClasses());
    }

    #[Test]
    public function it_still_prefixes_non_built_in_class(): void
    {
        $code = 'class MyPolyfillClass {}';

        $result = $this->processCode($code, 'Mozart_');

        $this->assertStringContainsString('class Mozart_MyPolyfillClass', $result['code']);
        $this->assertArrayHasKey('MyPolyfillClass', $result['visitor']->getReplacedClasses());
    }

    // --- Constant prefixing tests ---

    /**
     * Helper to process PHP code with a constant prefix.
     *
     * @param string $code The PHP code to process (without <?php tag)
     * @param string $constantPrefix The prefix to apply to constants
     * @return array{code: string, visitor: DeclarationVisitor}
     */
    protected function processCodeWithConstantPrefix(string $code, string $constantPrefix): array
    {
        $visitor = new DeclarationVisitor('', $this->builtInSymbols, $constantPrefix);
        $result = $this->processCodeWithVisitor($code, $visitor);
        return ['code' => $result, 'visitor' => $visitor];
    }

    #[Test]
    public function it_renames_const_declaration_in_global_namespace(): void
    {
        $code = 'const MY_CONST = 1;';

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $this->assertStringContainsString('MOZART_MY_CONST', $result['code']);
        $this->assertStringNotContainsString("'MY_CONST'", $result['code']);
    }

    #[Test]
    public function it_renames_define_call_in_global_namespace(): void
    {
        $code = "define('MY_CONST', 'value');";

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $this->assertStringContainsString("define('MOZART_MY_CONST'", $result['code']);
    }

    #[Test]
    public function it_does_not_rename_const_inside_namespace_block(): void
    {
        $code = 'namespace MyNamespace; const MY_CONST = 1;';

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $this->assertStringNotContainsString('MOZART_MY_CONST', $result['code']);
    }

    #[Test]
    public function it_does_not_rename_define_inside_namespace_block(): void
    {
        $code = "namespace MyNamespace; define('MY_CONST', 'value');";

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $this->assertStringNotContainsString('MOZART_MY_CONST', $result['code']);
    }

    #[Test]
    public function it_does_not_rename_built_in_constant(): void
    {
        $code = 'const PHP_INT_MAX = 999;';

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $this->assertStringNotContainsString('MOZART_PHP_INT_MAX', $result['code']);
        $this->assertArrayNotHasKey('PHP_INT_MAX', $result['visitor']->getReplacedConstants());
    }

    #[Test]
    public function it_tracks_replaced_constants(): void
    {
        $code = 'const MY_CONST = 1;';

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $replaced = $result['visitor']->getReplacedConstants();
        $this->assertArrayHasKey('MY_CONST', $replaced);
        $this->assertEquals('MOZART_MY_CONST', $replaced['MY_CONST']);
    }

    #[Test]
    public function it_handles_multiple_const_in_one_statement(): void
    {
        $code = 'const A = 1, B = 2;';

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $this->assertStringContainsString('MOZART_A', $result['code']);
        $this->assertStringContainsString('MOZART_B', $result['code']);
        $replaced = $result['visitor']->getReplacedConstants();
        $this->assertArrayHasKey('A', $replaced);
        $this->assertArrayHasKey('B', $replaced);
    }

    #[Test]
    public function it_does_not_rename_constants_when_prefix_is_empty(): void
    {
        $code = 'const MY_CONST = 1;';

        $result = $this->processCodeWithConstantPrefix($code, '');

        $this->assertStringNotContainsString('MOZART_', $result['code']);
        $this->assertEmpty($result['visitor']->getReplacedConstants());
    }

    #[Test]
    public function it_handles_define_inside_if_block(): void
    {
        $code = "if (!defined('MY_CONST')) { define('MY_CONST', 'value'); }";

        $result = $this->processCodeWithConstantPrefix($code, 'MOZART_');

        $this->assertStringContainsString("define('MOZART_MY_CONST'", $result['code']);
    }
}
