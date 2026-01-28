<?php

namespace CoenJacobs\Mozart\Replace;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Processes PHP files using AST-based replacement.
 *
 * This class coordinates parsing PHP files into an AST, applying namespace
 * and class name transformations, and converting back to PHP code. It falls
 * back to the original content if parsing fails.
 */
class PhpFileProcessor
{
    /**
     * The namespace prefix to add.
     */
    protected string $prefix;

    /**
     * Namespaces that should be prefixed.
     *
     * @var array<string>
     */
    protected array $targetNamespaces;

    /**
     * @param string        $prefix           The prefix to add (e.g., "MyPlugin\Dependencies")
     * @param array<string> $targetNamespaces Namespaces to prefix (e.g., ["Invoker", "Psr\Container"])
     */
    public function __construct(string $prefix, array $targetNamespaces)
    {
        $this->prefix = $prefix;
        $this->targetNamespaces = $targetNamespaces;
    }

    /**
     * Process a PHP file content and apply namespace prefixing.
     *
     * @param string $contents The PHP file contents to process
     * @return string The processed PHP file contents
     */
    public function process(string $contents): string
    {
        if (empty($contents)) {
            return $contents;
        }

        // Don't process if no target namespaces
        if (empty($this->targetNamespaces)) {
            return $contents;
        }

        $ast = $this->parseCode($contents);
        if ($ast === null) {
            // Parsing failed, return original content
            return $contents;
        }

        $modifiedAst = $this->traverseAndModify($ast);

        return $this->printCode($modifiedAst);
    }

    /**
     * Parse PHP code into an AST.
     *
     * @param string $contents The PHP code to parse
     * @return array<\PhpParser\Node>|null The AST nodes, or null if parsing failed
     */
    protected function parseCode(string $contents): ?array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($contents);
            return $ast;
        } catch (\PhpParser\Error $exception) {
            // Parsing failed, return null to trigger fallback
            unset($exception);
            return null;
        }
    }

    /**
     * Traverse the AST and apply modifications.
     *
     * @param array<\PhpParser\Node> $ast The AST to traverse
     * @return array<\PhpParser\Node> The modified AST
     */
    protected function traverseAndModify(array $ast): array
    {
        $traverser = new NodeTraverser();

        // Add parent connecting visitor first (required for context detection)
        $traverser->addVisitor(new ParentConnectingVisitor());

        // Add our class name visitor
        $visitor = new ClassNameVisitor($this->prefix, $this->targetNamespaces);
        $traverser->addVisitor($visitor);

        return $traverser->traverse($ast);
    }

    /**
     * Print the AST back to PHP code.
     *
     * @param array<\PhpParser\Node> $ast The AST to print
     * @return string The PHP code
     */
    protected function printCode(array $ast): string
    {
        $printer = new PrettyPrinter();
        return $printer->prettyPrintFile($ast);
    }

    /**
     * Add a target namespace to be prefixed.
     *
     * @param string $namespace The namespace to add
     */
    public function addTargetNamespace(string $namespace): void
    {
        $namespace = trim($namespace, '\\');
        if (!in_array($namespace, $this->targetNamespaces, true)) {
            $this->targetNamespaces[] = $namespace;
        }
    }

    /**
     * Get the target namespaces.
     *
     * @return array<string>
     */
    public function getTargetNamespaces(): array
    {
        return $this->targetNamespaces;
    }
}
