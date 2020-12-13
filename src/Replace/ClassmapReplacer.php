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

    public function replace($contents)
    {
        return preg_replace_callback(
            "
			/													# Start the pattern 
						(?:abstract\sclass|class|interface)\s+	# Look behind for class, abstract class, interface
						([a-zA-Z0-9_\x7f-\xff]+)				# Match the word made of valid classname characters
						\s?										# Allow a space after
						(?:{|extends|implements|\n)				# Class declaration can be followed by {, extends, implements, or a new line
			/x", //  non-greedy matching by default, ignore whitespace in regex.

            function ($matches) {
                $replace = $this->classmap_prefix . $matches[1];
                $this->saveReplacedClass($matches[1], $replace);
                return str_replace($matches[1], $replace, $matches[0]);
            },
            $contents
        );
    }

    public function saveReplacedClass($classname, $replacedName)
    {
        $this->replacedClasses[ $classname ] = $replacedName;
    }
}
