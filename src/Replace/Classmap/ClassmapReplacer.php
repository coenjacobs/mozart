<?php

/**
 * The purpose of this file is to find and update classnames (and interfaces...)
 * in their declarations. Those replaced are recorded and their uses elsewhere
 * are updated in a later step.
 */

namespace CoenJacobs\Mozart\Replace\Classmap;

use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Replace\AbstractAutoloadReplacer;
use CoenJacobs\Mozart\Replace\Support\AstUtils;

class ClassmapReplacer extends AbstractAutoloadReplacer
{
    /** @var array<string,string> */
    protected array $replacedClasses = [];

    protected string $classmapPrefix;

    protected AstUtils $astUtils;

    public function __construct(string $classmapPrefix = '')
    {
        $this->classmapPrefix = $classmapPrefix;
        $this->astUtils = new AstUtils();
    }

    public function getClassmapPrefix(): string
    {
        return $this->classmapPrefix;
    }

    /**
     * @return array<string,string>
     */
    public function getReplacedClasses(): array
    {
        return $this->replacedClasses;
    }

    /**
     * Replace class declarations in the given PHP code.
     */
    public function replace(string $contents): string
    {
        if (empty($contents) || empty($this->classmapPrefix)) {
            return $contents;
        }

        // AST parsing requires a PHP opening tag
        if (!preg_match('/^\s*<\?php/i', $contents)) {
            return $contents; // Not PHP code, return as-is
        }

        $ast = $this->astUtils->parseCode($contents);

        if ($ast === null) {
            $error = $this->astUtils->getLastError() ?? 'Unknown parse error';
            throw new FileOperationException("Failed to parse PHP code: {$error}");
        }

        $visitor = new DeclarationVisitor($this->classmapPrefix);
        $traverser = $this->astUtils->createSimpleTraverser($visitor);
        $modifiedAst = $traverser->traverse($ast);

        $this->replacedClasses = array_merge(
            $this->replacedClasses,
            $visitor->getReplacedClasses()
        );

        return $this->astUtils->printCode($modifiedAst);
    }
}
