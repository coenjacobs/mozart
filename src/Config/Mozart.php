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

    /** @var string[] */
    public array $excluded_packages = [];

    public OverrideAutoload $override_autoload;
    public bool $delete_vendor_directories;

    /**
     * @param string[] $packages
     */
    public function setPackages(array $packages): void
    {
        $this->packages = $packages;
    }

    /**
     * @param string[] $excluded_packages
     */
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

    public function isExcludedPackage(Package $package): bool
    {
        return in_array($package->getName(), $this->excluded_packages);
    }

    /**
     * Returns the configured dependency directory, with an appended directory
     * separator, if one isn't at the end of the configured string yet.
     */
    public function getDepDirectory(): string
    {
        return rtrim($this->dep_directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }
}
