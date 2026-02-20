<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use Iterator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\FilesystemOperationFailed;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class FilesHandler
{
    protected Mozart $config;
    protected Filesystem $filesystem;

    public function __construct(Mozart $config, ?Filesystem $filesystem = null)
    {
        $this->config = $config;

        if ($filesystem !== null) {
            $this->filesystem = $filesystem;
            return;
        }

        $adapter = new LocalFilesystemAdapter(
            $this->config->getWorkingDir(),
            new PortableVisibilityConverter(0644, 0600, 0755, 0700, Visibility::PUBLIC)
        );

        // The FilesystemOperator
        $this->filesystem = new Filesystem($adapter);
    }

    public function readFile(string $path): string
    {
        try {
            return $this->filesystem->read($path);
        } catch (FilesystemOperationFailed $e) {
            throw new FileOperationException("Failed to read file: {$path}. " . $e->getMessage(), 0, $e);
        }
    }

    public function getConfig(): Mozart
    {
        return $this->config;
    }

    public function writeFile(string $path, string $contents): void
    {
        try {
            $this->filesystem->write($path, $contents);
        } catch (FilesystemOperationFailed $e) {
            throw new FileOperationException("Failed to write file: {$path}. " . $e->getMessage(), 0, $e);
        }
    }

    public function getFilesFromPath(string $path): Iterator
    {
        $finder = new Finder();
        return $finder->files()->exclude('vendor')->in($path)->getIterator();
    }

    public function getFile(string $path, string $fileName): Iterator
    {
        $finder = new Finder();
        return $finder->files()->exclude('vendor')->name($fileName)->in($path)->getIterator();
    }

    public function createDirectory(string $path): void
    {
        try {
            $this->filesystem->createDirectory($path);
        } catch (FilesystemOperationFailed $e) {
            throw new FileOperationException("Failed to create directory: {$path}. " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->filesystem->deleteDirectory($path);
        } catch (FilesystemOperationFailed $e) {
            throw new FileOperationException("Failed to delete directory: {$path}. " . $e->getMessage(), 0, $e);
        }
    }

    public function isDirectoryEmpty(string $path): bool
    {
        return count($this->filesystem->listContents($path, true)->toArray()) === 0;
    }

    public function copyFile(string $origin, string $destination): void
    {
        try {
            $this->filesystem->copy($origin, $destination);
        } catch (FilesystemOperationFailed $e) {
            throw new FileOperationException(
                "Failed to copy file: {$origin} to {$destination}. " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
