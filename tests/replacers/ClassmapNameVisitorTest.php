<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Replace\ClassmapNameVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ClassmapNameVisitorTest extends TestCase
{
    /**
     * Helper to process PHP code through the ClassmapNameVisitor.
     *
     * @param string $code The PHP code to process (without <?php tag)
     * @param array<string,string> $classMap Map of original => prefixed class names
     * @return string The processed PHP code (without <?php tag)
     */
    protected function processCode(string $code, array $classMap): string
    {
        $fullCode = "<?php\n" . $code;

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($fullCode);

        if ($ast === null) {
            return $code;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor(new ClassmapNameVisitor($classMap));

        $modifiedAst = $traverser->traverse($ast);

        $printer = new PrettyPrinter();
        $result = $printer->prettyPrintFile($modifiedAst);

        // Remove the <?php tag and normalize
        $result = preg_replace('/^<\?php\s*/', '', $result);
        return trim($result);
    }

    /** @test */
    #[Test]
    public function it_replaces_simple_class_name_in_type_hint(): void
    {
        $code = 'function test(MyClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('Prefix_MyClass $var', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_nullable_type_hint(): void
    {
        $code = 'function test(?MyClass $var) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('?Prefix_MyClass $var', $result);
    }

    /** @test */
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

    /** @test */
    #[Test]
    public function it_replaces_class_name_in_extends(): void
    {
        $code = 'class ChildClass extends ParentClass {}';
        $classMap = ['ParentClass' => 'Prefix_ParentClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('extends Prefix_ParentClass', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_class_name_in_implements(): void
    {
        $code = 'class MyClass implements MyInterface {}';
        $classMap = ['MyInterface' => 'Prefix_MyInterface'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('implements Prefix_MyInterface', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_class_name_in_new_expression(): void
    {
        $code = '$obj = new MyClass();';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('new Prefix_MyClass()', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_class_name_in_instanceof(): void
    {
        $code = 'if ($obj instanceof MyClass) {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('instanceof Prefix_MyClass', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_class_name_in_catch_block(): void
    {
        $code = 'try {} catch (MyException $e) {}';
        $classMap = ['MyException' => 'Prefix_MyException'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('catch (Prefix_MyException $e)', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_class_name_in_return_type(): void
    {
        $code = 'function test(): MyClass {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString(': Prefix_MyClass', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_nullable_return_type(): void
    {
        $code = 'function test(): ?MyClass {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString(': ?Prefix_MyClass', $result);
    }

    /** @test */
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

    /** @test */
    #[Test]
    public function it_does_not_modify_class_declaration_name(): void
    {
        // The ClassmapNameVisitor should not modify the class declaration itself
        // That's handled by ClassmapReplacer
        $code = 'class MyClass {}';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        // The class declaration name should stay as-is (ClassmapReplacer handles this)
        $this->assertStringContainsString('class MyClass', $result);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
    #[Test]
    public function it_replaces_static_method_calls(): void
    {
        $code = 'MyClass::staticMethod();';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('Prefix_MyClass::staticMethod()', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_class_constant_access(): void
    {
        $code = '$value = MyClass::CONSTANT;';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('Prefix_MyClass::CONSTANT', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_class_name_in_property_type(): void
    {
        $code = 'class Test { private MyClass $prop; }';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('private Prefix_MyClass $prop', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_nullable_property_type(): void
    {
        $code = 'class Test { private ?MyClass $prop; }';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('private ?Prefix_MyClass $prop', $result);
    }

    /** @test */
    #[Test]
    public function it_does_not_replace_in_use_statements(): void
    {
        // Use statements are namespaced code, handled by ClassNameVisitor
        $code = 'use MyClass;';
        $classMap = ['MyClass' => 'Prefix_MyClass'];

        $result = $this->processCode($code, $classMap);

        // Use statements should remain unchanged by ClassmapNameVisitor
        $this->assertStringContainsString('use MyClass;', $result);
    }

    /** @test */
    #[Test]
    public function it_replaces_trait_name_in_use_inside_class(): void
    {
        $code = 'class Test { use MyTrait; }';
        $classMap = ['MyTrait' => 'Prefix_MyTrait'];

        $result = $this->processCode($code, $classMap);

        $this->assertStringContainsString('use Prefix_MyTrait;', $result);
    }
}
