<?php

namespace CoenJacobs\Mozart\Autoload;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\FilesHandler;
use Composer\ClassMapGenerator\ClassMapGenerator;

/**
 * Generates a Composer-compatible autoloader for prefixed dependencies.
 */
class AutoloaderGenerator
{
    protected Mozart $config;
    protected FilesHandler $files;
    protected string $hash;

    public function __construct(Mozart $config, ?FilesHandler $filesHandler = null)
    {
        $this->config = $config;
        $this->files = $filesHandler ?? new FilesHandler($config);
        $this->hash = $this->generateHash();
    }

    /**
     * Generate the complete autoloader for prefixed dependencies.
     *
     * @param array<string> $fileTargets Target paths of files that need eager loading
     */
    public function generate(array $fileTargets): void
    {
        $this->cleanPreviousAutoloader();

        $psr4 = $this->buildPsr4Entries();
        $classmap = $this->buildClassmapEntries();
        $files = $this->buildFilesEntries($fileTargets);

        $this->files->createDirectory($this->config->getDepDirectory() . 'composer');

        $writer = new AutoloaderFileWriter($this->files, $this->config->getDepDirectory(), $this->hash);
        $writer->write($psr4, $classmap, $files);

        $this->copyClassLoader();
        $this->copyLicense();
    }

    /**
     * Build the PSR-4 entries mapping the dependency namespace to dep_directory.
     *
     * @return array<string, array<string>>
     */
    protected function buildPsr4Entries(): array
    {
        $namespace = $this->config->getDependencyNamespace();
        $depDir = rtrim($this->config->getDepDirectory(), DIRECTORY_SEPARATOR . '/');

        return [$namespace => [$depDir]];
    }

    /**
     * Build classmap entries by scanning both output directories.
     *
     * @return array<string, string>
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function buildClassmapEntries(): array
    {
        $workingDir = $this->config->getWorkingDir();
        $depDir = $workingDir . $this->config->getDepDirectory();
        $cmapDir = $workingDir . $this->config->getClassmapDirectory();

        $classmap = [];

        if (is_dir($depDir)) {
            $classmap = array_merge($classmap, ClassMapGenerator::createMap($depDir));
        }

        if (is_dir($cmapDir) && realpath($cmapDir) !== realpath($depDir)) {
            $classmap = array_merge($classmap, ClassMapGenerator::createMap($cmapDir));
        }

        // Convert absolute paths to paths relative to working directory
        $entries = [];
        foreach ($classmap as $class => $absolutePath) {
            $relativePath = str_replace($workingDir, '', $absolutePath);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            $entries[$class] = $relativePath;
        }

        return $entries;
    }

    /**
     * Build files entries from tracked autoloader targets.
     *
     * @param array<string> $targets Target file paths relative to working directory
     * @return array<string, string>
     */
    protected function buildFilesEntries(array $targets): array
    {
        $entries = [];
        foreach ($targets as $target) {
            $identifier = md5($target);
            $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $target);
            $entries[$identifier] = $normalizedPath;
        }

        return $entries;
    }

    /**
     * Generate a unique hash based on the dependency namespace.
     */
    public function generateHash(): string
    {
        return md5($this->config->getDependencyNamespace());
    }

    /**
     * Remove previously generated autoloader files.
     */
    protected function cleanPreviousAutoloader(): void
    {
        $composerDir = $this->config->getDepDirectory() . 'composer';
        if (is_dir($this->config->getWorkingDir() . $composerDir)) {
            $this->files->deleteDirectory($composerDir);
        }

        $autoloadFile = $this->config->getDepDirectory() . 'autoload.php';
        $absolutePath = $this->config->getWorkingDir() . $autoloadFile;
        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }
    }

    /**
     * Copy ClassLoader.php from the project's vendor/composer/ directory.
     */
    protected function copyClassLoader(): void
    {
        $source = $this->config->getWorkingDir() . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'ClassLoader.php';

        if (!file_exists($source)) {
            throw new FileOperationException(
                'Cannot find vendor/composer/ClassLoader.php. '
                . 'Run "composer install" before generating the autoloader.'
            );
        }

        $this->files->copyFile(
            'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'ClassLoader.php',
            $this->config->getDepDirectory() . 'composer' . DIRECTORY_SEPARATOR . 'ClassLoader.php'
        );
    }

    /**
     * Copy the Composer LICENSE file.
     */
    protected function copyLicense(): void
    {
        $source = 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'LICENSE';

        if (!file_exists($this->config->getWorkingDir() . $source)) {
            return;
        }

        $this->files->copyFile(
            $source,
            $this->config->getDepDirectory() . 'composer' . DIRECTORY_SEPARATOR . 'LICENSE'
        );
    }

    /**
     * Get the generated hash (for testing).
     */
    public function getHash(): string
    {
        return $this->hash;
    }
}
