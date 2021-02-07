<?php
/**
 * The purpose of this file is to find and update classnames (and interfaces...) in their declarations.
 * Those replaced are recorded and their uses elsewhere are updated in a later step.
 */

namespace CoenJacobs\Mozart\Replace;

class ClassmapReplacer extends BaseReplacer
{

    /** @var string[] */
    public $replacedClasses = [];

    /** @var string */
    public $classmap_prefix;

    /**
     * @param false|string $contents
     *
     * @return string
     */
    public function replace($contents): string
    {
        if (empty($contents) || false === $contents) {
            return '';
        }

        return preg_replace_callback(
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
            function ($matches) use ($contents) {

                // If we're inside a namespace other than the global namesspace, just return.
                if (preg_match('/^namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*/', $matches[0])) {
                    return $matches[0] ;
                }

                // The prepended class name.
                $replace = $this->classmap_prefix . $matches[1];
                $this->saveReplacedClass($matches[1], $replace);
                return str_replace($matches[1], $replace, $matches[0]);
            },
            $contents
        );
    }

    public function saveReplacedClass($classname, string $replacedName): void
    {
        $this->replacedClasses[ $classname ] = $replacedName;
    }
}
