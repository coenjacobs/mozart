<?php
/**
 * The purpose of this file is to find and update classnames (and interfaces...) in their declarations.
 * Those replaced are recorded and their uses elsewhere are updated in a later step.
 */

namespace CoenJacobs\Mozart;

class ChangeEnumerator
{
    /** @var string[] */
    protected array $discoveredNamespaces = [];

    /** @var string[] */
    protected array $discoveredClasses = [];

    /**
     * @return string[]
     */
    public function getDiscoveredNamespaces(): array
    {
        // TODO: Order by longest string first.
        return array_keys($this->discoveredNamespaces);
    }

    /**
     * @return string[]
     */
    public function getDiscoveredClasses(): array
    {
        return array_keys($this->discoveredClasses);
    }


    public function findInFiles($dir, $relativeFilepaths)
    {

        foreach ($relativeFilepaths as $relativeFilepath) {
            $filepath = $dir . $relativeFilepath;

            // TODO:
            // $contents = $this->filesystem->read($targetFile);

            $contents = file_get_contents($filepath);

            $this->find($contents);
        }
    }


    /**
     * TODO: Don't use preg_replace!
     *
     * @param string $contents
     *
     * @return string $contents (unmodified)
     */
    public function find(string $contents): string
    {


        // If the entire file is under one namespace, all we want is the namespace.
        $singleNamespacePattern = '/
            namespace\s+([0-9A-Za-z_\x7f-\xff\\\\]+)[\s\S]*;    # A single namespace in the file.... should return
        /x';
        if (1=== preg_match($singleNamespacePattern, $contents, $matches)) {
            $this->discoveredNamespaces[$matches[1]] = $matches[1];
            return $contents;
        }


        return preg_replace_callback(
            '
			/											# Start the pattern
				namespace\s+([a-zA-Z0-9_\x7f-\xff\\\\]+)[;{\s\n]{1}[\s\S]*?(?=namespace|$) 
														# Look for a preceeding namespace declaration, up until a 
														# potential second namespace declaration.
				|										# if found, match that much before continuing the search on
														# the remainder of the string.
				^\s*\/\/.*$ |							# Skip line comments
				^\s*\*[^$\n]*? |						# Skip multiline comment bodies
				^[^$\n]*?(?:\*\/) |						# Skip multiline comment endings
				\s*										# Whitespace is allowed before 
				(?:abstract\sclass|class|interface)\s+	# Look behind for class, abstract class, interface
				([a-zA-Z0-9_\x7f-\xff]+)				# Match the word until the first non-classname-valid character
				\s?										# Allow a space after
				(?:{|extends|implements|\n|$)			# Class declaration can be followed by {, extends, implements 
														# or a new line
			/x', //                                     # x: ignore whitespace in regex.
            function ($matches) use ($contents) {

                // If we're inside a namespace other than the global namespace:
                if (1 === preg_match('/^namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*/', $matches[0])) {
                    $this->discoveredNamespaces[$matches[1]] = $matches[1];
                    return $matches[0];
                }

                // TODO: Why is this [2] and not [1] (which seems to be always empty).
                $this->discoveredClasses[$matches[2]] = $matches[2];
                return $matches[0];
            },
            $contents
        );
    }
}
