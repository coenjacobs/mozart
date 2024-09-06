<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Package;
use stdClass;
use CoenJacobs\Mozart\Config\OverrideAutoload;

class Mozart
{
    use ReadsConfig, ConfigAccessor;

    public string $dep_namespace;
    public string $dep_directory;
    public string $classmap_directory;
    public string $classmap_prefix;

    /** @var string[] */
    public array $packages = [];

    /** @var string[]> */
    public array $excluded_packages = [];

    public OverrideAutoload $override_autoload;

    public bool $delete_vendor_directories;

    public function setPackages(array $packages): void
    {
        $this->packages = $packages;
    }

    public function setExcludedPackages(array $excluded_packages): void
    {
        $this->excluded_packages = $excluded_packages;
    }

    public function setOverrideAutoload(stdClass $object): void
    {
        $this->override_autoload = new OverrideAutoload($object);
    }

    public function isValidMozartConfig(): bool
    {
        $required = [ 'dep_namespace', 'dep_directory', 'classmap_directory', 'classmap_prefix' ];

        foreach ($required as $requiredProp) {
            if (empty($this->$requiredProp)) {
                return false;
            }
        }

        return true;
    }

    public function isExcludedPackage(Package $package)
    {
        return in_array($package->getName(), $this->excluded_packages);
    }
}
