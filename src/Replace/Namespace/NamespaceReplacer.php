<?php

namespace CoenJacobs\Mozart\Replace\Namespace;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Replace\AbstractAutoloadReplacer;
use CoenJacobs\Mozart\Replace\Support\AstUtils;

/**
 * AST-based namespace replacer that properly handles PHP syntax.
 *
 * This class uses PHP-Parser to correctly identify and replace namespace
 * references, avoiding the issues with regex-based replacement on constructs
 * like nullable type hints (?ClassName).
 */
class NamespaceReplacer extends AbstractAutoloadReplacer
{
    /**
     * The prefix to add to existing namespaces, for example: "My\Mozart\Prefix"
     */
    protected string $depNamespace;

    protected AstUtils $astUtils;

    protected ?string $searchNamespace = null;

    public function __construct(string $depNamespace = '')
    {
        $this->depNamespace = $depNamespace;
        $this->astUtils = new AstUtils();
    }

    public function getDepNamespace(): string
    {
        return $this->depNamespace;
    }

    public function setSearchNamespace(string $namespace): void
    {
        $this->searchNamespace = $namespace;
    }

    /**
     * Resolve the search namespace from the explicit property or the autoloader.
     */
    protected function resolveSearchNamespace(): string
    {
        if ($this->searchNamespace !== null) {
            return rtrim($this->searchNamespace, '\\');
        }

        $autoloader = $this->autoloader;
        if ($autoloader instanceof NamespaceAutoloader) {
            return $autoloader->getSearchNamespace();
        }

        throw new FileOperationException('NamespaceReplacer requires a search namespace or a NamespaceAutoloader.');
    }

    /**
     * Replace namespace references in the given PHP code.
     */
    public function replace(string $contents): string
    {
        if (empty($contents)) {
            return $contents;
        }

        $searchNamespace = $this->resolveSearchNamespace();
        if (empty($searchNamespace)) {
            return $contents;
        }

        $ast = $this->astUtils->parseCode($contents);
        if ($ast === null) {
            $error = $this->astUtils->getLastError() ?? 'Unknown parse error';
            throw new FileOperationException("Failed to parse PHP code: {$error}");
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
        $visitor = new PrefixVisitor($this->depNamespace, [$searchNamespace]);
        $traverser = $this->astUtils->createTraverser($visitor);

        return $traverser->traverse($ast);
    }
}
