<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Replacer
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var \stdClass */
    protected $config;

    /** @var array */
    protected $replacedClasses = [];

    /** @var Filesystem */
    protected $filesystem;

    public function __construct($workingDir, $config)
    {
        $this->workingDir = $workingDir;
        $this->targetDir = $config->dep_directory;
        $this->config = $config;

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    public function replacePackage(Package $package): void
    {
        foreach ($package->autoloaders as $autoloader) {
            $this->replacePackageByAutoloader($package, $autoloader);
        }
    }

    /**
     * @param $targetFile
     * @param $autoloader
     *
     * @return void
     */
    public function replaceInFile($targetFile, Autoloader $autoloader): void
    {
        $targetFile = str_replace($this->workingDir, '', $targetFile);
        try {
            $contents = $this->filesystem->read($targetFile);
        } catch (FileNotFoundException $e) {
            return;
        }

        if (empty($contents) || false === $contents) {
            return;
        }

        if ($autoloader instanceof NamespaceAutoloader) {
            $replacer = new NamespaceReplacer();
            $replacer->dep_namespace = $this->config->dep_namespace;
        } else {
            $replacer = new ClassmapReplacer();
            $replacer->classmap_prefix = $this->config->classmap_prefix;
        }

        $replacer->setAutoloader($autoloader);
        $contents = $replacer->replace($contents);

        if ($replacer instanceof ClassmapReplacer) {
            $this->replacedClasses = array_merge($this->replacedClasses, $replacer->replacedClasses);
        }

        $this->filesystem->put($targetFile, $contents);
    }

    /**
     * @param Package $package
     * @param $autoloader
     *
     * @return void
     */
    public function replacePackageByAutoloader(Package $package, Composer\Autoload\Autoloader $autoloader): void
    {
        if ($autoloader instanceof NamespaceAutoloader) {
            $source_path = $this->workingDir . $this->targetDir
                           . str_replace('\\', DIRECTORY_SEPARATOR, $autoloader->namespace)
                           . DIRECTORY_SEPARATOR;
            $this->replaceInDirectory($autoloader, $source_path);
        } elseif ($autoloader instanceof Classmap) {
            $finder = new Finder();
            $source_path = $this->workingDir . $this->config->classmap_directory . DIRECTORY_SEPARATOR
                           . $package->config->name;
            $finder->files()->in($source_path);

            foreach ($finder as $foundFile) {
                $targetFile = $foundFile->getRealPath();

                if ('.php' == substr($targetFile, -4, 4)) {
                    $this->replaceInFile($targetFile, $autoloader);
                }
            }
        }
    }

    /**
     * @param $autoloader
     * @param $directory
     *
     * @return void
     */
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
                } catch (FileNotFoundException $e) {
                    continue;
                }

                if (empty($contents) || false === $contents) {
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
                }

                $this->filesystem->put($targetFile, $contents);
            }
        }
    }

    /**
     * @param $autoloader
     * @param $directory
     *
     * @return void
     */
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
     *
     * @param Package $package
     * @param Package $parent
     *
     * @return void
     */
    public function replaceParentPackage(Package $package, Package $parent): void
    {
        foreach ($parent->autoloaders as $parentAutoloader) {
            foreach ($package->autoloaders as $autoloader) {
                if ($parentAutoloader instanceof NamespaceAutoloader) {
                    $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $parentAutoloader->namespace);
                    $directory = $this->workingDir . $this->config->dep_directory . $namespace
                                 . DIRECTORY_SEPARATOR;

                    if ($autoloader instanceof NamespaceAutoloader) {
                        $this->replaceInDirectory($autoloader, $directory);
                    } else {
                        $directory = str_replace($this->workingDir, '', $directory);
                        $this->replaceParentClassesInDirectory($directory);
                    }
                } else {
                    $directory = $this->workingDir . $this->config->classmap_directory . $parent->config->name;

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
}
