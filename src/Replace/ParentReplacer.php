<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\FilesHandler;
use CoenJacobs\Mozart\Replace\Classmap\NameReplacer;

class ParentReplacer
{
    protected Mozart $config;

    protected FilesHandler $files;

    protected Replacer $replacer;

    /** @var array<string,string> */
    protected array $replacedClasses = [];

    public function __construct(Mozart $config, Replacer $replacer)
    {
        $this->config   = $config;
        $this->files    = new FilesHandler($config);
        $this->replacer = $replacer;
    }

    /**
     * @param array<string,string> $replacedClasses
     */
    public function setReplacedClasses(array $replacedClasses): void
    {
        $this->replacedClasses = $replacedClasses;
    }

    /**
     * Replaces all occurrences of previously replaced classes, in the provided
     * directory. This to ensure that each package has its parents package
     * classes also replaced in its own files.
     *
     * Uses AST-based replacement to properly handle PHP syntax and avoid
     * incorrectly replacing class names in string literals or comments.
     */
    public function replaceParentClassesInDirectory(string $directory): void
    {
        if (count($this->replacedClasses) === 0) {
            return;
        }

        $directory = trim($directory, '/');

        if (!is_dir($directory)) {
            return;
        }

        $files = $this->files->getFilesFromPath($directory);
        $replacer = new NameReplacer($this->replacedClasses);

        foreach ($files as $file) {
            $targetFile = $file->getPathName();

            if (str_ends_with($targetFile, '.php')) {
                try {
                    $contents = $this->files->readFile($targetFile);
                } catch (\CoenJacobs\Mozart\Exceptions\FileOperationException) {
                    // Skip files that cannot be read
                    continue;
                }

                $modifiedContents = $replacer->replace($contents);

                if ($modifiedContents !== $contents) {
                    $this->files->writeFile($targetFile, $modifiedContents);
                }
            }
        }
    }

    /**
     * Replace everything in parent package, based on the dependency package.
     * This is done to ensure that package A (which requires package B), is also
     * updated with the replacements being made in package B.
     */
    public function replaceParentPackage(Package $package, Package $parent): void
    {
        if ($this->config->isExcludedPackage($package)) {
            return;
        }

        foreach ($parent->getAutoloaders() as $parentAutoloader) {
            foreach ($package->getAutoloaders() as $autoloader) {
                if ($parentAutoloader instanceof NamespaceAutoloader) {
                    $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $parentAutoloader->namespace);
                    $directory = $this->config->getWorkingDir() . $this->config->getDepDirectory() . $namespace
                                 . DIRECTORY_SEPARATOR;

                    if ($autoloader instanceof NamespaceAutoloader) {
                        $this->replacer->replaceInDirectory($autoloader, $directory);
                        continue;
                    }

                    $directory = str_replace($this->config->getWorkingDir(), '', $directory);
                    $this->replaceParentClassesInDirectory($directory);
                    continue;
                }

                $directory = $this->config->getWorkingDir() .
                $this->config->getClassmapDirectory() . $parent->getDirectoryName();

                if ($autoloader instanceof NamespaceAutoloader) {
                    $this->replacer->replaceInDirectory($autoloader, $directory);
                    continue;
                }

                $directory = str_replace($this->config->getWorkingDir(), '', $directory);
                $this->replaceParentClassesInDirectory($directory);
            }
        }
    }

    /**
     * Get an array containing all the dependencies and dependencies.
     *
     * @param Package   $package
     * @param Package[] $dependencies
     * @param array<string,bool> $visited
     * @return Package[]
     */
    private function getAllDependenciesOfPackage(
        Package $package,
        array $dependencies = [],
        array &$visited = []
    ): array {
        if (empty($package->getDependencies())) {
            return $dependencies;
        }

        foreach ($package->getDependencies() as $dependency) {
            $name = $dependency->getName();
            if (isset($visited[$name])) {
                continue;
            }
            $visited[$name] = true;
            $dependencies[] = $dependency;
            $dependencies = $this->getAllDependenciesOfPackage($dependency, $dependencies, $visited);
        }

        return $dependencies;
    }

    /**
     * @param Package[] $packages
     */
    public function replaceParentInTree(array $packages): void
    {
        foreach ($packages as $package) {
            if ($this->config->isExcludedPackage($package)) {
                continue;
            }

            $dependencies = $this->getAllDependenciesOfPackage($package);

            foreach ($dependencies as $dependency) {
                $this->replaceParentPackage($dependency, $package);
            }

            $this->replaceParentInTree($package->getDependencies());
        }
    }
}
