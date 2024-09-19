<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

use CoenJacobs\Mozart\FilesHandler;
use Symfony\Component\Finder\SplFileInfo;

abstract class NamespaceAutoloader extends AbstractAutoloader
{
    /** @var string */
    public $namespace = '';

    /**
     * The subdir of the vendor/domain/package directory that contains the files for this autoloader type.
     *
     * e.g. src/
     *
     * @var array<string>
     */
    public $paths = [];

    private FilesHandler $fileHandler;

    /**
     * A package's composer.json config autoload key's value, where $key is `psr-1`|`psr-4`|`classmap`.
     *
     * @param $autoloadConfig
     */
    public function processConfig($autoloadConfig): void
    {
        if (is_array($autoloadConfig)) {
            foreach ($autoloadConfig as $path) {
                array_push($this->paths, $path);
            }

            return;
        }
        array_push($this->paths, $autoloadConfig);
    }

    public function getNamespace(): string
    {
        return rtrim($this->namespace, '\\') . '\\';
    }

    public function getSearchNamespace(): string
    {
        return rtrim($this->namespace, '\\');
    }

    public function getNamespacePath(): string
    {
        return '';
    }

    public function getFiles(FilesHandler $fileHandler): array
    {
        $this->fileHandler = $fileHandler;
        $filesToMove = array();

        foreach ($this->paths as $path) {
            $sourcePath = $fileHandler->getConfig()->getWorkingDir() . 'vendor' . DIRECTORY_SEPARATOR
                        . $this->getPackage()->getName() . DIRECTORY_SEPARATOR . $path;

            $sourcePath = str_replace('/', DIRECTORY_SEPARATOR, $sourcePath);


            $files = $fileHandler->getFilesFromPath($sourcePath);

            foreach ($files as $foundFile) {
                $filePath = $foundFile->getRealPath();
                $filesToMove[ $filePath ] = $foundFile;
            }
        }

        return $filesToMove;
    }

    public function getTargetFilePath(SplFileInfo $file): string
    {
        $suffix = '';
        foreach ($this->paths as $path) {
            if (! empty(strstr($file->getPathname(), $this->getPackage()->getName() . DIRECTORY_SEPARATOR . $path))) {
                $suffix = $path;
                break;
            }
        }

        $replaceWith = $this->fileHandler->getConfig()->getDepDirectory() . $this->getNamespacePath();
        $targetFile = str_replace($this->fileHandler->getConfig()->getWorkingDir(), $replaceWith, $file->getPathname());

        $packageVendorPath = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $this->getPackage()->getName();

        if (! empty($suffix)) {
            $packageVendorPath = $packageVendorPath . DIRECTORY_SEPARATOR . $suffix;
        }

        $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
        return str_replace($packageVendorPath, DIRECTORY_SEPARATOR, $targetFile);
    }
}
