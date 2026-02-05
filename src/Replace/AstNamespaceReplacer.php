<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Exceptions\FileOperationException;

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
    protected string $depNamespace;

    protected AstUtils $astUtils;

    public function __construct(string $depNamespace = '')
    {
        $this->depNamespace = $depNamespace;
        $this->astUtils = new AstUtils();
    }

    public function getDepNamespace(): string
    {
        return $this->depNamespace;
    }

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

        $ast = $this->astUtils->parseCode($contents);
        if ($ast === null) {
            // Parsing failed, fall back to original (unmodified) content
            return $contents;
        }

        $modifiedAst = $this->traverseAndModify($ast, $searchNamespace);

        return $this->astUtils->printCode($modifiedAst);
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
        $visitor = new ClassNameVisitor($this->depNamespace, [$searchNamespace]);
        $traverser = $this->astUtils->createTraverser($visitor);

        return $traverser->traverse($ast);
    }
}
