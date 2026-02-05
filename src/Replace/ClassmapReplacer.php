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
    /** @var array<string,string> */
    protected array $replacedClasses = [];

    protected string $classmapPrefix;

    public function __construct(string $classmapPrefix = '')
    {
        $this->classmapPrefix = $classmapPrefix;
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

    public function replace(string $contents): string
    {
        if (empty($contents)) {
            return '';
        }

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
