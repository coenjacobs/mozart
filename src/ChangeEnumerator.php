<?php
/**
 * The purpose of this class is only to find changes that should be made.
 * i.e. classes and namespaces to change.
 * Those recorded are updated in a later step.
 */

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;

class ChangeEnumerator
{

    protected string $namespacePrefix;
    protected string $classmapPrefix;
    /**
     *
     * @var string[]
     */
    protected array $excludePackagesFromPrefixing = array();
    protected array $excludeNamespacesFromPrefixing = array();

    protected array $excludeFilePatternsFromPrefixing = array( '/psr.*/');

    protected array $namespaceReplacementPatterns = array();

    /** @var string[] */
    protected array $discoveredNamespaces = [];

    /** @var string[] */
    protected array $discoveredClasses = [];


    /**
     * ChangeEnumerator constructor.
     * @param array $excludePackagesPaths
     * @param array $excludeNamespaces
     */
    public function __construct(StraussConfig $config)
    {
        $this->namespacePrefix = $config->getNamespacePrefix();
        $this->classmapPrefix = $config->getClassmapPrefix();

        $this->excludePackagesFromPrefixing = $config->getExcludePackagesFromPrefixing();
        $this->excludeNamespacesFromPrefixing = $config->getExcludeNamespacesFromPrefixing();
        $this->excludeFilePatternsFromPrefixing = $config->getExcludeFilePatternsFromPrefixing();

        $this->namespaceReplacementPatterns = $config->getNamespaceReplacementPatterns();
    }

    /**
     * TODO: Order by longest string first. (or instead, record classnames with their namespaces)
     *
     * @return string[]
     */
    public function getDiscoveredNamespaceReplacements(): array
    {
        $discoveredNamespaceReplacements = $this->discoveredNamespaces;

        uksort($discoveredNamespaceReplacements, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        return $discoveredNamespaceReplacements;
    }

    /**
     * @return string[]
     */
    public function getDiscoveredClasses(): array
    {
        return array_keys($this->discoveredClasses);
    }


    /**
     * @param string $dir
     * @param array<string, ComposerPackage> $relativeFilepaths
     */
    public function findInFiles($dir, $relativeFilepaths)
    {

        foreach ($relativeFilepaths as $relativeFilepath => $package) {
            foreach ($this->excludePackagesFromPrefixing as $excludePackagesName) {
                if ($package->getName() === $excludePackagesName) {
                    continue 2;
                }
            }

            foreach ($this->excludeFilePatternsFromPrefixing as $excludeFilePattern) {
                if (1 === preg_match($excludeFilePattern, $relativeFilepath)) {
                    continue 2;
                }
            }


            $filepath = $dir . $relativeFilepath;

            // TODO: use flysystem
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
        if (1 === preg_match($singleNamespacePattern, $contents, $matches)) {
            $this->addDiscoveredNamespaceChange($matches[1]);
            return $contents;
        }


        // TODO traits

        // TODO: Is the ";" in this still correct since it's being taken care of in the regex just above?
        // Looks like with the preceeding regex, it will never match.


        return preg_replace_callback(
            '
			/											# Start the pattern
				namespace\s+([a-zA-Z0-9_\x7f-\xff\\\\]+)[;{\s\n]{1}[\s\S]*?(?=namespace|$) 
														# Look for a preceeding namespace declaration, 
														# followed by a semicolon, open curly bracket, space or new line
														# up until a 
														# potential second namespace declaration or end of file.
														# if found, match that much before continuing the search on
				|										# the remainder of the string.
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
                    $this->addDiscoveredNamespaceChange($matches[1]);
                    return $matches[0];
                }

                // TODO: Why is this [2] and not [1] (which seems to be always empty).
                $this->discoveredClasses[$matches[2]] = $matches[2];
                return $matches[0];
            },
            $contents
        );
    }

    protected function addDiscoveredNamespaceChange(string $namespace)
    {

        foreach ($this->excludeNamespacesFromPrefixing as $excludeNamespace) {
            if (0 === strpos($namespace, $excludeNamespace)) {
                return;
            }
        }

        foreach ($this->namespaceReplacementPatterns as $namespaceReplacementPattern => $replacement) {
            $prefixed = preg_replace($namespaceReplacementPattern, $replacement, $namespace);

            if ($prefixed !== $namespace) {
                $this->discoveredNamespaces[$namespace] = $prefixed;
                return;
            }
        }

        $this->discoveredNamespaces[$namespace] = $this->namespacePrefix . '\\'. $namespace;
    }
}
