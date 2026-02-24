<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\AbstractAutoloader;
use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use CoenJacobs\Mozart\FilesHandler;
use Symfony\Component\Finder\SplFileInfo;

class Classmap extends AbstractAutoloader
{
    /** @var string[] */
    public array $files = [];

    /** @var string[] */
    public array $paths = [];

    private FilesHandler $fileHandler;

    /**
     * @inheritdoc
     */
    public function processConfig($autoloadConfig): void
    {
        foreach ($autoloadConfig as $value) {
            if (str_ends_with($value, '.php')) {
                $this->files[] = $value;
                continue;
            }

            $this->paths[] = $value;
        }
    }

    /**
     * @throws ConfigurationException
     */
    public function getSearchNamespace(): string
    {
        throw new ConfigurationException(
            'Classmap autoloaders do not contain a namespace and this method can not be used.'
        );
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
                            . DIRECTORY_SEPARATOR . $this->getPackage()->getDirectoryName();

            $files = $fileHandler->getFile($sourcePath, $file);

            foreach ($files as $foundFile) {
                $filePath = $foundFile->getRealPath();
                $filesToMove[ $filePath ] = $foundFile;
            }
        }

        foreach ($this->paths as $path) {
            $sourcePath = $fileHandler->getConfig()->getWorkingDir() . 'vendor'
                            . DIRECTORY_SEPARATOR . $this->getPackage()->getDirectoryName()
                            . DIRECTORY_SEPARATOR . $path;

            if (!is_dir($sourcePath)) {
                continue;
            }

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
            $directoryName = $this->getPackage()->getDirectoryName();
            $searchPath = str_replace('/', DIRECTORY_SEPARATOR, $directoryName . DIRECTORY_SEPARATOR . $path);
            if (str_contains($file->getPathname(), $searchPath)) {
                $suffix = $path;
                break;
            }
        }

        $namespacePath = $this->getPackage()->getDirectoryName();
        $replaceWith = $this->fileHandler->getConfig()->getClassmapDirectory() . $namespacePath . DIRECTORY_SEPARATOR;

        $targetFile = str_replace($this->fileHandler->getConfig()->getWorkingDir(), $replaceWith, $file->getPathname());

        $directoryName = $this->getPackage()->getDirectoryName();
        $packageVendorPath = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $directoryName
                                . DIRECTORY_SEPARATOR;

        if (! empty($suffix)) {
            $packageVendorPath = $packageVendorPath . DIRECTORY_SEPARATOR . $suffix;
        }

        $packageVendorPath = str_replace('/', DIRECTORY_SEPARATOR, $packageVendorPath);
        return str_replace($packageVendorPath, DIRECTORY_SEPARATOR, $targetFile);
    }
}
