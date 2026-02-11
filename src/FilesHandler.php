<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use Iterator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class FilesHandler
{
    protected Mozart $config;
    protected Filesystem $filesystem;

    public function __construct(Mozart $config)
    {
        $this->config = $config;

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
        } catch (UnableToReadFile $e) {
            throw new FileOperationException("Failed to read file: {$path}. " . $e->getMessage(), 0, $e);
        }
    }

    public function getConfig(): Mozart
    {
        return $this->config;
    }

    public function writeFile(string $path, string $contents): void
    {
        $this->filesystem->write($path, $contents);
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
        $this->filesystem->createDirectory($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->filesystem->deleteDirectory($path);
    }

    public function isDirectoryEmpty(string $path): bool
    {
        return count($this->filesystem->listContents($path, true)->toArray()) === 0;
    }

    public function copyFile(string $origin, string $destination): void
    {
        $this->filesystem->copy($origin, $destination);
    }
}
