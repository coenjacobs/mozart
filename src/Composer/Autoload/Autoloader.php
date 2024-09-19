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
    /**
     * Returns the intended target path of a file, where it should be moved by
     * the Mover class. This requires access to the Mozart configuration, for it
     * to determine the target directory. This is done by checking the paths
     * that are being registered for this autoloader, to see if they can be
     * matched with the full path name of the provided file.
     */
    public function getTargetFilePath(SplFileInfo $file): string;
}
