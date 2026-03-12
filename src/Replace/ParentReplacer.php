<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Files;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\FilesHandler;
use CoenJacobs\Mozart\Replace\GlobalScope\NameReplacer;

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

    protected ParentFilesAutoloaderNamespacePropagator $namespacePropagator;

    public function __construct(Mozart $config, Replacer $replacer)
    {
        $this->config = $config;
        $this->files = new FilesHandler($config);
        $this->replacer = $replacer;
        $this->namespacePropagator = new ParentFilesAutoloaderNamespacePropagator($config, $this->files);
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
        if (! $this->hasGlobalSymbolReplacements()) {
            return;
        }

        $this->replaceParentClassesWithReplacerInDirectory($directory, $this->createNameReplacer());
    }

    /**
     * Replace pass-2 global-scope symbols across all PHP files in a directory.
     */
    private function replaceParentClassesWithReplacerInDirectory(string $directory, NameReplacer $replacer): void
    {
        $directory = trim($directory, '/');

        if (!is_dir($directory)) {
            return;
        }

        $files = $this->files->getFilesFromPath($directory);

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

            $directory = $this->getParentAutoloaderDirectory($parent, $parentAutoloader);
            $this->replaceParentDirectoryPackage($package, $directory);
        }
    }

    /**
     * Replace pass-2 global-scope symbols in a single file.
     */
    private function replaceParentClassesInFile(string $targetFile, NameReplacer $replacer): void
    {
        if (! $this->hasGlobalSymbolReplacements()) {
            return;
        }

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
        $replacer = $this->createNameReplacer();

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
                    $this->namespacePropagator->replaceInFile($fullPath, $autoloader);
                }

                $this->replaceParentClassesInFile($fullPath, $replacer);
            }
        }
    }

    /**
     * Replace all child autoloaders against a parent directory target.
     */
    private function replaceParentDirectoryPackage(Package $package, string $directory): void
    {
        $replacer = $this->createNameReplacer();

        foreach ($package->getAutoloaders() as $autoloader) {
            $this->replaceChildAutoloaderInDirectory($autoloader, $directory, $replacer);
        }
    }

    /**
     * Replace a child autoloader against a parent directory target.
     */
    private function replaceChildAutoloaderInDirectory(
        Autoloader $autoloader,
        string $directory,
        NameReplacer $replacer
    ): void {
        if ($autoloader instanceof NamespaceAutoloader) {
            $this->replacer->replaceInDirectory($autoloader, $directory);
            return;
        }

        if ($autoloader instanceof Files) {
            $this->namespacePropagator->replaceInDirectory($directory, $autoloader);
        }

        $this->replaceParentClassesWithReplacerInDirectory($this->toRelativePath($directory), $replacer);
    }

    /**
     * Build the absolute directory target for a parent autoloader.
     */
    private function getParentAutoloaderDirectory(Package $parent, Autoloader $parentAutoloader): string
    {
        if ($parentAutoloader instanceof NamespaceAutoloader) {
            $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $parentAutoloader->namespace);

            return $this->config->getWorkingDir()
                . $this->config->getDepDirectory()
                . $namespace
                . DIRECTORY_SEPARATOR;
        }

        return $this->config->getWorkingDir()
            . $this->config->getClassmapDirectory()
            . $parent->getDirectoryName();
    }

    /**
     * Build the global symbol replacer for pass-2 parent replacement.
     */
    private function createNameReplacer(): NameReplacer
    {
        return new NameReplacer(
            $this->replacedClasses,
            $this->replacedConstants,
            $this->replacedFunctions
        );
    }

    /**
     * Check whether any global symbol replacements are available.
     */
    private function hasGlobalSymbolReplacements(): bool
    {
        return !empty($this->replacedClasses)
            || !empty($this->replacedConstants)
            || !empty($this->replacedFunctions);
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
     * Convert an absolute working-dir path to a relative Mozart target path.
     */
    private function toRelativePath(string $path): string
    {
        return str_replace($this->config->getWorkingDir(), '', $path);
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
