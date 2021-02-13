<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Autoload\Psr0;
use CoenJacobs\Mozart\Composer\Autoload\Psr4;
use CoenJacobs\Mozart\Composer\Package;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Mover
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var \stdClass */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    /** @var array */
    protected $movedPackages = [];

    public function __construct($workingDir, $config)
    {
        $this->workingDir = $workingDir;
        $this->targetDir = $config->dep_directory;
        $this->config = $config;

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    /**
     * Create the required `dep_directory` and `classmap_directory` and delete targetDirs of packages about to be moved.
     *
     * @param Package[] $packages The packages that, in the next step, will be moved.
     *
     * @return void
     */
    public function deleteTargetDirs($packages): void
    {
        $this->filesystem->createDir($this->config->dep_directory);

        $this->filesystem->createDir($this->config->classmap_directory);

        foreach ($packages as $package) {
            $this->deleteDepTargetDirs($package);
        }
    }

    /**
     * Delete the directories about to be used for packages earmarked for Mozart namespacing.
     *
     * @visibility private to allow recursion through packages and subpackages.
     *
     * @param Package $package
     *
     * @return void
     */
    private function deleteDepTargetDirs($package): void
    {
        foreach ($package->autoloaders as $packageAutoloader) {
            $autoloaderType = get_class($packageAutoloader);

            switch ($autoloaderType) {
                case Psr0::class:
                case Psr4::class:
                    $outputDir = $this->config->dep_directory . $packageAutoloader->namespace;
                    $outputDir = str_replace('\\', DIRECTORY_SEPARATOR, $outputDir);
                    $this->filesystem->deleteDir($outputDir);
                    break;
                case Classmap::class:
                    $outputDir = $this->config->classmap_directory . $package->config->name;
                    $outputDir = str_replace('\\', DIRECTORY_SEPARATOR, $outputDir);
                    $this->filesystem->deleteDir($outputDir);
                    break;
            }
        }

        foreach ($package->dependencies as $subPackage) {
            $this->deleteDepTargetDirs($subPackage);
        }
    }

    public function deleteEmptyDirs(): void
    {
        if (count($this->filesystem->listContents($this->config->dep_directory, true)) === 0) {
            $this->filesystem->deleteDir($this->config->dep_directory);
        }

        if (count($this->filesystem->listContents($this->config->classmap_directory, true)) === 0) {
            $this->filesystem->deleteDir($this->config->classmap_directory);
        }
    }
    
    /**
     * @return void
     */
    public function movePackage(Package $package)
    {
        if (in_array($package->config->name, $this->movedPackages)) {
            return;
        }

        foreach ($package->autoloaders as $autoloader) {
            if ($autoloader instanceof NamespaceAutoloader) {
                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR
                                   . $package->config->name . DIRECTORY_SEPARATOR . $path;

                    $source_path = str_replace('/', DIRECTORY_SEPARATOR, $source_path);

                    $finder->files()->in($source_path);

                    foreach ($finder as $file) {
                        $this->moveFile($package, $autoloader, $file, $path);
                    }
                }
            } elseif ($autoloader instanceof Classmap) {
                $finder = new Finder();

                $files_to_move = array();

                foreach ($autoloader->files as $file) {
                    $source_path = $this->workingDir . DIRECTORY_SEPARATOR . 'vendor'
                                   . DIRECTORY_SEPARATOR . $package->config->name;
                    $finder->files()->name($file)->in($source_path);

                    foreach ($finder as $foundFile) {
                        $filePath = $foundFile->getRealPath();
                        $files_to_move[ $filePath ] = $foundFile;
                    }
                }

                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . DIRECTORY_SEPARATOR . 'vendor'
                                   . DIRECTORY_SEPARATOR . $package->config->name . DIRECTORY_SEPARATOR . $path;

                    $finder->files()->in($source_path);

                    foreach ($finder as $foundFile) {
                        $filePath = $foundFile->getRealPath();
                        $files_to_move[ $filePath ] = $foundFile;
                    }
                }

                foreach ($files_to_move as $foundFile) {
                    $this->moveFile($package, $autoloader, $foundFile);
                }
            }

            if (!in_array($package->config->name, $this->movedPackages)) {
                $this->movedPackages[] = $package->config->name;
            }
        }

        if (!isset($this->config->delete_vendor_directories) || $this->config->delete_vendor_directories === true) {
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
            $replaceWith = $this->config->dep_directory . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getPathname());

            $packageVendorPath = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $package->config->name
                                 . DIRECTORY_SEPARATOR . $path;
            $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
            $targetFile = str_replace($packageVendorPath, '', $targetFile);
        } else {
            $namespacePath = $package->config->name;
            $replaceWith = $this->config->classmap_directory . DIRECTORY_SEPARATOR . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getPathname());

            $packageVendorPath = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $package->config->name
                                 . DIRECTORY_SEPARATOR;
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
     *
     * @return void
     */
    protected function deletePackageVendorDirectories(): void
    {
        foreach ($this->movedPackages as $movedPackage) {
            $packageDir = 'vendor' . DIRECTORY_SEPARATOR . $movedPackage;
            if (!is_dir($packageDir) || is_link($packageDir)) {
                continue;
            }

            $this->filesystem->deleteDir($packageDir);

            //Delete parent directory too if it became empty
            //(because that package was the only one from that vendor)
            $parentDir = dirname($packageDir);
            if ($this->dirIsEmpty($parentDir)) {
                $this->filesystem->deleteDir($parentDir);
            }
        }
    }

    private function dirIsEmpty(string $dir): bool
    {
        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        return iterator_count($di) === 0;
    }
}
