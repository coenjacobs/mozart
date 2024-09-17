<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

abstract class AbstractAutoloader implements Autoloader
{
    public function getOutputDir(string $basePath, string $autoloadPath): string
    {
        $outputDir = $basePath . $autoloadPath;
        $outputDir = str_replace('\\', DIRECTORY_SEPARATOR, $outputDir);
        return $outputDir;
    }
}
