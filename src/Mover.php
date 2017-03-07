<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Mover
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var \stdClass */
    protected $config;

    public function __construct( $workingDir, $config )
    {
        $this->workingDir = $workingDir;
        $this->targetDir = $config->dep_directory;
        $this->config = $config;
    }

    public function deleteTargetDir()
    {
        $filesystem = new Filesystem(new Local($this->workingDir));
        $filesystem->deleteDir($this->targetDir);
    }

    public function movePackage(Package $package)
    {
        $finder = new Finder();
        $filesystem = new Filesystem(new Local($this->workingDir));

        foreach( $package->autoloaders as $autoloader ) {
            foreach ($autoloader->paths as $path) {
                $source_path = $this->workingDir . '/vendor/' . $package->config->name . '/' . $path;

                $finder->files()->in($source_path);

                foreach ($finder as $file) {
                    if (is_a($autoloader, NamespaceAutoloader::class)) {
                        $namespacePath = $autoloader->getNamespacePath();
                        $targetFile = str_replace($this->workingDir, $this->config->dep_directory . $namespacePath, $file->getRealPath());
                    } else {
                        $targetFile = str_replace($this->workingDir, $this->config->classmap_directory, $file->getRealPath());
                    }

                    $targetFile = str_replace('/vendor/' . $package->config->name . '/' . $path, '', $targetFile);

                    $filesystem->copy(
                        str_replace($this->workingDir, '', $file->getRealPath()),
                        $targetFile
                    );

                    if ('.php' == substr($targetFile, '-4', 4)) {
                        $contents = $filesystem->read($targetFile);

                        if (is_a($autoloader, NamespaceAutoloader::class)) {
                            $replacer = new NamespaceReplacer();
                            $replacer->dep_namespace = $this->config->dep_namespace;
                        } else {
                            $replacer = new ClassmapReplacer();
                        }

                        $replacer->setAutoloader($autoloader);

                        $contents = $replacer->replace($contents);
                        $filesystem->put($targetFile, $contents);
                    }
                }
            }
        }
    }
}