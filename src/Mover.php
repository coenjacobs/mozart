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

    /** @var array */
    protected $replacedClasses = [];

    public function __construct( $workingDir, $config )
    {
        $this->workingDir = $workingDir;
        $this->targetDir = $config->dep_directory;
        $this->config = $config;
    }

    public function deleteTargetDirs()
    {
        $filesystem = new Filesystem(new Local($this->workingDir));
        $filesystem->deleteDir($this->config->dep_directory);
        $filesystem->deleteDir($this->config->classmap_directory);
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
                    if ($autoloader instanceof NamespaceAutoloader::class) {
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

                        if ($autoloader instanceof NamespaceAutoloader::class) {
                            $replacer = new NamespaceReplacer();
                            $replacer->dep_namespace = $this->config->dep_namespace;
                        } else {
                            $replacer = new ClassmapReplacer();
                            $replacer->classmap_prefix = $this->config->classmap_prefix;
                        }

                        $replacer->setAutoloader($autoloader);
                        $contents = $replacer->replace($contents);

                        if ( $replacer instanceof ClassmapReplacer::class) {
                            $this->replacedClasses = array_merge($this->replacedClasses, $replacer->replacedClasses);
                        }

                        $filesystem->put($targetFile, $contents);
                    }
                }
            }
        }
    }

    public function replaceClassmapNames()
    {
        $classmap_path = $this->workingDir . $this->config->classmap_directory;
        $finder = new Finder();
        $finder->files()->in($classmap_path);

        $filesystem = new Filesystem(new Local($this->workingDir));

        foreach ($finder as $file) {
            $file_path = str_replace($this->workingDir, '', $file->getRealPath());
            $contents = $filesystem->read($file_path);

            foreach( $this->replacedClasses as $original => $replacement ) {
                $contents = preg_replace_callback(
                    '/\W('.$original.')(?:\(|\:\:)/U',
                    function ($matches) use ($replacement) {
                        return str_replace($matches[1], $replacement, $matches[0]);
                    },
                    $contents
                );
            }

            $filesystem->put($file_path, $contents);
        }
    }
}