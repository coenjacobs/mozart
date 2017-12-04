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

    /** @var Filesystem */
    protected $filesystem;

    public function __construct($workingDir, $config)
    {
        $this->workingDir = $workingDir;
        $this->targetDir = $config->dep_directory;
        $this->config = $config;

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    public function deleteTargetDirs()
    {
        $this->filesystem->deleteDir($this->config->dep_directory);
        $this->filesystem->createDir($this->config->dep_directory);
        $this->filesystem->deleteDir($this->config->classmap_directory);
        $this->filesystem->createDir($this->config->classmap_directory);
    }

    public function movePackage(Package $package)
    {
        $finder = new Finder();

        foreach ($package->autoloaders as $autoloader) {
            foreach ($autoloader->paths as $path) {
                $source_path = $this->workingDir . '/vendor/' . $package->config->name . '/' . $path;

                $finder->files()->in($source_path);

                foreach ($finder as $file) {
                    $targetFile = $this->moveFile($package, $autoloader, $file, $path);

                    if ('.php' == substr($targetFile, '-4', 4)) {
                        $this->replaceInFile($targetFile, $autoloader);
                    }
                }
            }

            if ($autoloader instanceof Classmap && ! empty($autoloader->files)) {
                foreach ($autoloader->files as $file) {
                    $finder = new Finder();
                    $source_path = $this->workingDir . '/vendor/' . $package->config->name;
                    $finder->files()->name($file)->in($source_path);

                    foreach ($finder as $foundFile) {
                        $targetFile = $this->moveFile($package, $autoloader, $foundFile);

                        if ('.php' == substr($targetFile, '-4', 4)) {
                            $this->replaceInFile($targetFile, $autoloader);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Package $package
     * @param $autoloader
     * @param $file
     * @param $path
     * @return mixed
     */
    public function moveFile(Package $package, $autoloader, $file, $path = '')   {
        if ($autoloader instanceof NamespaceAutoloader) {
            $namespacePath = $autoloader->getNamespacePath();
            $replaceWith = $this->config->dep_directory . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getRealPath());
            $targetFile = str_replace('/vendor/' . $package->config->name . '/' . $path, '', $targetFile);
        } else {
            $namespacePath = $package->config->name;
            $replaceWith = $this->config->classmap_directory . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getRealPath());
            $targetFile = str_replace('/vendor/' . $package->config->name . '/', '/', $targetFile);
        }

        $this->filesystem->copy(
            str_replace($this->workingDir, '', $file->getRealPath()),
            $targetFile
        );

        return $targetFile;
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

            foreach ($this->replacedClasses as $original => $replacement) {
                $contents = preg_replace_callback(
                    '/\W(?<!(trait)\ )(?<!(interface)\ )(?<!(class)\ )('.$original.')\W/U',
                    function ($matches) use ($replacement) {
                        return str_replace($matches[4], $replacement, $matches[0]);
                    },
                    $contents
                );
            }

            $filesystem->put($file_path, $contents);
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

        if ($replacer instanceof ClassmapReplacer) {
            $this->replacedClasses = array_merge($this->replacedClasses, $replacer->replacedClasses);
        }

        $this->filesystem->put($targetFile, $contents);
    }
}
