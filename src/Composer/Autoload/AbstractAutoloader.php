<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

use CoenJacobs\Mozart\Config\Package;

abstract class AbstractAutoloader implements Autoloader
{
    private Package $package;

    public function getPackage(): Package
    {
        return $this->package;
    }

    public function setPackage(Package $package): void
    {
        $this->package = $package;
    }

    public function getOutputDir(string $basePath, string $autoloadPath): string
    {
        $outputDir = $basePath . $autoloadPath;
        $outputDir = str_replace('\\', DIRECTORY_SEPARATOR, $outputDir);
        return $outputDir;
    }
}
