<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Package;
use stdClass;

class PackageFactory
{
    /** @var array <string,Package> */
    public array $cache = [];

    public function createPackage(string $path, stdClass $overrideAutoload = null): Package
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        $package = new Package();
        $package = $package->loadFromFile($path);

        if (! empty($overrideAutoload)) {
            $package->setAutoload($overrideAutoload);
        }

        $this->cache[$path] = $package;
        return $package;
    }
}
