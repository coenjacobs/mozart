<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr0;
use CoenJacobs\Mozart\Config\Psr4;
use Symfony\Component\Finder\SplFileInfo;

class Mover
{
    /** @var Mozart */
    protected $config;

    protected FilesHandler $files;

    /** @var array<string> */
    protected $movedPackages = [];

    /** @var array<string> */
    protected $movedFiles = [];

    public function __construct(Mozart $config)
    {
        $this->config = $config;
        $this->files = new FilesHandler($config);
    }

    /**
     * Create the required `dep_directory` and `classmap_directory` and delete targetDirs of packages about to be moved.
     *
     * @param Package[] $packages The packages that, in the next step, will be moved.
     */
    public function deleteTargetDirs($packages): void
    {
        $this->files->createDirectory($this->config->getDepDirectory());
        $this->files->createDirectory($this->config->getClassmapDirectory());

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
        foreach ($package->getAutoloaders() as $autoloader) {
            $autoloaderType = get_class($autoloader);
            $outputDir = '';

            switch ($autoloaderType) {
                case Psr0::class:
                case Psr4::class:
                    $outputDir = $autoloader->getOutputDir(
                        $this->config->getDepDirectory(),
                        $autoloader->getSearchNamespace()
                    );
                    break;
                case Classmap::class:
                    $outputDir = $autoloader->getOutputDir(
                        $this->config->getClassmapDirectory(),
                        $package->getName()
                    );
                    break;
            }

            if (empty($outputDir)) {
                continue;
            }

            $this->files->deleteDirectory($outputDir);
        }


        foreach ($package->getDependencies() as $subPackage) {
            $this->deleteDepTargetDirs($subPackage);
        }
    }

    private function deleteEmptyDirs(): void
    {
        if ($this->files->isDirectoryEmpty($this->config->getDepDirectory())) {
            $this->files->deleteDirectory($this->config->getDepDirectory());
        }

        if ($this->files->isDirectoryEmpty($this->config->getClassmapDirectory())) {
            $this->files->deleteDirectory($this->config->getClassmapDirectory());
        }
    }

    /**
     * @param Package[] $packages
     */
    public function movePackages($packages): void
    {
        foreach ($packages as $package) {
            $this->movePackage($package);
        }

        $this->deleteEmptyDirs();
    }

    private function movePackage(Package $package): void
    {
        if (!$this->shouldPackageBeMoved($package)) {
            return;
        }

        /**
         * @todo: This maybe even warrants its own 'File' class, where stuff
         * like the SplFileInfo etc can be stored in.
         */
        foreach ($package->getAutoloaders() as $autoloader) {
            $filesToMove = $autoloader->getFiles($this->files);

            foreach ($filesToMove as $foundFile) {
                $this->moveFile($autoloader, $foundFile);
            }
        }

        if (!in_array($package->getName(), $this->movedPackages)) {
            $this->movedPackages[] = $package->getName();
        }
    }

    private function shouldPackageBeMoved(Package $package): bool
    {
        if (in_array($package->getName(), $this->movedPackages)) {
            return false;
        }

        if ($this->config->isExcludedPackage($package)) {
            return false;
        }

        return true;
    }

    private function moveFile(Autoloader $autoloader, SplFileInfo $file): void
    {
        if (in_array($file->getRealPath(), $this->movedFiles)) {
            return;
        }

        $targetFile = $autoloader->getTargetFilePath($file);
        $this->copyFile($file, $targetFile);

        array_push($this->movedFiles, $file->getRealPath());
    }

    private function copyFile(SplFileInfo $file, string $targetFile): void
    {
        $this->files->copyFile(
            str_replace($this->config->getWorkingDir(), '', $file->getPathname()),
            $targetFile
        );
    }

    /**
     * Deletes all the packages that are moved from the /vendor/ directory to
     * prevent packages that are prefixed/namespaced from being used or
     * influencing the output of the code. They just need to be gone.
     */
    public function deletePackageVendorDirectories(): void
    {
        foreach ($this->movedPackages as $movedPackage) {
            $packageDir = 'vendor' . DIRECTORY_SEPARATOR . $movedPackage;
            if (!is_dir($packageDir) || is_link($packageDir)) {
                continue;
            }

            $this->files->deleteDirectory($packageDir);

            //Delete parent directory too if it became empty
            //(because that package was the only one from that vendor)
            $parentDir = dirname($packageDir);
            if ($this->files->isDirectoryEmpty($parentDir)) {
                $this->files->deleteDirectory($parentDir);
            }
        }
    }
}
