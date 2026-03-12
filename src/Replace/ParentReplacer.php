<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Files;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\FilesHandler;
use CoenJacobs\Mozart\Replace\GlobalScope\NameReplacer;
use CoenJacobs\Mozart\Replace\Namespace\NamespaceReplacer;

class ParentReplacer
{
    protected Mozart $config;

    protected FilesHandler $files;

    protected Replacer $replacer;

    /** @var array<string,string> */
    protected array $replacedClasses = [];

    /** @var array<string,string> */
    protected array $replacedConstants = [];

    /** @var array<string,string> */
    protected array $replacedFunctions = [];

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
     * @param array<string,string> $replacedConstants
     */
    public function setReplacedConstants(array $replacedConstants): void
    {
        $this->replacedConstants = $replacedConstants;
    }

    /**
     * @param array<string,string> $replacedFunctions
     */
    public function setReplacedFunctions(array $replacedFunctions): void
    {
        $this->replacedFunctions = $replacedFunctions;
    }

    /**
     * Replaces all occurrences of previously replaced global-scope symbols
     * in the provided directory. This ensures that each package has its parent
     * package's symbols also replaced in its own files.
     *
     * Uses AST-based replacement to properly handle PHP syntax and avoid
     * incorrectly replacing names in string literals or comments.
     */
    public function replaceParentClassesInDirectory(string $directory): void
    {
        if (empty($this->replacedClasses) && empty($this->replacedConstants) && empty($this->replacedFunctions)) {
            return;
        }

        $directory = trim($directory, '/');

        if (!is_dir($directory)) {
            return;
        }

        $files = $this->files->getFilesFromPath($directory);
        $replacer = new NameReplacer($this->replacedClasses, $this->replacedConstants, $this->replacedFunctions);

        foreach ($files as $file) {
            $targetFile = $file->getPathName();

            if (str_ends_with($targetFile, '.php')) {
                $this->replaceParentClassesInFile($targetFile, $replacer);
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
            if ($parentAutoloader instanceof Files) {
                $this->replaceParentFilesPackage($package, $parentAutoloader);
                continue;
            }

            foreach ($package->getAutoloaders() as $autoloader) {
                if ($parentAutoloader instanceof NamespaceAutoloader) {
                    $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $parentAutoloader->namespace);
                    $directory = $this->config->getWorkingDir() . $this->config->getDepDirectory() . $namespace
                                 . DIRECTORY_SEPARATOR;

                    if ($autoloader instanceof NamespaceAutoloader) {
                        $this->replacer->replaceInDirectory($autoloader, $directory);
                        continue;
                    }

                    if ($autoloader instanceof Files) {
                        $this->replaceParentNamespacesFromFilesAutoloaderInDirectory($directory, $autoloader);
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

                if ($autoloader instanceof Files) {
                    $this->replaceParentNamespacesFromFilesAutoloaderInDirectory($directory, $autoloader);
                }

                $directory = str_replace($this->config->getWorkingDir(), '', $directory);
                $this->replaceParentClassesInDirectory($directory);
            }
        }
    }

    /**
     * Replace pass-2 global-scope symbols in a single file.
     */
    private function replaceParentClassesInFile(string $targetFile, NameReplacer $replacer): void
    {
        $fullPath = $this->getFullPath($targetFile);

        if (!is_file($fullPath)) {
            return;
        }

        $targetFile = str_replace($this->config->getWorkingDir(), '', $fullPath);

        try {
            $contents = $this->files->readFile($targetFile);
        } catch (\CoenJacobs\Mozart\Exceptions\FileOperationException) {
            // Skip files that cannot be read
            return;
        }

        $modifiedContents = $replacer->replace($contents);

        if ($modifiedContents !== $contents) {
            $this->files->writeFile($targetFile, $modifiedContents);
        }
    }

    /**
     * Replace parent files-autoloader targets using the same resolved paths as the mover.
     */
    private function replaceParentFilesPackage(Package $package, Files $parentAutoloader): void
    {
        $files = $parentAutoloader->getFiles($this->files);

        foreach ($package->getAutoloaders() as $autoloader) {
            foreach ($files as $file) {
                $targetFile = $parentAutoloader->getTargetFilePath($file);
                $fullPath = $this->config->getWorkingDir() . $targetFile;

                if (!str_ends_with($fullPath, '.php')) {
                    continue;
                }

                if ($autoloader instanceof NamespaceAutoloader) {
                    $this->replacer->replaceInFile($fullPath, $autoloader);
                    continue;
                }

                if ($autoloader instanceof Files) {
                    $this->replaceParentNamespacesFromFilesAutoloaderInFile($fullPath, $autoloader);
                }

                $replacer = new NameReplacer(
                    $this->replacedClasses,
                    $this->replacedConstants,
                    $this->replacedFunctions
                );
                $this->replaceParentClassesInFile($fullPath, $replacer);
            }
        }
    }

    /**
     * Normalize a path to an absolute path under the configured working directory.
     */
    private function getFullPath(string $path): string
    {
        if (str_starts_with($path, $this->config->getWorkingDir())) {
            return $path;
        }

        return $this->config->getWorkingDir() . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Apply namespace replacement from a child files autoloader to every PHP file in a parent directory.
     */
    private function replaceParentNamespacesFromFilesAutoloaderInDirectory(string $directory, Files $autoloader): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $namespaces = $this->getFilesAutoloaderNamespaces($autoloader);
        if (empty($namespaces)) {
            return;
        }

        $files = $this->files->getFilesFromPath($directory);

        foreach ($files as $file) {
            $targetFile = $file->getPathName();

            if (!str_ends_with($targetFile, '.php')) {
                continue;
            }

            $this->replaceParentNamespacesFromFilesAutoloaderInFile($targetFile, $autoloader, $namespaces);
        }
    }

    /**
     * Apply namespace replacement from a child files autoloader to a single parent file.
     *
     * @param string[]|null $namespaces Cached namespaces from the child files autoloader.
     */
    private function replaceParentNamespacesFromFilesAutoloaderInFile(
        string $targetFile,
        Files $autoloader,
        ?array $namespaces = null
    ): void {
        $fullPath = $this->getFullPath($targetFile);

        if (!is_file($fullPath)) {
            return;
        }

        $targetFile = str_replace($this->config->getWorkingDir(), '', $fullPath);

        try {
            $contents = $this->files->readFile($targetFile);
        } catch (\CoenJacobs\Mozart\Exceptions\FileOperationException) {
            return;
        }

        $namespaces = $namespaces ?? $this->getFilesAutoloaderNamespaces($autoloader);

        foreach ($namespaces as $namespace) {
            $replacer = new NamespaceReplacer($this->config->getDependencyNamespace());
            $replacer->setAutoloader($autoloader);
            $replacer->setSearchNamespace($namespace);
            $contents = $replacer->replace($contents);
        }

        $this->files->writeFile($targetFile, $contents);
    }

    /**
     * Collect distinct namespaces declared by files in a files autoloader.
     *
     * @return string[]
     */
    private function getFilesAutoloaderNamespaces(Files $autoloader): array
    {
        $namespaces = [];

        foreach ($autoloader->getFiles($this->files) as $file) {
            $namespace = $autoloader->getDetectedNamespace($file);

            if ($namespace === null || $namespace === '') {
                continue;
            }

            $namespaces[$namespace] = true;
        }

        return array_keys($namespaces);
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
