<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

use CoenJacobs\Mozart\FilesHandler;
use Symfony\Component\Finder\SplFileInfo;

interface Autoloader
{
    /**
     * @param mixed $autoloadConfig
     */
    public function processConfig($autoloadConfig): void;
    public function getSearchNamespace(): string;
    public function getOutputDir(string $basePath, string $autoloadPath): string;
    /**
     * @return array<string,SplFileInfo>
     */
    public function getFiles(FilesHandler $files): array;
    public function getTargetFilePath(SplFileInfo $file): string;
}
