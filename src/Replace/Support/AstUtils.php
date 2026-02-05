<?php

namespace CoenJacobs\Mozart\Replace\Support;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Utility class providing shared AST operations for PHP code processing.
 */
class AstUtils
{
    private ?\PhpParser\Parser $parser = null;

    private ?string $lastError = null;

    /**
     * Parse PHP code into an AST.
     *
     * @param string $contents The PHP code to parse
     * @return array<Node>|null The AST nodes, or null if parsing failed
     */
    public function parseCode(string $contents): ?array
    {
        $this->lastError = null;

        if ($this->parser === null) {
            $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        }

        try {
            return $this->parser->parse($contents);
        } catch (\PhpParser\Error $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Get the last parse error message.
     *
     * @return string|null The error message, or null if no error occurred
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Create a NodeTraverser with the given visitors.
     *
     * @param NodeVisitorAbstract ...$visitors Visitors to add to the traverser
     * @return NodeTraverser The configured traverser
     */
    public function createTraverser(NodeVisitorAbstract ...$visitors): NodeTraverser
    {
        return $this->createTraverserWithOptions(true, ...$visitors);
    }

    /**
     * Create a NodeTraverser without ParentConnectingVisitor.
     *
     * Use this for visitors that don't need parent node access to avoid
     * stack overflow on very large files with deeply nested code.
     *
     * @param NodeVisitorAbstract ...$visitors Visitors to add to the traverser
     * @return NodeTraverser The configured traverser
     */
    public function createSimpleTraverser(NodeVisitorAbstract ...$visitors): NodeTraverser
    {
        return $this->createTraverserWithOptions(false, ...$visitors);
    }

    /**
     * Create a NodeTraverser with configurable options.
     *
     * @param bool $connectParents Whether to add ParentConnectingVisitor
     * @param NodeVisitorAbstract ...$visitors Visitors to add to the traverser
     * @return NodeTraverser The configured traverser
     */
    protected function createTraverserWithOptions(bool $connectParents, NodeVisitorAbstract ...$visitors): NodeTraverser
    {
        $traverser = new NodeTraverser();

        // Add parent connecting visitor first (required for context detection in some visitors)
        if ($connectParents) {
            $traverser->addVisitor(new ParentConnectingVisitor());
        }

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        return $traverser;
    }

    /**
     * Print an AST back to PHP code.
     *
     * @param array<Node> $ast The AST to print
     * @return string The PHP code
     */
    public function printCode(array $ast): string
    {
        $printer = new PrettyPrinter();
        return $printer->prettyPrintFile($ast);
    }
}
