<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Exceptions\ConfigurationException;

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

    /**
     * Returns a Package object for the package based on the provided slug (in
     * vendor/package format). The data of the package is loaded if a valid
     * installed package could be found based on the slug, which is then being
     * used to read the composer.json file of the package.
     */
    public function getPackageBySlug(string $slug): ?Package
    {
        /**
         * This case prevents issues where the requirements array can contain
         * non-package like lines, for example: php or extensions.
         */
        if (!str_contains($slug, '/')) {
            return null;
        }

        if (empty($this->config)) {
            throw new ConfigurationException("Config not set to find packages");
        }

        $packageDir = $this->config->getWorkingDir() . DIRECTORY_SEPARATOR . 'vendor'
                          . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR;

        if (! is_dir($packageDir)) {
            throw new ConfigurationException("Couldn't load package based on provided slug: " . $slug);
        }

        $overrideAutoload = $this->config->getOverrideAutoload();
        $autoloaders = $overrideAutoload->getByKey($slug);

        $package = $this->factory->createPackage($packageDir . 'composer.json', $autoloaders);
        $package->loadDependencies($this);
        return $package;
    }

    /**
     * Returns Package objects which are loaded based on the provided array of
     * slugs (in vendor/package format).
     *
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
     * Loops through all dependencies and their dependencies and so on... will
     * eventually return a list of all packages required by the full tree.
     *
     * @param Package[] $packages
     * @return Package[]
     */
    public function findPackages(array $packages): array
    {
        $visited = [];
        $queue = $packages;

        while (!empty($queue)) {
            $package = array_shift($queue);
            $name = $package->getName();

            if (isset($visited[$name])) {
                continue;
            }

            $visited[$name] = $package;

            foreach ($package->getDependencies() as $dependency) {
                if (!isset($visited[$dependency->getName()])) {
                    $queue[] = $dependency;
                }
            }
        }

        return array_values($visited);
    }
}
