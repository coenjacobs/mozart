<?php

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use Exception;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;

class Prefixer
{
    /** @var StraussConfig */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    protected string $targetDirectory;
    protected string $namespacePrefix;
    protected string $classmapPrefix;
    protected ?string $constantsPrefix;

    protected array $excludePackageNamesFromPrefixing;
    protected array $excludeNamespacesFromPrefixing;
    protected array $excludeFilePatternsFromPrefixing;

    /** @var array<string, ComposerPackage> */
    protected array $changedFiles = array();

    public function __construct(StraussConfig $config, string $workingDir)
    {
        $this->filesystem = new Filesystem(new Local($workingDir));

        $this->targetDirectory = $config->getTargetDirectory();
        $this->namespacePrefix = $config->getNamespacePrefix();
        $this->classmapPrefix = $config->getClassmapPrefix();
        $this->constantsPrefix = $config->getConstantsPrefix();

        $this->excludePackageNamesFromPrefixing = $config->getExcludePackagesFromPrefixing();
        $this->excludeNamespacesFromPrefixing = $config->getExcludeNamespacesFromPrefixing();
        $this->excludeFilePatternsFromPrefixing = $config->getExcludeFilePatternsFromPrefixing();
    }

    // Don't replace a classname if there's an import for a class with the same name.
    // but do replace \Classname always


    /**
     * @param array<string, string> $namespaceChanges
     * @param array<string, string> $classChanges
     * @param array<string, ComposerPackage> $phpFileList
     * @throws FileNotFoundException
     */
    public function replaceInFiles(array $namespaceChanges, array $classChanges, array $constants, array $phpFileList)
    {

        foreach ($phpFileList as $sourceRelativeFilePathFromVendor => $package) {
            // Skip excluded namespaces.
            if (in_array($package->getName(), $this->excludePackageNamesFromPrefixing)) {
                continue;
            }

            // Skip files whose filepath matches an excluded pattern.
            foreach ($this->excludeFilePatternsFromPrefixing as $excludePattern) {
                if (1 === preg_match($excludePattern, $sourceRelativeFilePathFromVendor)) {
                    continue 2;
                }
            }

            $targetRelativeFilepathFromProject =
                $this->targetDirectory. $sourceRelativeFilePathFromVendor;

            // Throws an exception, but unlikely to happen.
            $contents = $this->filesystem->read($targetRelativeFilepathFromProject);

            $updatedContents = $this->replaceInString($namespaceChanges, $classChanges, $constants, $contents);

            if ($updatedContents !== $contents) {
                $this->changedFiles[$sourceRelativeFilePathFromVendor] = $package;
                $this->filesystem->put($targetRelativeFilepathFromProject, $updatedContents);
            }
        }
    }

    public function replaceInString(array $namespacesChanges, array $classes, array $originalConstants, string $contents): string
    {

        foreach ($namespacesChanges as $originalNamespace => $replacement) {
            if (in_array($originalNamespace, $this->excludeNamespacesFromPrefixing)) {
                continue;
            }

            $contents = $this->replaceNamespace($contents, $originalNamespace, $replacement);
        }

        foreach ($classes as $originalClassname) {
            $classmapPrefix = $this->classmapPrefix;

            $contents = $this->replaceClassname($contents, $originalClassname, $classmapPrefix);
        }

        if (!is_null($this->constantsPrefix)) {
            $contents = $this->replaceConstants($contents, $originalConstants, $this->constantsPrefix);
        }

        return $contents;
    }

    /**
     * TODO: Test against traits.
     *
     * @param string $contents The text to make replacements in.
     *
     * @return string The updated text.
     */
    public function replaceNamespace($contents, $originalNamespace, $replacement)
    {

        // When namespaces exist inside strings...
        $searchNamespace =
            preg_quote($originalNamespace, '/')
            . '|'
            . preg_quote(str_replace('\\', '\\\\', $originalNamespace), '/')
            . '|'
            . preg_quote('\\' . $originalNamespace, '/')
            . '|'
            . preg_quote('\\\\' . str_replace('\\', '\\\\', $originalNamespace), '/');

        $pattern = "
            /                              # Start the pattern
            (
            ^\s*                           # start of the line
            |namespace\s+                  # the namespace keyword
            |use\s+                        # the use keyword
            |new\s+
            |static\s+
            |\"[^\s]*                      # inside a string that does not contain spaces
            |'[^\s]*
            |implements\s+
            |extends\s+                    # when the class being extended is namespaced inline
            |return\s+
            |\(\s*                         # inside a function declaration as the first parameters type
            |,\s*                          # inside a function declaration as a subsequent parameter type
            |\.\s*                         # as part of a concatenated string
            |=\s*                          # as the value being assigned to a variable
            |\*\s+@\w+\s+                  # In a comments param etc  
            )        
            (
            \\\\*                          # maybe preceeded by a backslash
            {$searchNamespace}             # followed by the namespace to replace
            )
            (?!:)                          # Not followed by : which would only be valid after a classname
            (
            \s*;                           # followed by a semicolon 
            |\\\\{1,2}[a-zA-Z0-9_\x7f-\xff]+         # or a classname no slashes 
            |\s+as                         # or the keyword as 
            |\"                            # or quotes
            |'                             # or single quote         
            |:                             # or a colon to access a static
            )                            
            /Ux";                          // U: Non-greedy matching, x: ignore whitespace in pattern.

        $replacingFunction = function ($matches) use ($originalNamespace, $replacement) {

            $singleBackslash = '\\\\';
            $doubleBackslash = '\\\\\\\\';
            $originalNamespacePattern = str_replace('\\', '\\\\+', $originalNamespace);
            $beginsSingleBackslashPattern = "#{$singleBackslash}{$originalNamespacePattern}#";
            $beginsDoubleBackslashPattern = "#{$doubleBackslash}{$originalNamespacePattern}#";
            $containsDoubleBackslashPattern = "#{$doubleBackslash}#";

            // Namespace begins with \\.
            if (preg_match($beginsDoubleBackslashPattern, $matches[2])) {
                $replacement = str_replace("\\", "\\\\", $replacement);
                $replacement = "\\\\" . $replacement;
            } elseif (preg_match($beginsSingleBackslashPattern, $matches[2])) {
                $replacement = "\\" . $replacement;
            } elseif (preg_match($containsDoubleBackslashPattern, $matches[2])) {
                $replacement = str_replace("\\", "\\\\", $replacement);
            }

            return $matches[1] . $replacement . $matches[3];
        };

        $result = preg_replace_callback($pattern, $replacingFunction, $contents);

        $matchingError = preg_last_error();
        if (0 !== $matchingError) {
            $message = "Matching error {$matchingError}";
            if (PREG_BACKTRACK_LIMIT_ERROR === $matchingError) {
                $message = 'Preg Backtrack limit was exhausted!';
            }
            throw new Exception($message);
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
     * @param string $contents
     * @param string $originalClassname
     * @param string $classnamePrefix
     * @return array|string|string[]|null
     * @throws \Exception
     */
    public function replaceClassname($contents, $originalClassname, $classnamePrefix)
    {
        $searchClassname = preg_quote($originalClassname, '/');

        // This could be more specific if we could enumerate all preceeding and proceeding words ("new", "("...).
        $pattern = '
			/											# Start the pattern
				namespace\s+([a-zA-Z0-9_\x7f-\xff\\\\]+).*?{.*?(namespace|\z) 
														# Look for a preceeding namespace declaration, up until a 
														# potential second namespace declaration.
				|										# if found, match that much before continuing the search on
								    		        	# the remainder of the string.
				([^a-zA-Z0-9_\x7f-\xff\$])('. $searchClassname . ')([^a-zA-Z0-9_\x7f-\xff])
				
			/x'; //                                     # x: ignore whitespace in regex.

        $replacingFunction = function ($matches) use ($originalClassname, $classnamePrefix) {

            // If we're inside a namespace other than the global namespace:
            if (1 === preg_match('/^namespace\s+[a-zA-Z0-9_\x7f-\xff\\\\]+[;{\s\n]{1}.*/', $matches[0])) {
                $updated = $this->replaceGlobalClassInsideNamedNamespace(
                    $matches[0],
                    $originalClassname,
                    $classnamePrefix
                );

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
            throw new Exception($message);
        }

        return $result;
    }

    /**
     * Pass in a string and look for \Classname instances.
     *
     * @param string $contents
     * @param string $originalClassname
     * @param string $classnamePrefix
     * @return string
     */
    protected function replaceGlobalClassInsideNamedNamespace($contents, $originalClassname, $classnamePrefix): string
    {

        return preg_replace_callback(
            '/([^a-zA-Z0-9_\x7f-\xff]  # Not a class character
			\\\)                       # Followed by a backslash to indicate global namespace
			('.$originalClassname.')   # Followed by the classname
			([^\\\;]+)                 # Not a backslash or semicolon which might indicate a namespace
			/x', //                    # x: ignore whitespace in regex.
            function ($matches) use ($originalClassname, $classnamePrefix) {
                return $matches[1] . $classnamePrefix . $originalClassname . $matches[3];
            },
            $contents
        );
    }

    protected function replaceConstants($contents, $originalConstants, $prefix): string
    {

        foreach ($originalConstants as $constant) {
            $contents = $this->replaceConstant($contents, $constant, $prefix . $constant);
        }

        return $contents;
    }

    protected function replaceConstant($contents, $originalConstant, $replacementConstant): string
    {
        return str_replace($originalConstant, $replacementConstant, $contents);
    }

    /**
     * @return array<string, ComposerPackage>
     */
    public function getModifiedFiles(): array
    {
        return $this->changedFiles;
    }
}
