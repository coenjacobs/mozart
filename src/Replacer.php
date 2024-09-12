<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
use Exception;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Replacer
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var Mozart */
    protected $config;

    /** @var array<string,string> */
    protected $replacedClasses = [];

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(string $workingDir, Mozart $config)
    {
        $this->workingDir = $workingDir;
        $this->config     = $config;
        $this->targetDir  = $this->config->getDepDirectory();

        $adapter = new LocalFilesystemAdapter(
            $this->workingDir
        );

        // The FilesystemOperator
        $this->filesystem = new Filesystem($adapter);
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
        $targetFile = str_replace($this->workingDir, '', $targetFile);
        try {
            $contents = $this->filesystem->read($targetFile);
        } catch (UnableToReadFile $e) {
            return;
        }

        if (!$contents) {
            return;
        }

        if ($autoloader instanceof NamespaceAutoloader) {
            $replacer = new NamespaceReplacer();
            $replacer->dep_namespace = $this->config->getDependencyNamespace();
        } else {
            $replacer = new ClassmapReplacer();
            $replacer->classmap_prefix = $this->config->getClassmapPrefix();
        }

        $replacer->setAutoloader($autoloader);
        $contents = $replacer->replace($contents);

        if ($replacer instanceof ClassmapReplacer) {
            $this->replacedClasses = array_merge($this->replacedClasses, $replacer->replacedClasses);
        }

        $this->filesystem->write($targetFile, $contents);
    }

    public function replacePackageByAutoloader(Package $package, Autoloader $autoloader): void
    {
        if ($this->config->isExcludedPackage($package)) {
            return;
        }

        if ($autoloader instanceof NamespaceAutoloader) {
            $source_path = $this->workingDir . $this->targetDir
                           . str_replace('\\', DIRECTORY_SEPARATOR, $autoloader->getNamespace());
            $this->replaceInDirectory($autoloader, $source_path);
        } elseif ($autoloader instanceof Classmap) {
            $finder = new Finder();
            $source_path = $this->workingDir . $this->config->getClassmapDirectory() . $package->getName();
            $finder->files()->in($source_path);

            foreach ($finder as $foundFile) {
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
        $finder = new Finder();
        $finder->files()->in($directory);

        $replacedClasses = $this->replacedClasses;

        foreach ($finder as $file) {
            $targetFile = $file->getPathName();

            if ('.php' == substr($targetFile, -4, 4)) {
                try {
                    $contents = $this->filesystem->read($targetFile);
                } catch (UnableToReadFile $e) {
                    continue;
                }

                foreach ($replacedClasses as $original => $replacement) {
                    $contents = preg_replace_callback(
                        '/(.*)([^a-zA-Z0-9_\x7f-\xff])'. $original . '([^a-zA-Z0-9_\x7f-\xff])/U',
                        function ($matches) use ($replacement) {
                            if (preg_match('/(include|require)/', $matches[0], $output_array)) {
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

                $this->filesystem->write($targetFile, $contents);
            }
        }
    }

    public function replaceInDirectory(NamespaceAutoloader $autoloader, string $directory): void
    {
        $finder = new Finder();
        $finder->files()->in($directory);

        foreach ($finder as $file) {
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
                    $directory = $this->workingDir . $this->config->getDepDirectory() . $namespace
                                 . DIRECTORY_SEPARATOR;

                    if ($autoloader instanceof NamespaceAutoloader) {
                        $this->replaceInDirectory($autoloader, $directory);
                    } else {
                        $directory = str_replace($this->workingDir, '', $directory);
                        $this->replaceParentClassesInDirectory($directory);
                    }
                } else {
                    $directory = $this->workingDir .
                        $this->config->getClassmapDirectory() . $parent->getName();

                    if ($autoloader instanceof NamespaceAutoloader) {
                        $this->replaceInDirectory($autoloader, $directory);
                    } else {
                        $directory = str_replace($this->workingDir, '', $directory);
                        $this->replaceParentClassesInDirectory($directory);
                    }
                }
            }
        }
    }

    /**
     * Get an array containing all the dependencies and dependencies
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
