<?php

/**
 * The purpose of this file is to find and update classnames (and interfaces...)
 * in their declarations. Those replaced are recorded and their uses elsewhere
 * are updated in a later step.
 */

namespace CoenJacobs\Mozart\Replace;

use Exception;

class ClassmapReplacer extends BaseReplacer
{
    /** @var string[] */
    public $replacedClasses = [];

    /** @var string */
    public $classmapPrefix;

    public function replace(string $contents): string
    {
        if (empty($contents)) {
            return '';
        }

        $replaced = preg_replace_callback(
            "
			/													# Start the pattern
						namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*?(?=namespace|$)
																# Look for a preceeding namespace declaration, up until
																# a potential second namespace declaration
						|										# if found, match that much before repeating the search
																# on the remainder of the string
						(?:abstract\sclass|class|interface)\s+	# Look behind for class, abstract class, interface
						([a-zA-Z0-9_\x7f-\xff]+)				# Match the word until the first
																# non-classname-valid character
						\s?										# Allow a space after
						(?:{|extends|implements|\n)				# Class declaration can be followed by {, extends,
																# implements, or a new line
			/sx", //                                            # dot matches newline, ignore whitespace in regex.
            function ($matches) {

                // If we're inside a namespace other than the global namesspace, just return.
                if (preg_match('/^namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*/', $matches[0])) {
                    return $matches[0] ;
                }

                // The prepended class name.
                $replace = $this->classmapPrefix . $matches[1];
                $this->saveReplacedClass($matches[1], $replace);
                return str_replace($matches[1], $replace, $matches[0]);
            },
            $contents
        );

        if (empty($replaced)) {
            throw new Exception('Failed to replace contents of the file.');
        }

        return $replaced;
    }

    public function saveReplacedClass(string $classname, string $replacedName): void
    {
        $this->replacedClasses[ $classname ] = $replacedName;
    }
}
