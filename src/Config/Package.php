<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Config\Autoload;
use CoenJacobs\Mozart\Config\ConfigAccessor;
use CoenJacobs\Mozart\Config\Extra;
use CoenJacobs\Mozart\Config\ReadsConfig;
use stdClass;

class Package
{
    use ReadsConfig;

    /** @var string */
    public $path = '';

    /** @var Package[] */
    public $requirePackages = [];

    public string $name;

    /** @var string[] */
    public array $require;

    public ?Autoload $autoload = null;
    public ?Extra $extra = null;

    /**
     * Create a PHP object to represent a composer package.
     *
     * @param string $path The path to the vendor folder with the composer.json "name", i.e. the domain/package
     *                     definition, which is the vendor subdir from where the package's composer.json should be read.
     * @param stdClass $overrideAutoload Optional configuration to replace the package's own autoload definition with
     *                                    another which Mozart can use.
     */
    public function __construct($path, $overrideAutoload = null)
    {
        $this->path = $path;

        if (isset($overrideAutoload)) {
            $this->setAutoload($overrideAutoload);
        }
    }

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
    public function getPackages(): array
    {
        return $this->require;
    }

    /**
     * @return Package[]
     */
    public function getDependencies(): array
    {
        return $this->requirePackages;
    }

    public function registerRequirePackage(Package $package): void
    {
        array_push($this->requirePackages, $package);
    }

    /**
     * @param Package[] $packages
     */
    public function registerRequirePackages(array $packages): void
    {
        foreach ($packages as $package) {
            $this->registerRequirePackage($package);
        }
    }
}