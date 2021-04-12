<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Extra\NannerlConfig;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;

class Replacer
{
    /** @var NannerlConfig */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    public function __construct($config, $workingDir)
    {

        $this->config = $config;

        $this->filesystem = new Filesystem(new Local($workingDir));
    }

    // Don't replace a classname if there's an import for a class with the same name.
    // but do replace \Classname always


    /**
     * @param array $namespaces
     * @param array $classes
     * @param string $absoluteTargerDir
     * @param array $phpFileList
     * @throws FileNotFoundException
     */
    public function replaceInFiles(array $namespaces, array $classes, array $phpFileList)
    {

        foreach ($phpFileList as $relativeFilePath) {
            $filepath = $this->config->getTargetDirectory() . $relativeFilePath;

            // Throws an exception, but unlikely to happen.
            $contents = $this->filesystem->read($filepath);

            foreach ($namespaces as $originalNamespace) {
                $namespacePrefix = $this->config->getNamespacePrefix();

                $contents = $this->replaceNamespace($contents, $originalNamespace, $namespacePrefix);
            }

            foreach ($classes as $originalClassname) {
                $classmapPrefix = $this->config->getClassmapPrefix();

                $contents = $this->replaceClassname($contents, $originalClassname, $classmapPrefix);
            }

            $this->filesystem->put($filepath, $contents);
        }
    }


    /**
     * @param string $contents The text to make replacements in.
     *
     * @return string The updated text.
     */
    public function replaceNamespace($contents, $originalNamespace, $namespacePrefix)
    {
        $searchNamespace = preg_quote($originalNamespace, '/');

        $pattern = "
            /                                # Start the pattern
            (namespace\s
            |use\s
            |[^a-zA-Z0-9_\x7f-\xff]\\\)
            ({$searchNamespace})            
            /Ux";

        $replacingFunction = function ($matches) use ($namespacePrefix) {

            return $matches[1] . $namespacePrefix . $matches[2];
        };

        $result = preg_replace_callback($pattern, $replacingFunction, $contents);

        $matchingError = preg_last_error();
        if (0 !== $matchingError) {
            $message = "Matching error {$matchingError}";
            if (PREG_BACKTRACK_LIMIT_ERROR === $matchingError) {
                $message = 'Backtrack limit was exhausted!';
            }
            throw new \Exception($message);
        }

        return $result;
    }

    /**
     * In a namespace:
     * * use \Classname;
     * * new \Classname()
     *
     * In a global namespace:
     * * new Classname()
     *
     * @param $contents
     * @param $originalClassname
     * @param $classnamePrefix
     * @return array|string|string[]|null
     * @throws \Exception
     */
    public function replaceClassname($contents, $originalClassname, $classnamePrefix)
    {
        $searchClassname = preg_quote($originalClassname, '/');

        $pattern = '
			/											# Start the pattern
				namespace\s+([a-zA-Z0-9_\x7f-\xff\\\\]+).*?{.*?(namespace|\z) 
														# Look for a preceeding namespace declaration, up until a 
														# potential second namespace declaration.
				|										# if found, match that much before continuing the search on
								    		        	# the remainder of the string.
				([^a-zA-Z0-9_\x7f-\xff])('. $searchClassname . ')([^a-zA-Z0-9_\x7f-\xff])
				
			/x'; //                                     # x: ignore whitespace in regex.

        $replacingFunction = function ($matches) use ($originalClassname, $classnamePrefix) {

            // If we're inside a namespace other than the global namespace:
            if (1 === preg_match('/^namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*/', $matches[0])) {
                $updated = $this->replaceGlobalClassInsideNamedNamespace($matches[0], $originalClassname, $classnamePrefix);

                return $updated;
            }

            return $matches[1] . $matches[2] . $matches[3] . $classnamePrefix . $originalClassname . $matches[5];
        };

        $result = preg_replace_callback($pattern, $replacingFunction, $contents);

        $matchingError = preg_last_error();
        if (0 !== $matchingError) {
            $message = "Matching error {$matchingError}";
            if (PREG_BACKTRACK_LIMIT_ERROR === $matchingError) {
                $message = 'Backtrack limit was exhausted!';
            }
            throw new \Exception($message);
        }

        return $result;
    }

    /**
     * Pass in a string and look for \Classname instances.
     *
     * @param $content
     * @param $originalClassname
     * @param $classnamePrefix
     * @return string
     */
    protected function replaceGlobalClassInsideNamedNamespace($contents, $originalClassname, $classnamePrefix): string
    {

        return preg_replace_callback(
            '/([^a-zA-Z0-9_\x7f-\xff]      # Not a class character
                                    \\\)                       # Followed by a backslash to indicate global namespace
                                    ('.$originalClassname.')   # Followed by the classname
                                    ([^\\\;]+)         # Not a backslash or semicolon which might indicate a namespace
                                                        
                                    
			        /x', //                                     # x: ignore whitespace in regex.
            function ($matches) use ($contents, $originalClassname, $classnamePrefix) {


                return $matches[1] . $classnamePrefix . $originalClassname . $matches[3];
            },
            $contents
        );
    }
}
