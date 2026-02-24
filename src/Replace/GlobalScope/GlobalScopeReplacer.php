<?php

/**
 * The purpose of this file is to find and update global-scope declarations
 * (classes, interfaces, traits, enums, constants) in their declarations.
 * Those replaced are recorded and their uses elsewhere are updated in a
 * later step.
 */

namespace CoenJacobs\Mozart\Replace\GlobalScope;

use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\PhpSymbols\BuiltInSymbols;
use CoenJacobs\Mozart\PhpSymbols\BuiltInSymbolsInterface;
use CoenJacobs\Mozart\Replace\AbstractAutoloadReplacer;
use CoenJacobs\Mozart\Replace\Support\AstUtils;

class GlobalScopeReplacer extends AbstractAutoloadReplacer
{
    /** @var array<string,string> */
    protected array $replacedClasses = [];

    /** @var array<string,string> */
    protected array $replacedConstants = [];

    protected string $classmapPrefix;

    protected string $constantPrefix;

    protected BuiltInSymbolsInterface $builtInSymbols;

    protected AstUtils $astUtils;

    /**
     * @param string $classmapPrefix Prefix for class/interface/trait/enum declarations
     * @param BuiltInSymbolsInterface|null $builtInSymbols Built-in symbol database
     * @param string $constantPrefix Prefix for constant declarations
     */
    public function __construct(
        string $classmapPrefix = '',
        ?BuiltInSymbolsInterface $builtInSymbols = null,
        string $constantPrefix = ''
    ) {
        $this->classmapPrefix = $classmapPrefix;
        $this->constantPrefix = $constantPrefix;
        $this->builtInSymbols = $builtInSymbols ?? new BuiltInSymbols();
        $this->astUtils = new AstUtils();
    }

    /**
     * Get the classmap prefix.
     */
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
     * @return array<string,string>
     */
    public function getReplacedConstants(): array
    {
        return $this->replacedConstants;
    }

    /**
     * Replace global-scope declarations in the given PHP code.
     */
    public function replace(string $contents): string
    {
        if (empty($contents) || (empty($this->classmapPrefix) && empty($this->constantPrefix))) {
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

        $visitor = new DeclarationVisitor(
            $this->classmapPrefix,
            $this->builtInSymbols,
            $this->constantPrefix
        );
        $traverser = $this->astUtils->createSimpleTraverser($visitor);
        $modifiedAst = $traverser->traverse($ast);

        $this->replacedClasses = array_merge(
            $this->replacedClasses,
            $visitor->getReplacedClasses()
        );

        $this->replacedConstants = array_merge(
            $this->replacedConstants,
            $visitor->getReplacedConstants()
        );

        return $this->astUtils->printCode($modifiedAst);
    }
}
