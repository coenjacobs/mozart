<?php
/**
 * The purpose of this file is to find and update classnames (and interfaces...) in their declarations.
 * Those replaced are recorded and their uses elsewhere are updated in a later step.
 */

namespace CoenJacobs\Mozart\Replace;

class ClassmapReplacer extends BaseReplacer
{

    /** @var string[] */
    protected array $replacedClasses = [];

    /** @var string */
    protected string $classmap_prefix;

    /**
     * @return string[]
     */
    public function getReplacedClasses(): array
    {
        return $this->replacedClasses;
    }

    /**
     * @param string[] $replacedClasses
     */
    public function setReplacedClasses(array $replacedClasses): void
    {
        $this->replacedClasses = $replacedClasses;
    }

    /**
     * @return string
     */
    public function getClassmapPrefix(): string
    {
        return $this->classmap_prefix;
    }

    /**
     * @param string $classmap_prefix
     */
    public function setClassmapPrefix(string $classmap_prefix): void
    {
        $this->classmap_prefix = $classmap_prefix;
    }

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
            '
			/											# Start the pattern
				namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}[\s\S]*?(?=namespace|$) 
														# Look for a preceeding namespace declaration, up until a 
														# potential second namespace declaration.
				|										# if found, match that much before continuing the search on
														# the remainder of the string.
				^\s*\/\/.*$ |							# Skip line comments
				^\s*\*[^$\n]*? |						# Skip multiline comment bodies
				^[^$\n]*?(?:\*\/) |						# Skip multiline comment endings
				[\s\S]*?(?='.$this->classmap_prefix.')	# Match an already prefixed classname
				[a-zA-Z0-9_\x7f-\xff]+ |				# before continuing on the remainder of the string	
				\s*										# Whitespace is allowed before 
				(?:abstract\sclass|class|interface)\s+	# Look behind for class, abstract class, interface
				([a-zA-Z0-9_\x7f-\xff]+)				# Match the word until the first non-classname-valid character
				\s?										# Allow a space after
				(?:{|extends|implements|\n|$)			# Class declaration can be followed by {, extends, implements 
														# or a new line
			/x', //                                     # x: ignore whitespace in regex.
            function ($matches) use ($contents) {

                // If we didn't capture a proper class/interface, return.
                if (1 === count($matches)) {
                    return $matches[0];
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
