<?php

/**
 * The purpose of this file is to find and update classnames (and interfaces...)
 * in their declarations. Those replaced are recorded and their uses elsewhere
 * are updated in a later step.
 */

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Exceptions\FileOperationException;

class ClassmapReplacer extends BaseReplacer
{
    /**
     * Maximum file size (in bytes) for AST processing.
     * Files larger than this use regex to avoid memory issues during printing.
     * Memory usage is roughly 10x file size for AST operations, but when processing
     * many files in sequence, memory may accumulate. 300KB keeps us safe with
     * PHP's default 128MB limit while still handling most files via AST.
     */
    protected const MAX_AST_FILE_SIZE = 300000; // 300KB

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
     * Uses AST-based replacement with regex fallback.
     */
    public function replace(string $contents): string
    {
        if (empty($contents) || empty($this->classmapPrefix)) {
            return $contents;
        }

        try {
            return $this->replaceWithAst($contents);
        } catch (\Throwable) {
            return $this->replaceWithRegex($contents);
        }
    }

    protected function replaceWithAst(string $contents): string
    {
        // AST parsing requires a PHP opening tag
        if (!preg_match('/^\s*<\?php/i', $contents)) {
            throw new FileOperationException('Content does not start with PHP opening tag');
        }

        // Skip AST for very large files to avoid memory issues during printing
        if (strlen($contents) > self::MAX_AST_FILE_SIZE) {
            throw new FileOperationException('File too large for AST processing');
        }

        $ast = $this->astUtils->parseCode($contents);

        if ($ast === null) {
            throw new FileOperationException('Failed to parse PHP code');
        }

        $visitor = new ClassmapDeclarationVisitor($this->classmapPrefix);
        $traverser = $this->astUtils->createTraverser($visitor);
        $modifiedAst = $traverser->traverse($ast);

        // Merge replaced classes
        $this->replacedClasses = array_merge(
            $this->replacedClasses,
            $visitor->getReplacedClasses()
        );

        return $this->astUtils->printCode($modifiedAst);
    }

    protected function replaceWithRegex(string $contents): string
    {
        $replaced = preg_replace_callback(
            "
			/													# Start the pattern
						namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*?(?=namespace|$)
															# Look for a preceding namespace declaration, up until
															# a potential second namespace declaration
						|										# if found, match that much before repeating the search
															# on the remainder of the string
						(?:abstract\sclass|class|interface|trait)\s+
														# Look for class, abstract class, interface, trait
						([a-zA-Z0-9_\x7f-\xff]+)				# Match the word until the first
															# non-classname-valid character
						\s?										# Allow a space after
						(?:{|extends|implements|\n)				# Class declaration can be followed by {, extends,
															# implements, or a new line
			/sx", //                                            # dot matches newline, ignore whitespace in regex.
            function ($matches) {

                // If we're inside a namespace other than the global namespace, just return.
                if (preg_match('/^namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*/', $matches[0])) {
                    return $matches[0] ;
                }

                // The prepended class name.
                $replace = $this->classmapPrefix . $matches[1];
                $this->replacedClasses[$matches[1]] = $replace;
                return str_replace($matches[1], $replace, $matches[0]);
            },
            $contents
        );

        if (empty($replaced)) {
            throw new FileOperationException('Failed to replace contents of the file.');
        }

        return $replaced;
    }
}
