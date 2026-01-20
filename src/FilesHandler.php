<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use Iterator;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class FilesHandler
{
    /** @var Mozart */
    protected $config;

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(Mozart $config)
    {
        $this->config = $config;

        $adapter = new Local(
            $this->config->getWorkingDir()
        );

        $this->filesystem = new Filesystem($adapter);
    }

    public function readFile(string $path): string
    {
        try {
            $contents = $this->filesystem->read($path);
            if ($contents === false) {
                throw new FileOperationException("Failed to read file: {$path}");
            }
            return $contents;
        } catch (FileNotFoundException $e) {
            throw new FileOperationException("Failed to read file: {$path}. " . $e->getMessage(), 0, $e);
        }
    }

    public function getConfig(): Mozart
    {
        return $this->config;
    }

    public function writeFile(string $path, string $contents): void
    {
        $this->filesystem->put($path, $contents);
    }

    public function getFilesFromPath(string $path): Iterator
    {
        $finder = new Finder();
        return $finder->files()->in($path)->getIterator();
    }

    public function getFile(string $path, string $fileName): Iterator
    {
        $finder = new Finder();
        return $finder->files()->name($fileName)->in($path)->getIterator();
    }

    public function createDirectory(string $path): void
    {
        $this->filesystem->createDir($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->filesystem->deleteDir($path);
    }

    public function isDirectoryEmpty(string $path): bool
    {
        return count($this->filesystem->listContents($path, true)) === 0;
    }

    public function copyFile(string $origin, string $destination): void
    {
        $this->filesystem->copy($origin, $destination);
    }
}
