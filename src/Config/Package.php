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

    public function setAutoload(stdClass $data): void
    {
        $autoload = new Autoload();
        $autoload->setupAutoloaders($data);
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

    public function loadDependencies(PackageFinder $finder): void
    {
        if ($this->isValidMozartConfig() && !empty($this->getExtra())) {
            $mozart = $this->getExtra()->getMozart();

            if (empty($mozart)) {
                throw new Exception("Couldn't load dependencies because config not set.");
            }
            $finder->setConfig($mozart);
        }

        $dependencies = $finder->getPackagesBySlugs($this->getRequire());

        $this->registerDependencies($dependencies);
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
