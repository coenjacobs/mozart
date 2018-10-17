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

class Synchronizer
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var \stdClass */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    public function __construct($workingDir, $config)
    {
        $this->workingDir = $workingDir;
        $this->targetDir = $config->dep_directory;
        $this->config = $config;

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }


    public function syncMovedPackageWithDependency(Package $package, Package $dependency)
    {
        $finder = new Finder();

        foreach ($package->autoloaders as $autoloader) {
            if ($autoloader instanceof NamespaceAutoloader) {
                $source_path = $this->workingDir . $this->targetDir . $autoloader->getNamespacePath();
                $finder->files()->in($source_path);

                foreach ($finder as $file) {
                    if ('.php' == substr($file, '-4', 4)) {
                        foreach ($dependency->autoloaders as $dep_autoloader) {
                            $targetFile = str_replace($this->workingDir, '', $file->getRealPath());
                            $this->replaceInFile($targetFile, $dep_autoloader);
                        }
                    }
                }
            }

            if ($autoloader instanceof Classmap) {
                $finder = new Finder();
                $source_path = $this->workingDir . $this->config->classmap_directory . $package->config->name;
                $finder->files()->in($source_path);

                foreach ($finder as $foundFile) {
                    if ('.php' == substr($foundFile, '-4', 4)) {
                        foreach ($dependency->autoloaders as $dep_autoloader) {
                            $targetFile = str_replace($this->workingDir, '', $foundFile->getRealPath());
                            $this->replaceInFile($targetFile, $dep_autoloader);
                        }
                    }
                }
            }
        }
    }

    /**
    * @param $targetFile
    * @param $autoloader
    */
    public function replaceInFile($targetFile, $autoloader)
    {
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

        $this->filesystem->put($targetFile, $contents);
    }
}
