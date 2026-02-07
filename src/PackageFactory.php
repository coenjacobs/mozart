<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Package;
use stdClass;

class PackageFactory
{
    /** @var array <string,Package> */
    private array $cache = [];

    public function createPackage(string $path, ?stdClass $overrideAutoload = null): Package
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        $package = new Package();
        $package = $package->loadFromFile($path);

        // Extract directory name from the composer.json file path
        $packageDir = dirname($path);
        // Normalize path separators to forward slashes for consistent matching
        $normalizedDir = str_replace('\\', '/', $packageDir);
        $vendorPos = strpos($normalizedDir, '/vendor/');
        if ($vendorPos !== false) {
            $directoryName = substr($normalizedDir, $vendorPos + strlen('/vendor/'));
            $package->directoryName = $directoryName;
        }

        if (! empty($overrideAutoload)) {
            $package->setAutoload($overrideAutoload);
        }

        $this->cache[$path] = $package;
        return $package;
    }
}
