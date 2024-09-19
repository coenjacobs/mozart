<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\AbstractAutoloader;
use CoenJacobs\Mozart\FilesHandler;
use Exception;
use Symfony\Component\Finder\SplFileInfo;

class Classmap extends AbstractAutoloader
{
    /** @var string[] */
    public $files = [];

    /** @var string[] */
    public $paths = [];

    private FilesHandler $fileHandler;

    /**
     * @inheritdoc
     */
    public function processConfig($autoloadConfig): void
    {
        foreach ($autoloadConfig as $value) {
            if ('.php' == substr($value, -4, 4)) {
                array_push($this->files, $value);
                continue;
            }

            array_push($this->paths, $value);
        }
    }

    /**
     * @throws Exception
     */
    public function getSearchNamespace(): string
    {
        throw new Exception('Classmap autoloaders do not contain a namespace and this method can not be used.');
    }

    /**
     * @return array<string,SplFileInfo>
     */
    public function getFiles(FilesHandler $fileHandler): array
    {
        $this->fileHandler = $fileHandler;
        $filesToMove = array();

        foreach ($this->files as $file) {
            $sourcePath = $fileHandler->getConfig()->getWorkingDir() . 'vendor'
                            . DIRECTORY_SEPARATOR . $this->getPackage()->getName();

            $files = $fileHandler->getFile($sourcePath, $file);

            foreach ($files as $foundFile) {
                $filePath = $foundFile->getRealPath();
                $filesToMove[ $filePath ] = $foundFile;
            }
        }

        foreach ($this->paths as $path) {
            $sourcePath = $fileHandler->getConfig()->getWorkingDir() . 'vendor'
                            . DIRECTORY_SEPARATOR . $this->getPackage()->getName() . DIRECTORY_SEPARATOR . $path;

            $files = $fileHandler->getFilesFromPath($sourcePath);
            foreach ($files as $foundFile) {
                $filePath = $foundFile->getRealPath();
                $filesToMove[ $filePath ] = $foundFile;
            }
        }

        return $filesToMove;
    }

    /**
     * @inheritdoc
     */
    public function getTargetFilePath(SplFileInfo $file): string
    {
        $suffix = '';
        foreach ($this->paths as $path) {
            if (! empty(strstr($file->getPathname(), $this->getPackage()->getName() . DIRECTORY_SEPARATOR . $path))) {
                $suffix = $path;
                break;
            }
        }

        $namespacePath = $this->getPackage()->getName();
        $replaceWith = $this->fileHandler->getConfig()->getClassmapDirectory() . $namespacePath . DIRECTORY_SEPARATOR;

        $targetFile = str_replace($this->fileHandler->getConfig()->getWorkingDir(), $replaceWith, $file->getPathname());

        $packageVendorPath = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $this->getPackage()->getName()
                                . DIRECTORY_SEPARATOR;

        if (! empty($suffix)) {
            $packageVendorPath = $packageVendorPath . DIRECTORY_SEPARATOR . $suffix;
        }

        $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
        return str_replace($packageVendorPath, DIRECTORY_SEPARATOR, $targetFile);
    }
}
