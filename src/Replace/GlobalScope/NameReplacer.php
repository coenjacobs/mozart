<?php

namespace CoenJacobs\Mozart\Replace\GlobalScope;

use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Replace\StringReplacer;
use CoenJacobs\Mozart\Replace\Support\AstUtils;

/**
 * Replaces global-scope names (classes, constants) in PHP code.
 */
class NameReplacer implements StringReplacer
{
    /**
     * Map of original class names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $classMap;

    /**
     * Map of original constant names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $constantMap;

    protected AstUtils $astUtils;

    /**
     * @param array<string,string> $classMap Map of original => prefixed class names
     * @param array<string,string> $constantMap Map of original => prefixed constant names
     */
    public function __construct(array $classMap, array $constantMap = [])
    {
        $this->classMap = $classMap;
        $this->constantMap = $constantMap;
        $this->astUtils = new AstUtils();
    }

    /**
     * Replace global-scope names in the given PHP code.
     *
     * @param string $contents The PHP code to process
     * @return string The processed PHP code
     */
    public function replace(string $contents): string
    {
        if (empty($contents) || (empty($this->classMap) && empty($this->constantMap))) {
            return $contents;
        }

        $ast = $this->astUtils->parseCode($contents);

        if ($ast === null) {
            $error = $this->astUtils->getLastError() ?? 'Unknown parse error';
            throw new FileOperationException("Failed to parse PHP code: {$error}");
        }

        $visitor = new NameVisitor($this->classMap, $this->constantMap);
        $traverser = $this->astUtils->createTraverser($visitor);
        $modifiedAst = $traverser->traverse($ast);

        return $this->astUtils->printCode($modifiedAst);
    }
}
