<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr0;
use CoenJacobs\Mozart\Config\Psr4;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Mover
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var Mozart */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    /** @var array<string> */
    protected $movedPackages = [];

    public function __construct(string $workingDir, Mozart $config)
    {
        $this->config = $config;
        $this->workingDir = $workingDir;
        $this->targetDir = $this->config->getDepDirectory();

        $adapter = new LocalFilesystemAdapter(
            $this->workingDir
        );

        // The FilesystemOperator
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * Create the required `dep_directory` and `classmap_directory` and delete targetDirs of packages about to be moved.
     *
     * @param Package[] $packages The packages that, in the next step, will be moved.
     */
    public function deleteTargetDirs($packages): void
    {
        $this->filesystem->createDirectory($this->config->getDepDirectory());
        $this->filesystem->createDirectory($this->config->getClassmapDirectory());

        foreach ($packages as $package) {
            $this->deleteDepTargetDirs($package);
        }
    }

    /**
     * Delete the directories about to be used for packages earmarked for Mozart namespacing.
     *
     * @visibility private to allow recursion through packages and subpackages.
     */
    private function deleteDepTargetDirs(Package $package): void
    {
        foreach ($package->getAutoloaders() as $packageAutoloader) {
            $autoloaderType = get_class($packageAutoloader);

            switch ($autoloaderType) {
                case Psr0::class:
                case Psr4::class:
                    $outputDir = $this->config->getDepDirectory() . $packageAutoloader->namespace;
                    $outputDir = str_replace('\\', DIRECTORY_SEPARATOR, $outputDir);
                    $this->filesystem->deleteDirectory($outputDir);
                    break;
                case Classmap::class:
                    $outputDir = $this->config->getClassmapDirectory() . $package->getName();
                    $outputDir = str_replace('\\', DIRECTORY_SEPARATOR, $outputDir);
                    $this->filesystem->deleteDirectory($outputDir);
                    break;
            }
        }

        foreach ($package->getDependencies() as $subPackage) {
            $this->deleteDepTargetDirs($subPackage);
        }
    }

    public function deleteEmptyDirs(): void
    {
        if (count($this->filesystem->listContents($this->config->getDepDirectory(), true)->toArray()) === 0) {
            $this->filesystem->deleteDirectory($this->config->getDepDirectory());
        }

        if (count($this->filesystem->listContents($this->config->getClassmapDirectory(), true)->toArray()) === 0) {
            $this->filesystem->deleteDirectory($this->config->getClassmapDirectory());
        }
    }

    /**
     * @param Package[] $packages
     */
    public function movePackages($packages): void
    {
        foreach ($packages as $package) {
            $this->movePackages($package->getDependencies());
            $this->movePackage($package);
        }

        $this->deleteEmptyDirs();
    }

    public function movePackage(Package $package): void
    {
        if (in_array($package->getName(), $this->movedPackages)) {
            return;
        }

        if ($this->config->isExcludedPackage($package)) {
            return;
        }

        foreach ($package->getAutoloaders() as $autoloader) {
            if ($autoloader instanceof NamespaceAutoloader) {
                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR
                                   . $package->getName() . DIRECTORY_SEPARATOR . $path;

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
                                   . DIRECTORY_SEPARATOR . $package->getName();
                    $finder->files()->name($file)->in($source_path);

                    foreach ($finder as $foundFile) {
                        $filePath = $foundFile->getRealPath();
                        $files_to_move[ $filePath ] = $foundFile;
                    }
                }

                $finder = new Finder();

                foreach ($autoloader->paths as $path) {
                    $source_path = $this->workingDir . DIRECTORY_SEPARATOR . 'vendor'
                                   . DIRECTORY_SEPARATOR . $package->getName() . DIRECTORY_SEPARATOR . $path;

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

        if ($this->config->getDeleteVendorDirectories()) {
            $this->deletePackageVendorDirectories();
        }
    }

    public function moveFile(Package $package, Autoloader $autoloader, SplFileInfo $file, string $path = ''): string
    {
        if ($autoloader instanceof NamespaceAutoloader) {
            $namespacePath = $autoloader->getNamespacePath();
            $replaceWith = $this->config->getDepDirectory() . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getPathname());

            $packageVendorPath = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $package->getName()
                                 . DIRECTORY_SEPARATOR . $path;
            $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
            $targetFile = str_replace($packageVendorPath, '', $targetFile);
        } else {
            $namespacePath = $package->getName();
            $replaceWith = $this->config->getClassmapDirectory() . $namespacePath;
            $targetFile = str_replace($this->workingDir, $replaceWith, $file->getPathname());

            $packageVendorPath = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $package->getName()
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
     */
    protected function deletePackageVendorDirectories(): void
    {
        foreach ($this->movedPackages as $movedPackage) {
            $packageDir = 'vendor' . DIRECTORY_SEPARATOR . $movedPackage;
            if (!is_dir($packageDir) || is_link($packageDir)) {
                continue;
            }

            $this->filesystem->deleteDirectory($packageDir);

            //Delete parent directory too if it became empty
            //(because that package was the only one from that vendor)
            $parentDir = dirname($packageDir);
            if ($this->dirIsEmpty($parentDir)) {
                $this->filesystem->deleteDirectory($parentDir);
            }
        }
    }

    private function dirIsEmpty(string $dir): bool
    {
        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        return iterator_count($di) === 0;
    }
}
