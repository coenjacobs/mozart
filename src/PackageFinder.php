<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use Exception;

class PackageFinder
{
    private ?Mozart $config;
    public PackageFactory $factory;

    public function __construct()
    {
        $this->factory = new PackageFactory();
    }

    public function setConfig(Mozart $config): void
    {
        $this->config = $config;
    }

    public function getPackageBySlug(string $slug): ?Package
    {
        /**
         * This case prevents issues where the requirements array can contain
         * non-package like lines, for example: php or extensions.
         */
        if (!strstr($slug, '/')) {
            return null;
        }

        if (empty($this->config)) {
            throw new Exception("Config not set to find packages");
        }

        $packageDir = $this->config->getWorkingDir() . DIRECTORY_SEPARATOR . 'vendor'
                          . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR;

        if (! is_dir($packageDir)) {
            throw new Exception("Couldn't load package based on provided slug: " . $slug);
        }

        $autoloaders = null;
        $overrideAutoload = $this->config->getOverrideAutoload();
        if ($overrideAutoload !== false && isset($overrideAutoload->$slug)) {
            $autoloaders = $overrideAutoload->$slug;
        }

        $package = $this->factory->createPackage($packageDir . 'composer.json', $autoloaders);
        $package->loadDependencies($this);
        return $package;
    }

    /**
     * @param string[] $slugs
     * @return Package[]
     */
    public function getPackagesBySlugs(array $slugs): array
    {
        $packages = array_map(function (string $slug) {
            return $this->getPackageBySlug($slug);
        }, $slugs);

        return array_filter($packages, function ($package) {
            return $package instanceof Package;
        });
    }

    /**
     * Loops through all dependencies and their dependencies and so on...
     * will eventually return a list of all packages required by the full tree.
     *
     * @param Package[] $packages
     *
     * @return Package[]
     */
    public function findPackages(array $packages): array
    {
        foreach ($packages as $package) {
            $dependencies = $package->getDependencies();

            $package->registerDependencies($this->findPackages($dependencies));
            $packages[$package->getName()] = $package;
        }

        return $packages;
    }
}
