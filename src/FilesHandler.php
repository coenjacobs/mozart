<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Mozart;
use Iterator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
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
            $this->config->getWorkingDir()
        );

        // The FilesystemOperator
        $this->filesystem = new Filesystem($adapter);
    }

    public function readFile(string $path): string
    {
        try {
            $contents = $this->filesystem->read($path);
        } catch (UnableToReadFile $e) {
            $contents = '';
        }

        return $contents;
    }

    public function writeFile(string $path, string $contents): void
    {
        $this->filesystem->write($path, $contents);
    }

    public function getFilesFromPath(string $path): Iterator
    {
        $finder = new Finder();
        return $finder->files()->in($path)->getIterator();
    }
}
