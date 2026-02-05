<?php

namespace CoenJacobs\Mozart\Replace;

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

    /**
     * Parse PHP code into an AST.
     *
     * @param string $contents The PHP code to parse
     * @return array<Node>|null The AST nodes, or null if parsing failed
     */
    public function parseCode(string $contents): ?array
    {
        if ($this->parser === null) {
            $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        }

        try {
            return $this->parser->parse($contents);
        } catch (\PhpParser\Error) {
            return null;
        }
    }

    /**
     * Create a NodeTraverser with ParentConnectingVisitor and the given visitors.
     *
     * @param NodeVisitorAbstract ...$visitors Visitors to add to the traverser
     * @return NodeTraverser The configured traverser
     */
    public function createTraverser(NodeVisitorAbstract ...$visitors): NodeTraverser
    {
        $traverser = new NodeTraverser();

        // Add parent connecting visitor first (required for context detection)
        $traverser->addVisitor(new ParentConnectingVisitor());

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
