<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Autoload\Psr0;
use CoenJacobs\Mozart\Composer\Autoload\Psr4;
use CoenJacobs\Mozart\Composer\ComposerPackageConfig;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use CoenJacobs\Mozart\Composer\MozartConfig;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Mover
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

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
     * @visibility private to allow recursion through packages and subpackages.
     *
     * @param ComposerPackageConfig $package
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
                    $outputDir = $this->config->getDepDirectory() . $packageAutoloader->namespace;
                    $outputDir = str_replace('\\', DIRECTORY_SEPARATOR, $outputDir);
                    $this->filesystem->deleteDir($outputDir);
                    break;
                case Classmap::class:
                    $outputDir = $this->config->getClassmapDirectory() . $package->config->name;
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
        if (count($this->filesystem->listContents($this->config->getDepDirectory(), true)) === 0) {
            $this->filesystem->deleteDir($this->config->getDepDirectory());
        }

        if (count($this->filesystem->listContents($this->config->getClassmapDirectory(), true)) === 0) {
            $this->filesystem->deleteDir($this->config->getClassmapDirectory());
        }
    }
    
    /**
     * @return void
     */
    public function movePackage(ComposerPackageConfig $package)
    {
        if (in_array($package->config->name, $this->movedPackages)) {
            return;
        }

        foreach ($package->autoloaders as $autoloader) {
            $files_to_move = array();

            if ($autoloader instanceof NamespaceAutoloader) {
                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . 'vendor' . DIRECTORY_SEPARATOR
                                   . $package->config->name . DIRECTORY_SEPARATOR . $path;

                    $source_path = str_replace('/', DIRECTORY_SEPARATOR, $source_path);

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


                foreach ($autoloader->files as $file) {
                    $source_path = $this->workingDir . 'vendor'
                                   . DIRECTORY_SEPARATOR . $package->config->name;

                    $finder->files()->name($file)->in($source_path);

                    foreach ($finder as $foundFile) {
                        $filePath = $foundFile->getRealPath();
                        $files_to_move[ $filePath ] = $foundFile;
                    }
                }

                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . 'vendor'
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
        $sourceFileAbsolutePath = $file->getPathname();
        if ($autoloader instanceof NamespaceAutoloader) {
            $namespacePath = strtolower($autoloader->getNamespacePath());
            $replaceWith = $this->config->getDepDirectory() . $namespacePath;

            $findString = $this->workingDir . 'vendor' . DIRECTORY_SEPARATOR . $namespacePath . $path;

            $targetFileRelativePath = str_replace($findString, $replaceWith, $sourceFileAbsolutePath);


            $packageVendorPath = $this->workingDir . 'vendor' . DIRECTORY_SEPARATOR . $package->config->name
                                 . DIRECTORY_SEPARATOR . $path;
            $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
            $targetFileRelativePath = str_replace($packageVendorPath, '', $targetFileRelativePath);
        } else {
            $namespacePath = $package->config->name;
            $replaceWith = $this->config->getClassmapDirectory() . $namespacePath;
            $targetFileRelativePath = str_replace($this->workingDir, $replaceWith, $file->getPathname());

            $packageVendorPath =  'vendor' . DIRECTORY_SEPARATOR . $package->config->name
                                 . DIRECTORY_SEPARATOR;

            $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
        }

        $sourceFileRelativePath = str_replace($this->workingDir, '', $sourceFileAbsolutePath);
        $targetFileRelativePath = str_replace($packageVendorPath, DIRECTORY_SEPARATOR, $targetFileRelativePath);

        try {
            $this->filesystem->copy($sourceFileRelativePath, $targetFileRelativePath);
        } catch (FileNotFoundException $e) {
            throw $e;
        } catch (FileExistsException $e) {
            if (md5_file($sourceFileAbsolutePath) === md5_file($this->workingDir . $targetFileRelativePath)) {
                return $targetFileRelativePath;
            } else {
                throw $e;
            }
        }

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
