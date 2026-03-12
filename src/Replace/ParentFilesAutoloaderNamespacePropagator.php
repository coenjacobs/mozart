<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Config\Files;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\FilesHandler;
use CoenJacobs\Mozart\Replace\Namespace\NamespaceReplacer;

/**
 * Applies namespace replacement from child Files autoloaders to parent targets.
 */
class ParentFilesAutoloaderNamespacePropagator
{
    private Mozart $config;

    private FilesHandler $files;

    /** @var array<int, string[]> */
    private array $namespaceCache = [];

    /**
     * Create a namespace propagator for child Files autoloaders.
     */
    public function __construct(Mozart $config, FilesHandler $files)
    {
        $this->config = $config;
        $this->files = $files;
    }

    /**
     * Apply namespace replacement to every PHP file in a parent directory.
     */
    public function replaceInDirectory(string $directory, Files $autoloader): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $namespaces = $this->getNamespaces($autoloader);
        if (empty($namespaces)) {
            return;
        }

        foreach ($this->files->getFilesFromPath($directory) as $file) {
            $targetFile = $file->getPathName();

            if (!str_ends_with($targetFile, '.php')) {
                continue;
            }

            $this->replaceResolvedFile($targetFile, $autoloader, $namespaces);
        }
    }

    /**
     * Apply namespace replacement to a single parent file.
     */
    public function replaceInFile(string $targetFile, Files $autoloader): void
    {
        $namespaces = $this->getNamespaces($autoloader);
        if (empty($namespaces)) {
            return;
        }

        $this->replaceResolvedFile($targetFile, $autoloader, $namespaces);
    }

    /**
     * @param string[] $namespaces
     */
    private function replaceResolvedFile(string $targetFile, Files $autoloader, array $namespaces): void
    {
        $fullPath = $this->getFullPath($targetFile);

        if (!is_file($fullPath)) {
            return;
        }

        $targetFile = str_replace($this->config->getWorkingDir(), '', $fullPath);

        try {
            $contents = $this->files->readFile($targetFile);
        } catch (\CoenJacobs\Mozart\Exceptions\FileOperationException) {
            return;
        }

        foreach ($namespaces as $namespace) {
            $replacer = new NamespaceReplacer($this->config->getDependencyNamespace());
            $replacer->setAutoloader($autoloader);
            $replacer->setSearchNamespace($namespace);
            $contents = $replacer->replace($contents);
        }

        $this->files->writeFile($targetFile, $contents);
    }

    /**
     * Collect distinct namespaces declared by the child Files autoloader.
     *
     * @return string[]
     */
    private function getNamespaces(Files $autoloader): array
    {
        $cacheKey = spl_object_id($autoloader);

        if (isset($this->namespaceCache[$cacheKey])) {
            return $this->namespaceCache[$cacheKey];
        }

        $namespaces = [];

        foreach ($autoloader->getFiles($this->files) as $file) {
            $namespace = $autoloader->getDetectedNamespace($file);

            if ($namespace === null || $namespace === '') {
                continue;
            }

            $namespaces[$namespace] = true;
        }

        $this->namespaceCache[$cacheKey] = array_keys($namespaces);

        return $this->namespaceCache[$cacheKey];
    }

    /**
     * Normalize a path to an absolute path under the configured working directory.
     */
    private function getFullPath(string $path): string
    {
        if (str_starts_with($path, $this->config->getWorkingDir())) {
            return $path;
        }

        return $this->config->getWorkingDir() . ltrim($path, DIRECTORY_SEPARATOR);
    }
}
