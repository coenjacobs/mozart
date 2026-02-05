<?php

namespace CoenJacobs\Mozart\Replace\Classmap;

use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Replace\StringReplacer;
use CoenJacobs\Mozart\Replace\Support\AstUtils;

/**
 * Replaces classmap class names in PHP code.
 */
class NameReplacer implements StringReplacer
{
    /**
     * Map of original class names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $classMap;

    protected AstUtils $astUtils;

    /**
     * @param array<string,string> $classMap Map of original => prefixed class names
     */
    public function __construct(array $classMap)
    {
        $this->classMap = $classMap;
        $this->astUtils = new AstUtils();
    }

    /**
     * Replace classmap class names in the given PHP code.
     *
     * @param string $contents The PHP code to process
     * @return string The processed PHP code
     */
    public function replace(string $contents): string
    {
        if (empty($contents) || empty($this->classMap)) {
            return $contents;
        }

        $ast = $this->astUtils->parseCode($contents);

        if ($ast === null) {
            $error = $this->astUtils->getLastError() ?? 'Unknown parse error';
            throw new FileOperationException("Failed to parse PHP code: {$error}");
        }

        $visitor = new NameVisitor($this->classMap);
        $traverser = $this->astUtils->createTraverser($visitor);
        $modifiedAst = $traverser->traverse($ast);

        return $this->astUtils->printCode($modifiedAst);
    }
}
