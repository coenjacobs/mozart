<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace\Support;

use CoenJacobs\Mozart\Replace\Support\AstUtils;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AstUtilsTest extends TestCase
{
    protected AstUtils $astUtils;

    protected function setUp(): void
    {
        $this->astUtils = new AstUtils();
    }

    // Parsing tests

    #[Test]
    public function it_parses_valid_php_code(): void
    {
        $code = '<?php echo "Hello World";';
        $ast = $this->astUtils->parseCode($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    #[Test]
    public function it_returns_null_for_invalid_php(): void
    {
        $code = '<?php class Foo { invalid syntax here';
        $ast = $this->astUtils->parseCode($code);

        $this->assertNull($ast);
    }

    #[Test]
    public function it_captures_parse_error_message(): void
    {
        $code = '<?php class Foo { invalid syntax here';
        $this->astUtils->parseCode($code);
        $error = $this->astUtils->getLastError();

        $this->assertNotNull($error);
        $this->assertIsString($error);
        $this->assertNotEmpty($error);
    }

    #[Test]
    public function it_returns_null_error_after_successful_parse(): void
    {
        $code = '<?php echo "Hello";';
        $this->astUtils->parseCode($code);
        $error = $this->astUtils->getLastError();

        $this->assertNull($error);
    }

    #[Test]
    public function it_clears_previous_error_on_successful_parse(): void
    {
        // First, cause an error
        $invalidCode = '<?php class { }';
        $this->astUtils->parseCode($invalidCode);
        $this->assertNotNull($this->astUtils->getLastError());

        // Then, parse valid code
        $validCode = '<?php class Foo {}';
        $this->astUtils->parseCode($validCode);
        $this->assertNull($this->astUtils->getLastError());
    }

    #[Test]
    public function it_reuses_parser_instance(): void
    {
        // Parse multiple times to verify parser is reused (no exceptions)
        $codes = [
            '<?php class Foo {}',
            '<?php interface Bar {}',
            '<?php trait Baz {}',
        ];

        foreach ($codes as $code) {
            $ast = $this->astUtils->parseCode($code);
            $this->assertIsArray($ast);
        }
    }

    #[Test]
    public function it_parses_complex_php_code(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Services;

use App\Contracts\ServiceInterface;

class ComplexService implements ServiceInterface
{
    private array $data = [];

    public function __construct(
        private readonly string $name,
        private ?int $count = null
    ) {}

    public function process(array $items): array|false
    {
        return match(count($items)) {
            0 => false,
            default => array_map(fn($x) => $x * 2, $items),
        };
    }
}
PHP;
        $ast = $this->astUtils->parseCode($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    // Traverser tests

    #[Test]
    public function it_creates_traverser_with_parent_connecting_visitor(): void
    {
        $code = '<?php class Foo extends Bar {}';
        $ast = $this->astUtils->parseCode($code);

        $visited = false;
        $hasParent = false;

        $visitor = new class($visited, $hasParent) extends NodeVisitorAbstract {
            public function __construct(
                private bool &$visited,
                private bool &$hasParent
            ) {}

            public function leaveNode(Node $node): ?Node
            {
                if ($node instanceof Node\Name && $node->toString() === 'Bar') {
                    $this->visited = true;
                    $this->hasParent = $node->getAttribute('parent') !== null;
                }
                return null;
            }
        };

        $traverser = $this->astUtils->createTraverser($visitor);
        $traverser->traverse($ast);

        $this->assertTrue($visited, 'Visitor should have visited the Bar name node');
        $this->assertTrue($hasParent, 'Node should have parent attribute when using createTraverser');
    }

    #[Test]
    public function it_creates_simple_traverser_without_parent_connecting_visitor(): void
    {
        $code = '<?php class Foo extends Bar {}';
        $ast = $this->astUtils->parseCode($code);

        $visited = false;
        $hasParent = false;

        $visitor = new class($visited, $hasParent) extends NodeVisitorAbstract {
            public function __construct(
                private bool &$visited,
                private bool &$hasParent
            ) {}

            public function leaveNode(Node $node): ?Node
            {
                if ($node instanceof Node\Name && $node->toString() === 'Bar') {
                    $this->visited = true;
                    $this->hasParent = $node->getAttribute('parent') !== null;
                }
                return null;
            }
        };

        $traverser = $this->astUtils->createSimpleTraverser($visitor);
        $traverser->traverse($ast);

        $this->assertTrue($visited, 'Visitor should have visited the Bar name node');
        $this->assertFalse($hasParent, 'Node should NOT have parent attribute when using createSimpleTraverser');
    }

    #[Test]
    public function it_adds_all_provided_visitors_to_traverser(): void
    {
        $code = '<?php class Foo {}';
        $ast = $this->astUtils->parseCode($code);

        $visitor1Called = false;
        $visitor2Called = false;

        $visitor1 = new class($visitor1Called) extends NodeVisitorAbstract {
            public function __construct(private bool &$called) {}
            public function enterNode(Node $node): ?Node
            {
                $this->called = true;
                return null;
            }
        };

        $visitor2 = new class($visitor2Called) extends NodeVisitorAbstract {
            public function __construct(private bool &$called) {}
            public function enterNode(Node $node): ?Node
            {
                $this->called = true;
                return null;
            }
        };

        $traverser = $this->astUtils->createTraverser($visitor1, $visitor2);
        $traverser->traverse($ast);

        $this->assertTrue($visitor1Called, 'First visitor should have been called');
        $this->assertTrue($visitor2Called, 'Second visitor should have been called');
    }

    // Print tests

    #[Test]
    public function it_prints_ast_to_valid_php(): void
    {
        $code = '<?php class Foo {}';
        $ast = $this->astUtils->parseCode($code);
        $output = $this->astUtils->printCode($ast);

        $this->assertStringContainsString('<?php', $output);
        $this->assertStringContainsString('class Foo', $output);
    }

    #[Test]
    public function it_prints_empty_ast(): void
    {
        $code = '<?php';
        $ast = $this->astUtils->parseCode($code);
        $output = $this->astUtils->printCode($ast);

        $this->assertStringContainsString('<?php', $output);
    }

    #[Test]
    public function it_preserves_code_structure_after_round_trip(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

class Foo
{
    public function bar(): string
    {
        return 'baz';
    }
}
PHP;
        $ast = $this->astUtils->parseCode($code);
        $output = $this->astUtils->printCode($ast);

        $this->assertStringContainsString('namespace App;', $output);
        $this->assertStringContainsString('class Foo', $output);
        // AST printer may add space before colon for return types
        $this->assertStringContainsString('public function bar()', $output);
        $this->assertStringContainsString('string', $output);
        $this->assertStringContainsString("return 'baz';", $output);
    }

    #[Test]
    public function it_handles_modified_ast(): void
    {
        $code = '<?php class OldName {}';
        $ast = $this->astUtils->parseCode($code);

        // Modify the class name
        $visitor = new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                if ($node instanceof Node\Stmt\Class_ && $node->name !== null) {
                    $node->name = new Node\Identifier('NewName');
                    return $node;
                }
                return null;
            }
        };

        $traverser = $this->astUtils->createSimpleTraverser($visitor);
        $modifiedAst = $traverser->traverse($ast);
        $output = $this->astUtils->printCode($modifiedAst);

        $this->assertStringContainsString('class NewName', $output);
        $this->assertStringNotContainsString('class OldName', $output);
    }
}
