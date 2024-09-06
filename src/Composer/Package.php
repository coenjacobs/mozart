<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Config\Autoload;
use CoenJacobs\Mozart\Config\Composer;
use stdClass;

class Package
{
    /** @var string */
    public $path = '';

    /** @var Composer */
    public $config;

    /** @var Package[] */
    public $requirePackages = [];

    /**
     * Create a PHP object to represent a composer package.
     *
     * @param string $path The path to the vendor folder with the composer.json "name", i.e. the domain/package
     *                     definition, which is the vendor subdir from where the package's composer.json should be read.
     * @param stdClass $overrideAutoload Optional configuration to replace the package's own autoload definition with
     *                                    another which Mozart can use.
     */
    public function __construct($path, Composer $config = null, $overrideAutoload = null)
    {
        $this->path = $path;

        if (empty($config)) {
            $config = Composer::loadFromFile($this->path . '/composer.json');
        }

        $this->config = $config;

        if (isset($overrideAutoload)) {
            $autoload = new Autoload();
            $autoload->setupAutoloaders($overrideAutoload);
            $this->config->set('autoload', $autoload);
        }
    }

    public function getName(): string
    {
        return $this->config->name;
    }

    /**
     * @return Autoloader[]
     */
    public function getAutoloaders(): array
    {
        if (empty($this->config->autoload)) {
            return array();
        }

        return $this->config->autoload->getAutoloaders();
    }

    public function getDependencies(): array
    {
        return $this->requirePackages;
    }

    public function registerRequirePackage(Package $package): void
    {
        array_push($this->requirePackages, $package);
    }

    public function registerRequirePackages(array $packages): void
    {
        foreach ($packages as $package) {
            $this->registerRequirePackage($package);
        }
    }
}
