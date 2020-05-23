<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
use League\Flysystem\Adapter\Local;
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

    public function replacePackage(Package $package)
    {
        foreach ($package->autoloaders as $autoloader) {
            $this->replacePackageByAutoloader($package, $autoloader);
        }
    }

    /**
     * @param $targetFile
     * @param $autoloader
     */
    public function replaceInFile($targetFile, $autoloader)
    {
        $targetFile = str_replace($this->workingDir, '', $targetFile);
        $contents = $this->filesystem->read($targetFile);

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
     */
    public function replacePackageByAutoloader(Package $package, $autoloader)
    {
        if ($autoloader instanceof NamespaceAutoloader) {
            $source_path = $this->workingDir . $this->targetDir . str_replace('\\', '/', $autoloader->namespace) . '/';
            $this->replaceInDirectory($autoloader, $source_path);
        } elseif ($autoloader instanceof Classmap) {
            $finder = new Finder();
            $source_path = $this->workingDir . $this->config->classmap_directory . '/' . $package->config->name;
            $finder->files()->in($source_path);

            foreach ($finder as $foundFile) {
                $targetFile = $foundFile->getRealPath();

                if ('.php' == substr($targetFile, '-4', 4)) {
                    $this->replaceInFile($targetFile, $autoloader);
                }
            }
        }
    }

    /**
     * @param $autoloader
     * @param $directory
     */
    public function replaceParentClassesInDirectory($directory)
    {
        $directory = trim($directory, '//');
        $finder = new Finder();
        $finder->files()->in($directory);

        $replacedClasses = $this->replacedClasses;

        foreach ($finder as $file) {
            $targetFile = $file->getPathName();

            if ('.php' == substr($targetFile, '-4', 4)) {
                $contents = $this->filesystem->read($targetFile);

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
     */
    public function replaceInDirectory($autoloader, $directory)
    {
        $finder = new Finder();
        $finder->files()->in($directory);

        foreach ($finder as $file) {
            $targetFile = $file->getPathName();

            if ('.php' == substr($targetFile, '-4', 4)) {
                $this->replaceInFile($targetFile, $autoloader);
            }
        }
    }

    public function replaceParentPackage(Package $package, $parent)
    {
        if ($parent !== null) {
            // Replace everything in parent, based on the dependencies
            foreach ($parent->autoloaders as $parentAutoloader) {
                foreach ($package->autoloaders as $autoloader) {
                    if ($parentAutoloader instanceof NamespaceAutoloader) {
                        $namespace = str_replace('\\', '/', $parentAutoloader->namespace);
                        $directory = $this->workingDir . $this->config->dep_directory . $namespace . '/';

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

        if (! empty($package->dependencies)) {
            foreach ($package->dependencies as $dependency) {
                $this->replaceParentPackage($dependency, $package);
            }
        }
    }
}
