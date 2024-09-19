<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
use CoenJacobs\Mozart\Replace\Replacer as ReplacerInterface;
use Exception;

class Replacer
{
    /** @var Mozart */
    protected $config;

    /** @var array<string,string> */
    protected $replacedClasses = [];

    /** @var FilesHandler */
    protected $files;

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
            $this->replacePackages($package->getDependencies());
            $this->replacePackage($package);
        }
    }

    public function replacePackage(Package $package): void
    {
        foreach ($package->getAutoloaders() as $autoloader) {
            $this->replacePackageByAutoloader($package, $autoloader);
        }
    }

    public function replaceInFile(string $targetFile, Autoloader $autoloader): void
    {
        $targetFile = str_replace($this->config->getWorkingDir(), '', $targetFile);
        $contents = $this->files->readFile($targetFile);

        if (!$contents) {
            return;
        }

        $replacer = $this->getReplacerByAutoloader($autoloader);
        $contents = $replacer->replace($contents);

        if ($replacer instanceof ClassmapReplacer) {
            $this->replacedClasses = array_merge($this->replacedClasses, $replacer->replacedClasses);
        }

        $this->files->writeFile($targetFile, $contents);
    }

    public function getReplacerByAutoloader(Autoloader $autoloader): ReplacerInterface
    {
        if ($autoloader instanceof NamespaceAutoloader) {
            $replacer = new NamespaceReplacer();
            $replacer->depNamespace = $this->config->getDependencyNamespace();
            $replacer->setAutoloader($autoloader);
            return $replacer;
        }

        $replacer = new ClassmapReplacer();
        $replacer->classmapPrefix = $this->config->getClassmapPrefix();
        $replacer->setAutoloader($autoloader);
        return $replacer;
    }

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
            $sourcePath = $this->config->getWorkingDir() . $this->config->getClassmapDirectory() . $package->getName();
            $files = $this->files->getFilesFromPath($sourcePath);

            foreach ($files as $foundFile) {
                $targetFile = $foundFile->getRealPath();

                if ('.php' == substr($targetFile, -4, 4)) {
                    $this->replaceInFile($targetFile, $autoloader);
                }
            }
        }
    }

    public function replaceParentClassesInDirectory(string $directory): void
    {
        if (count($this->replacedClasses)===0) {
            return;
        }

        $directory = trim($directory, '//');
        $files = $this->files->getFilesFromPath($directory);

        $replacedClasses = $this->replacedClasses;

        foreach ($files as $file) {
            $targetFile = $file->getPathName();

            if ('.php' == substr($targetFile, -4, 4)) {
                $contents = $this->files->readFile($targetFile);

                foreach ($replacedClasses as $original => $replacement) {
                    $contents = preg_replace_callback(
                        '/(.*)([^a-zA-Z0-9_\x7f-\xff])'. $original . '([^a-zA-Z0-9_\x7f-\xff])/U',
                        function ($matches) use ($replacement) {
                            if (preg_match('/(include|require)/', $matches[0])) {
                                return $matches[0];
                            }
                            return $matches[1] . $matches[2] . $replacement . $matches[3];
                        },
                        $contents
                    );

                    if (empty($contents)) {
                        throw new Exception('Failed to replace parent classes in directory.');
                    }
                }

                $this->files->writeFile($targetFile, $contents);
            }
        }
    }

    public function replaceInDirectory(NamespaceAutoloader $autoloader, string $directory): void
    {
        $files = $this->files->getFilesFromPath($directory);

        foreach ($files as $file) {
            $targetFile = $file->getPathName();

            if ('.php' == substr($targetFile, -4, 4)) {
                $this->replaceInFile($targetFile, $autoloader);
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
                        $this->replaceInDirectory($autoloader, $directory);
                        return;
                    }

                    $directory = str_replace($this->config->getWorkingDir(), '', $directory);
                    $this->replaceParentClassesInDirectory($directory);
                    return;
                }

                $directory = $this->config->getWorkingDir() .
                $this->config->getClassmapDirectory() . $parent->getName();

                if ($autoloader instanceof NamespaceAutoloader) {
                    $this->replaceInDirectory($autoloader, $directory);
                    return;
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
     * @return Package[]
     */
    private function getAllDependenciesOfPackage(Package $package, $dependencies = []): array
    {
        if (empty($package->getDependencies())) {
            return $dependencies;
        }

        foreach ($package->getDependencies() as $dependency) {
            $dependencies[] = $dependency;
        }

        foreach ($package->getDependencies() as $dependency) {
            $dependencies = $this->getAllDependenciesOfPackage($dependency, $dependencies);
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
