<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Config\Autoload;
use CoenJacobs\Mozart\Config\Extra;
use CoenJacobs\Mozart\Config\ReadsConfig;
use CoenJacobs\Mozart\PackageFinder;
use Exception;
use stdClass;

class Package
{
    use ReadsConfig;

    /** @var Package[] */
    public $dependencies = [];

    public string $name;

    /** @var string[] */
    public array $require = [];

    public ?Autoload $autoload = null;
    public ?Extra $extra = null;

    private bool $dependenciesLoaded = false;

    public function setAutoload(stdClass $data): void
    {
        $autoload = new Autoload();
        $autoload->setupAutoloaders($data, $this);
        $this->autoload = $autoload;
    }

    public function getExtra(): ?Extra
    {
        return $this->extra;
    }

    public function isValidMozartConfig(): bool
    {
        if (empty($this->getExtra())) {
            return false;
        }

        if (empty($this->getExtra()->getMozart())) {
            return false;
        }

        return $this->getExtra()->getMozart()->isValidMozartConfig();
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Autoloader[]
     */
    public function getAutoloaders(): array
    {
        if (empty($this->autoload)) {
            return array();
        }

        return $this->autoload->getAutoloaders();
    }

    /**
     * @return string[]
     */
    public function getRequire(): array
    {
        return array_keys($this->require);
    }

    /**
     * @return Package[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Loads and registers all dependencies of this package, by checking the
     * require-object of the composer.json file of this package. Each package
     * listed as a dependency is then loaded and registered as being a
     * dependency of this package. Also flags this package for having its
     * dependencies already loaded, so it doesn't duplicate dependencies.
     */
    public function loadDependencies(PackageFinder $finder): void
    {
        if ($this->dependenciesLoaded) {
            return;
        }

        if ($this->isValidMozartConfig() && !empty($this->getExtra())) {
            $mozart = $this->getExtra()->getMozart();

            if (empty($mozart)) {
                throw new Exception("Couldn't load dependencies because config not set.");
            }
            $finder->setConfig($mozart);
        }

        $dependencies = $finder->getPackagesBySlugs($this->getRequire());

        $this->registerDependencies($dependencies);
        $this->dependenciesLoaded = true;
    }

    public function registerDependency(Package $package): void
    {
        array_push($this->dependencies, $package);
    }

    /**
     * @param Package[] $packages
     */
    public function registerDependencies(array $packages): void
    {
        foreach ($packages as $package) {
            $this->registerDependency($package);
        }
    }
}
