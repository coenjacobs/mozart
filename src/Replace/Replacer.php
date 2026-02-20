<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Files;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\FilesHandler;
use CoenJacobs\Mozart\Replace\Classmap\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\Namespace\NamespaceReplacer;

class Replacer
{
    protected Mozart $config;

    /** @var array<string,string> */
    protected array $replacedClasses = [];

    /** @var array<string,bool> */
    protected array $visitedPackages = [];

    protected FilesHandler $files;

    public function __construct(Mozart $config)
    {
        $this->config     = $config;
        $this->files      = new FilesHandler($config);
    }

    /**
     * @param Package[] $packages
     */
    public function replacePackages(array $packages): void
    {
        foreach ($packages as $package) {
            $name = $package->getName();

            if (isset($this->visitedPackages[$name])) {
                continue;
            }

            $this->visitedPackages[$name] = true;

            $this->replacePackages($package->getDependencies());
            $this->replacePackage($package);
        }
    }

    /**
     * Replace all autoloaders for a given package.
     */
    public function replacePackage(Package $package): void
    {
        foreach ($package->getAutoloaders() as $autoloader) {
            $this->replacePackageByAutoloader($package, $autoloader);
        }
    }

    /**
     * Replace namespace or classmap references in a single PHP file.
     */
    public function replaceInFile(string $targetFile, Autoloader $autoloader): void
    {
        $targetFile = str_replace($this->config->getWorkingDir(), '', $targetFile);

        try {
            $contents = $this->files->readFile($targetFile);
        } catch (\CoenJacobs\Mozart\Exceptions\FileOperationException) {
            // Skip files that cannot be read
            return;
        }

        if (empty($contents)) {
            return;
        }

        $replacer = $this->getReplacerByAutoloader($autoloader);
        $contents = $replacer->replace($contents);

        if ($replacer instanceof ClassmapReplacer) {
            $this->replacedClasses = array_merge($this->replacedClasses, $replacer->getReplacedClasses());
        }

        $this->files->writeFile($targetFile, $contents);
    }

    /**
     * Create the appropriate replacer for an autoloader type.
     */
    public function getReplacerByAutoloader(Autoloader $autoloader): AutoloadReplacer
    {
        if ($autoloader instanceof NamespaceAutoloader) {
            $replacer = new NamespaceReplacer($this->config->getDependencyNamespace());
            $replacer->setAutoloader($autoloader);
            return $replacer;
        }

        $replacer = new ClassmapReplacer($this->config->getClassmapPrefix());
        $replacer->setAutoloader($autoloader);
        return $replacer;
    }

    /**
     * Fetches the files or directories to perform a replace action on, based
     * on the provided autoloader, for the provided package.
     */
    public function replacePackageByAutoloader(Package $package, Autoloader $autoloader): void
    {
        if ($this->config->isExcludedPackage($package)) {
            return;
        }

        if ($autoloader instanceof NamespaceAutoloader) {
            $sourcePath = $this->config->getWorkingDir() . $this->config->getDepDirectory()
                           . str_replace('\\', DIRECTORY_SEPARATOR, $autoloader->getNamespace());
            $this->replaceInDirectory($autoloader, $sourcePath);
        } elseif ($autoloader instanceof Classmap) {
            $sourcePath = $this->config->getWorkingDir()
                           . $this->config->getClassmapDirectory()
                           . $package->getDirectoryName();

            if (!is_dir($sourcePath)) {
                return;
            }

            $files = $this->files->getFilesFromPath($sourcePath);

            foreach ($files as $foundFile) {
                $targetFile = $foundFile->getRealPath();

                if (str_ends_with($targetFile, '.php')) {
                    $this->replaceInFile($targetFile, $autoloader);
                }
            }
        } elseif ($autoloader instanceof Files) {
            $this->replaceFilesAutoloader($autoloader);
        }
    }

    /**
     * Handle replacement for Files autoloader entries.
     *
     * Files with namespaces use NamespaceReplacer, files without use ClassmapReplacer.
     */
    protected function replaceFilesAutoloader(Files $autoloader): void
    {
        $filesToProcess = $autoloader->getFiles($this->files);

        foreach ($filesToProcess as $file) {
            $targetFile = $autoloader->getTargetFilePath($file);
            $fullPath = $this->config->getWorkingDir() . $targetFile;

            if (!str_ends_with($fullPath, '.php')) {
                continue;
            }

            $targetFile = str_replace($this->config->getWorkingDir(), '', $fullPath);

            try {
                $contents = $this->files->readFile($targetFile);
            } catch (\CoenJacobs\Mozart\Exceptions\FileOperationException) {
                continue;
            }

            if (empty($contents)) {
                continue;
            }

            // Use appropriate replacer based on whether file has a namespace
            $detectedNamespace = $autoloader->getDetectedNamespace($file);
            $replacer = new ClassmapReplacer($this->config->getClassmapPrefix());

            if ($detectedNamespace !== null) {
                $replacer = new NamespaceReplacer($this->config->getDependencyNamespace());
                $replacer->setSearchNamespace($detectedNamespace);
            }

            $replacer->setAutoloader($autoloader);

            $contents = $replacer->replace($contents);

            if ($replacer instanceof ClassmapReplacer) {
                $this->replacedClasses = array_merge($this->replacedClasses, $replacer->getReplacedClasses());
            }

            $this->files->writeFile($targetFile, $contents);
        }
    }

    /**
     * Replace namespace references in all PHP files within a directory.
     */
    public function replaceInDirectory(NamespaceAutoloader $autoloader, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = $this->files->getFilesFromPath($directory);

        foreach ($files as $file) {
            $targetFile = $file->getPathName();

            if (str_ends_with($targetFile, '.php')) {
                $this->replaceInFile($targetFile, $autoloader);
            }
        }
    }

    /**
     * @return array<string,string>
     */
    public function getReplacedClasses(): array
    {
        return $this->replacedClasses;
    }
}
