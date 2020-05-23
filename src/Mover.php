<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Package;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use CoenJacobs\Mozart\Composer\Config;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Mover
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var Config */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    /** @var array */
    protected $movedPackages = [];

    public function __construct($workingDir, Config $config)
    {
        $this->config = $config;
        $this->workingDir = $workingDir;
        $this->targetDir = $this->config->get('dep_directory');

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    public function deleteTargetDirs()
    {
        $this->filesystem->deleteDir($this->config->get('dep_directory'));
        $this->filesystem->createDir($this->config->get('dep_directory'));
        $this->filesystem->deleteDir($this->config->get('classmap_directory'));
        $this->filesystem->createDir($this->config->get('classmap_directory'));
    }

    public function movePackage(Package $package)
    {
        if (in_array($package->config->get('name'), $this->movedPackages)) {
            return;
        }

        foreach ($package->autoloaders as $autoloader) {
            if ($autoloader instanceof NamespaceAutoloader) {
                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . '/vendor/' . $package->config->get('name') . '/' . $path;

                    $finder->files()->in($source_path);

                    foreach ($finder as $file) {
                        $this->moveFile($package, $autoloader, $file, $path);
                    }
                }
            } elseif ($autoloader instanceof Classmap) {
                $finder = new Finder();

                foreach ($autoloader->files as $file) {
                    $source_path = $this->workingDir . '/vendor/' . $package->config->get('name');
                    $finder->files()->name($file)->in($source_path);

                    foreach ($finder as $foundFile) {
                        $this->moveFile($package, $autoloader, $foundFile);
                    }
                }

                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . '/vendor/' . $package->config->get('name') . '/' . $path;

                    $finder->files()->in($source_path);

                    foreach ($finder as $file) {
                        $this->moveFile($package, $autoloader, $file);
                    }
                }
            }

            $this->movedPackages[] = $package->config->get('name');
        }

        if ($this->config->get('delete_vendor_directories')) {
            $this->deletePackageVendorDirectories();
        }
    }

    /**
     * @param Package $package
     * @param Autoloader $autoloader
     * @param SplFileInfo $file
     * @param string $path
     * @return string
     */
    public function moveFile(Package $package, $autoloader, $file, $path = '')
    {
        if ($autoloader instanceof NamespaceAutoloader) {
            $namespacePath = $autoloader->getNamespacePath();
            $replaceWith = $this->config->get('dep_directory') . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getPathname());

            $packageVendorPath = '/vendor/' . $package->config->get('name') . '/' . $path;
            $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
            $targetFile = str_replace($packageVendorPath, '', $targetFile);
        } else {
            $namespacePath = $package->config->get('name');
            $replaceWith = $this->config->get('classmap_directory') . '/' . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getPathname());

            $packageVendorPath = '/vendor/' . $package->config->get('name') . '/';
            $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
            $targetFile = str_replace($packageVendorPath, DIRECTORY_SEPARATOR, $targetFile);
        }

        $this->filesystem->copy(
            str_replace($this->workingDir, '', $file->getPathname()),
            $targetFile
        );

        return $targetFile;
    }

    /**
     * Deletes all the packages that are moved from the /vendor/ directory to
     * prevent packages that are prefixed/namespaced from being used or
     * influencing the output of the code. They just need to be gone.
     */
    protected function deletePackageVendorDirectories()
    {
        foreach ($this->movedPackages as $movedPackage) {
            $packageDir = '/vendor/' . $movedPackage;
            if (is_link($packageDir)) {
                continue;
            }
            $this->filesystem->deleteDir($packageDir);
        }
    }
}
