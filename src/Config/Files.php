<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\AbstractAutoloader;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use CoenJacobs\Mozart\FilesHandler;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\SplFileInfo;

class Files extends AbstractAutoloader
{
    /** @var string[] List of file paths from composer.json */
    protected array $files = [];

    protected ?FilesHandler $fileHandler = null;

    /** @var array<string, string|null> Cache of detected namespaces per file */
    protected array $detectedNamespaces = [];

    /**
     * @inheritdoc
     */
    public function processConfig($autoloadConfig): void
    {
        $this->files = (array) $autoloadConfig;
    }

    /**
     * @throws ConfigurationException
     */
    public function getSearchNamespace(): string
    {
        throw new ConfigurationException(
            'Files autoloaders do not contain a namespace and this method can not be used.'
        );
    }

    /**
     * @return array<string,SplFileInfo>
     */
    public function getFiles(FilesHandler $fileHandler): array
    {
        $this->fileHandler = $fileHandler;
        $filesToMove = [];

        foreach ($this->files as $file) {
            // Skip files that are already inside a PSR-4/PSR-0 path from the same package
            if ($this->isInsidePsrPath($file)) {
                continue;
            }

            $sourcePath = $fileHandler->getConfig()->getWorkingDir() . 'vendor'
                . DIRECTORY_SEPARATOR . $this->getPackage()->getDirectoryName();

            $files = $fileHandler->getFile($sourcePath, basename($file));

            foreach ($files as $foundFile) {
                // Verify this is the exact file we're looking for (not just matching the basename)
                $relativePath = str_replace(
                    $sourcePath . DIRECTORY_SEPARATOR,
                    '',
                    $foundFile->getRealPath()
                );
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                $expectedPath = str_replace(DIRECTORY_SEPARATOR, '/', $file);

                if ($relativePath === $expectedPath) {
                    $filePath = $foundFile->getRealPath();
                    $filesToMove[$filePath] = $foundFile;
                }
            }
        }

        return $filesToMove;
    }

    /**
     * Check if a file path is inside a PSR-4 or PSR-0 path from the same package.
     * This prevents duplicate processing when a file is listed in both `files` and covered by PSR-4/PSR-0.
     */
    protected function isInsidePsrPath(string $filePath): bool
    {
        foreach ($this->getPackage()->getAutoloaders() as $autoloader) {
            if ($autoloader instanceof NamespaceAutoloader) {
                foreach ($autoloader->paths as $psrPath) {
                    $psrPath = rtrim($psrPath, '/');
                    $normalizedFilePath = str_replace(DIRECTORY_SEPARATOR, '/', $filePath);

                    if (str_starts_with($normalizedFilePath, $psrPath . '/') || $normalizedFilePath === $psrPath) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTargetFilePath(SplFileInfo $file): string
    {
        if ($this->fileHandler === null) {
            throw new ConfigurationException('FileHandler not initialized. Call getFiles() first.');
        }

        $namespace = $this->getDetectedNamespace($file);

        if ($namespace !== null) {
            // File has a namespace - put it in dep_directory following namespace path
            $namespacePath = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
            return $this->fileHandler->getConfig()->getDepDirectory()
                . $namespacePath . DIRECTORY_SEPARATOR . $file->getFilename();
        }

        // File is global scope - put it in classmap_directory with package name
        $packageName = str_replace('/', DIRECTORY_SEPARATOR, $this->getPackage()->getDirectoryName());
        return $this->fileHandler->getConfig()->getClassmapDirectory()
            . $packageName . DIRECTORY_SEPARATOR . $file->getFilename();
    }

    /**
     * Detect the namespace declared in a PHP file by parsing its AST.
     *
     * @return string|null The namespace name, or null if the file is in global scope
     */
    public function getDetectedNamespace(SplFileInfo $file): ?string
    {
        $realPath = $file->getRealPath();

        if (isset($this->detectedNamespaces[$realPath])) {
            return $this->detectedNamespaces[$realPath];
        }

        $contents = file_get_contents($realPath);
        if ($contents === false) {
            $this->detectedNamespaces[$realPath] = null;
            return null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($contents);
        } catch (\PhpParser\Error $e) {
            $this->detectedNamespaces[$realPath] = null;
            return null;
        }

        if ($ast === null) {
            $this->detectedNamespaces[$realPath] = null;
            return null;
        }

        // Find namespace node in AST
        foreach ($ast as $node) {
            if ($node instanceof Namespace_) {
                $namespace = $node->name ? $node->name->toString() : null;
                $this->detectedNamespaces[$realPath] = $namespace;
                return $namespace;
            }
        }

        $this->detectedNamespaces[$realPath] = null;
        return null;
    }

    /**
     * Check if a file has a detected namespace (vs being in global scope).
     */
    public function hasNamespace(SplFileInfo $file): bool
    {
        return $this->getDetectedNamespace($file) !== null;
    }
}
