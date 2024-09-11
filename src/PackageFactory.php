<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Package;
use stdClass;

class PackageFactory
{
    /** @var array <string,Package> */
    public static array $cache = [];

    public static function createPackage(
        string $path,
        stdClass $overrideAutoload = null,
        bool $loadDependencies = true
    ): Package {
        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        $package = Package::loadFromFile($path);

        if (! empty($overrideAutoload)) {
            $package->setAutoload($overrideAutoload);
        }

        if ($loadDependencies) {
            $package->loadDependencies();
        }

        self::$cache[$path] = $package;
        return $package;
    }
}
