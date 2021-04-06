<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Autoload\Psr0;
use CoenJacobs\Mozart\Composer\Autoload\Psr4;
use CoenJacobs\Mozart\Composer\ComposerPackageConfig;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use CoenJacobs\Mozart\Composer\MozartConfig;
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

    /** @var MozartConfig */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    /** @var array */
    protected $movedPackages = [];

    public function __construct($workingDir, MozartConfig $config)
    {
        $this->config = $config;
        $this->workingDir = $workingDir;
        $this->targetDir = $this->config->getDepDirectory();

        $this->workingDir = $workingDir;

        $this->dep_directory = $this->clean($config->getDepDirectory());
        $this->classmap_directory = $this->clean($config->getClassmapDirectory());

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    /**
     * Create the required `dep_directory` and `classmap_directory` and delete targetDirs of packages about to be moved.
     *
     * @param ComposerPackageConfig[] $packages The packages that, in the next step, will be moved.
     *
     * @return void
     */
    public function deleteTargetDirs($packages): void
    {

        $this->filesystem->createDir($this->config->getDepDirectory());
        $this->filesystem->createDir($this->config->getClassmapDirectory());

        foreach ($packages as $package) {
            $this->deleteDepTargetDirs($package);
        }
    }

    /**
     * Delete the directories about to be used for packages earmarked for Mozart namespacing.
     *
     * @visibility protected to allow recursion through packages and subpackages.
     *
     * @param ComposerPackageConfig $package
     *
     * @return void
     */
    protected function deleteDepTargetDirs($package): void
    {
        foreach ($package->getAutoloaders() as $packageAutoloader) {
            $autoloaderType = get_class($packageAutoloader);

            switch ($autoloaderType) {
                case Psr0::class:
                case Psr4::class:
                    $outputDir = $this->dep_directory . DIRECTORY_SEPARATOR .
                                 $this->clean($packageAutoloader->getSearchNamespace());
                    $this->filesystem->deleteDir($outputDir);
                    break;
                case Classmap::class:
                    $outputDir = $this->classmap_directory . DIRECTORY_SEPARATOR . $this->clean($package->getName());
                    $this->filesystem->deleteDir($outputDir);
                    break;
            }
        }

        foreach ($package->getDependencies() as $subPackage) {
            $this->deleteDepTargetDirs($subPackage);
        }
    }

    public function deleteEmptyDirs(): void
    {
        if (count($this->filesystem->listContents($this->dep_directory, true)) === 0) {
            $this->filesystem->deleteDir($this->dep_directory);
        }

        if (count($this->filesystem->listContents($this->classmap_directory, true)) === 0) {
            $this->filesystem->deleteDir($this->classmap_directory);
        }
    }
    
    /**
     * @return void
     */
    public function movePackage(ComposerPackageConfig $package)
    {
        if (in_array($package->getName(), $this->movedPackages)) {
            return;
        }

        foreach ($package->getAutoloaders() as $autoloader) {
            $files_to_move = array();

            if ($autoloader instanceof NamespaceAutoloader) {
                $finder = new Finder();

                foreach ($autoloader->getPaths() as $path) {
                    $source_path = DIRECTORY_SEPARATOR . $this->clean($package->getPath() . DIRECTORY_SEPARATOR . $path);

                    $finder->files()->in($source_path);

                    foreach ($finder as $foundFile) {
                        $filePath = $foundFile->getRealPath();
                        $files_to_move[ $filePath ] = $foundFile;
                    }

                    foreach ($files_to_move as $foundFile) {
                        $this->moveFile($package, $autoloader, $foundFile, $path);
                    }
                }
            } elseif ($autoloader instanceof Classmap) {
                $finder = new Finder();


                foreach ($autoloader->getFiles() as $file) {
                    $source_path = DIRECTORY_SEPARATOR . $this->clean($package->getPath());

                    $finder->files()->name($file)->in($source_path);

                    foreach ($finder as $foundFile) {
                        $filePath = $foundFile->getRealPath();
                        $files_to_move[ $filePath ] = $foundFile;
                    }
                }

                $finder = new Finder();

                foreach ($autoloader->getPaths() as $path) {
                    $source_path = DIRECTORY_SEPARATOR . $this->clean($package->getPath() . DIRECTORY_SEPARATOR . $path);

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


            if (!in_array($package->getName(), $this->movedPackages)) {
                $this->movedPackages[] = $package->getName();
            }
        }

        if ($this->config->isDeleteVendorDirectories()) {
            $this->deletePackageVendorDirectories();
        }
    }

    /**
     * @param ComposerPackageConfig $package
     * @param Autoloader $autoloader
     * @param SplFileInfo $file
     * @param string $path
     *
     * @return string
     */
    public function moveFile(ComposerPackageConfig $package, $autoloader, $file, $path = '')
    {
        // The relative path to the file from the project root.
        $sourceFileRelativePath = $this->clean(str_replace($this->workingDir, '', $file->getPathname()));

        $packagePath = $this->clean(str_replace($this->workingDir, '', $package->getPath()));

        if ($autoloader instanceof NamespaceAutoloader) {
            $namespacePath = $this->clean($autoloader->getNamespacePath());

            // TODO: Should $path come from the NameSpaceAutoloader object?
            $sourceVendorPath = $this->clean($packagePath . DIRECTORY_SEPARATOR . $path);

            $destinationMozartPath = $this->dep_directory . DIRECTORY_SEPARATOR . $namespacePath;

            $targetFileRelativePath = str_ireplace($sourceVendorPath, $destinationMozartPath, $sourceFileRelativePath);
        } else {
            $packageName = $this->clean($package->getName());

            $destinationMozartPath = $this->classmap_directory . DIRECTORY_SEPARATOR . $packageName;

            $targetFileRelativePath = str_ireplace($packagePath, $destinationMozartPath, $sourceFileRelativePath);
        }

        $this->filesystem->copy($sourceFileRelativePath, $targetFileRelativePath);

        return $targetFileRelativePath;
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

    protected function dirIsEmpty(string $dir): bool
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
