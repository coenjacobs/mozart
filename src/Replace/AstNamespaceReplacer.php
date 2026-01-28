<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * AST-based namespace replacer that properly handles PHP syntax.
 *
 * This class uses PHP-Parser to correctly identify and replace namespace
 * references, avoiding the issues with regex-based replacement on constructs
 * like nullable type hints (?ClassName).
 */
class AstNamespaceReplacer extends BaseReplacer
{
    /**
     * The prefix to add to existing namespaces, for example: "My\Mozart\Prefix"
     */
    public string $depNamespace = '';

    /**
     * Replace namespace references in the given PHP code.
     */
    public function replace(string $contents): string
    {
        if (empty($contents)) {
            return $contents;
        }

        $autoloader = $this->autoloader;
        if (!$autoloader instanceof NamespaceAutoloader) {
            throw new FileOperationException('AstNamespaceReplacer requires a NamespaceAutoloader.');
        }

        $searchNamespace = $autoloader->getSearchNamespace();
        if (empty($searchNamespace)) {
            return $contents;
        }

        $ast = $this->parseCode($contents);
        if ($ast === null) {
            // Parsing failed, fall back to original (unmodified) content
            return $contents;
        }

        $modifiedAst = $this->traverseAndModify($ast, $searchNamespace);

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
            return $parser->parse($contents);
        } catch (\PhpParser\Error $exception) {
            unset($exception);
            return null;
        }
    }

    /**
     * Traverse the AST and apply modifications.
     *
     * @param array<\PhpParser\Node> $ast             The AST to traverse
     * @param string                 $searchNamespace The namespace to search for
     * @return array<\PhpParser\Node> The modified AST
     */
    protected function traverseAndModify(array $ast, string $searchNamespace): array
    {
        $traverser = new NodeTraverser();

        // Add parent connecting visitor first (required for context detection)
        $traverser->addVisitor(new ParentConnectingVisitor());

        // Add our class name visitor with the target namespace
        $visitor = new ClassNameVisitor($this->depNamespace, [$searchNamespace]);
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
}
