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
use stdClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Mover
{
    /**
     * The only path variable with a leading slash.
     *
     * @var string
     */
    protected $workingDir;

    /** @var string */
    protected $dep_directory;

    /** @var string */
    protected $classmap_directory;

    /** @var stdClass */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    /** @var array */
    protected $movedPackages = [];

    public function __construct($workingDir, $config)
    {
        $this->config = $config;
        
        $this->workingDir = DIRECTORY_SEPARATOR . $this->clean($workingDir);

        $this->dep_directory = $this->clean($config->dep_directory);
        $this->classmap_directory = $this->clean($config->classmap_directory);

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    /**
     * Create the required `dep_directory` and `classmap_directory` and delete targetDirs of packages about to be moved.
     *
     * @param Package[] $packages The packages that, in the next step, will be moved.
     */
    public function deleteTargetDirs($packages)
    {
        $this->filesystem->createDir($this->dep_directory);

        $this->filesystem->createDir($this->classmap_directory);

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
     */
    private function deleteDepTargetDirs($package)
    {
        foreach ($package->autoloaders as $packageAutoloader) {
            $autoloaderType = get_class($packageAutoloader);

            switch ($autoloaderType) {
                case Psr0::class:
                case Psr4::class:
                    $outputDir = $this->dep_directory . DIRECTORY_SEPARATOR .
                                 $this->clean($packageAutoloader->namespace);
                    $this->filesystem->deleteDir($outputDir);
                    break;
                case Classmap::class:
                    $outputDir = $this->classmap_directory . DIRECTORY_SEPARATOR . $this->clean($package->config->name);
                    $this->filesystem->deleteDir($outputDir);
                    break;
            }
        }

        foreach ($package->dependencies as $subPackage) {
            $this->deleteDepTargetDirs($subPackage);
        }
    }

    public function deleteEmptyDirs()
    {
        if (count($this->filesystem->listContents($this->dep_directory, true)) === 0) {
            $this->filesystem->deleteDir($this->dep_directory);
        }

        if (count($this->filesystem->listContents($this->classmap_directory, true)) === 0) {
            $this->filesystem->deleteDir($this->classmap_directory);
        }
    }
    
    public function movePackage(Package $package)
    {
        if (in_array($package->config->name, $this->movedPackages)) {
            return;
        }

        foreach ($package->autoloaders as $autoloader) {
            if ($autoloader instanceof NamespaceAutoloader) {
                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = DIRECTORY_SEPARATOR . $this->clean($package->path . DIRECTORY_SEPARATOR . $path);

                    $finder->files()->in($source_path);

                    foreach ($finder as $file) {
                        $this->moveFile($package, $autoloader, $file, $path);
                    }
                }
            } elseif ($autoloader instanceof Classmap) {
                $finder = new Finder();

                foreach ($autoloader->files as $file) {
                    $source_path = DIRECTORY_SEPARATOR . $this->clean($package->path);

                    $finder->files()->name($file)->in($source_path);

                    foreach ($finder as $foundFile) {
                        $this->moveFile($package, $autoloader, $foundFile);
                    }
                }

                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = DIRECTORY_SEPARATOR . $this->clean($package->path . DIRECTORY_SEPARATOR . $path);

                    $finder->files()->in($source_path);

                    foreach ($finder as $file) {
                        $this->moveFile($package, $autoloader, $file);
                    }
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
        // The relative path to the file from the project root.
        $sourceFilePath = $this->clean(str_replace($this->workingDir, '', $file->getPathname()));

        $packagePath = $this->clean(str_replace($this->workingDir, '', $package->path));

        if ($autoloader instanceof NamespaceAutoloader) {
            $namespacePath = $this->clean($autoloader->getNamespacePath());

            // TODO: Should $path come from the NameSpaceAutoloader object?
            $sourceVendorPath = $this->clean($packagePath . DIRECTORY_SEPARATOR . $path);

            $destinationMozartPath = $this->dep_directory . DIRECTORY_SEPARATOR . $namespacePath;

            $targetFilePath = str_ireplace($sourceVendorPath, $destinationMozartPath, $sourceFilePath);
        } else {
            $packageName = $this->clean($package->config->name);

            $destinationMozartPath = $this->classmap_directory . DIRECTORY_SEPARATOR . $packageName;

            $targetFilePath = str_ireplace($packagePath, $destinationMozartPath, $sourceFilePath);
        }

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        return $targetFilePath;
    }

    /**
     * Deletes all the packages that are moved from the /vendor/ directory to
     * prevent packages that are prefixed/namespaced from being used or
     * influencing the output of the code. They just need to be gone.
     */
    protected function deletePackageVendorDirectories()
    {
        foreach ($this->movedPackages as $movedPackage) {
            $packageDir = 'vendor' . DIRECTORY_SEPARATOR . $this->clean($movedPackage);
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

    private function dirIsEmpty($dir)
    {
        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        return iterator_count($di) === 0;
    }

    /**
     * For Windows & Unix file paths' compatibility.
     *
     *  * Removes duplicate `\` and `/`.
     *  * Trims them from each end.
     *  * Replaces them with the OS agnostic DIRECTORY_SEPARATOR.
     *
     * @param string $path A full or partial filepath.
     *
     * @return string
     */
    protected function clean($path)
    {
        return trim(preg_replace('/[\/\\\\]+/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
