<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Support;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Trait providing shared test helpers for AST processing tests.
 */
trait AstProcessingTestTrait
{
    /**
     * Process PHP code through a visitor and return the modified code.
     *
     * @param string $code The PHP code to process (without <?php tag)
     * @param NodeVisitorAbstract $visitor The visitor to apply
     * @return string The processed PHP code (without <?php tag, trimmed)
     */
    protected function processCodeWithVisitor(string $code, NodeVisitorAbstract $visitor): string
    {
        $fullCode = $this->wrapCode($code);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($fullCode);

        if ($ast === null) {
            return $code;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor($visitor);

        $modifiedAst = $traverser->traverse($ast);

        $printer = new PrettyPrinter();
        $result = $printer->prettyPrintFile($modifiedAst);

        return $this->extractCode($result);
    }

    /**
     * Helper to wrap code in a valid PHP file for AST parsing.
     */
    protected function wrapCode(string $code): string
    {
        return "<?php\n" . $code;
    }

    /**
     * Helper to extract the relevant code from AST output.
     *
     * Strips the opening PHP tag and trims whitespace.
     */
    protected function extractCode(string $output): string
    {
        $code = preg_replace('/^<\?php\s*/', '', $output);
        return trim($code);
    }
}
