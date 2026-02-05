<?php

namespace CoenJacobs\Mozart\Replace;

use RuntimeException;

/**
 * Replaces classmap class names in PHP code.
 *
 * Uses AST-based replacement with regex fallback when AST processing fails.
 */
class ClassmapNameReplacer implements StringReplacer
{
    /**
     * Maximum file size (in bytes) for AST processing.
     * Files larger than this use regex to avoid memory issues during printing.
     * Memory usage is roughly 10x file size for AST operations, but when processing
     * many files in sequence, memory may accumulate. 300KB keeps us safe with
     * PHP's default 128MB limit while still handling most files via AST.
     */
    protected const MAX_AST_FILE_SIZE = 300000; // 300KB

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

        try {
            return $this->replaceWithAst($contents);
        } catch (\Throwable) {
            // Fall back to regex if AST processing fails (syntax errors, etc.)
            return $this->replaceWithRegex($contents);
        }
    }

    /**
     * AST-based classmap replacement.
     *
     * @param string $contents The PHP code to process
     * @return string The processed PHP code
     * @throws \RuntimeException If file is too large for AST processing
     */
    protected function replaceWithAst(string $contents): string
    {
        // Skip AST for very large files to avoid memory issues during printing
        if (strlen($contents) > self::MAX_AST_FILE_SIZE) {
            throw new RuntimeException('File too large for AST processing');
        }

        $ast = $this->astUtils->parseCode($contents);

        if ($ast === null) {
            return $contents;
        }

        $visitor = new ClassmapNameVisitor($this->classMap);
        $traverser = $this->astUtils->createTraverser($visitor);
        $modifiedAst = $traverser->traverse($ast);

        return $this->astUtils->printCode($modifiedAst);
    }

    /**
     * Regex-based fallback for classmap replacement.
     *
     * @param string $contents The PHP code to process
     * @return string The processed PHP code
     */
    protected function replaceWithRegex(string $contents): string
    {
        foreach ($this->classMap as $original => $replacement) {
            $result = preg_replace_callback(
                '/(.*)([^a-zA-Z0-9_\x7f-\xff])' . preg_quote($original, '/') . '([^a-zA-Z0-9_\x7f-\xff])/U',
                function ($matches) use ($replacement) {
                    if (preg_match('/(include|require)/', $matches[0])) {
                        return $matches[0];
                    }
                    return $matches[1] . $matches[2] . $replacement . $matches[3];
                },
                $contents
            );

            if ($result === null) {
                break;
            }
            $contents = $result;
        }

        return $contents;
    }
}
