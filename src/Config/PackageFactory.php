<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Package;
use stdClass;

class PackageFactory
{
    public static function createPackage(string $path, stdClass $overrideAutoload = null): Package
    {
        $package = Package::loadFromFile($path);

        if (! empty($overrideAutoload)) {
            $package->setAutoload($overrideAutoload);
        }

        return $package;
    }
}
