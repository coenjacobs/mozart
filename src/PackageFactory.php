<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Config\ConfigLoader;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use stdClass;

class PackageFactory
{
    /** @var array <string,Package> */
    private array $cache = [];
    private ConfigLoader $loader;

    public function __construct()
    {
        $this->loader = new ConfigLoader();
    }

    /**
     * Create a Package instance from a composer.json file path.
     */
    public function createPackage(string $path, ?stdClass $overrideAutoload = null): Package
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        $package = $this->loader->fromFile($path, Package::class);

        // Extract directory name from the composer.json file path
        $packageDir = dirname($path);
        // Normalize path separators to forward slashes for consistent matching
        $normalizedDir = str_replace('\\', '/', $packageDir);
        $vendorPos = strpos($normalizedDir, '/vendor/');
        if ($vendorPos !== false) {
            $directoryName = substr($normalizedDir, $vendorPos + strlen('/vendor/'));
            $package->directoryName = $directoryName;
        }

        if (! empty($overrideAutoload)) {
            $package->setAutoload($overrideAutoload);
        }

        $this->cache[$path] = $package;
        return $package;
    }

    /**
     * Return the Mozart config from the package's extra.mozart block,
     * or a fresh empty config when the block is absent. Running the
     * command is the opt-in — no extra.mozart block is required.
     */
    public function resolveConfig(Package $package): Mozart
    {
        $extra = $package->getExtra();

        if (!empty($extra) && !empty($extra->getMozart())) {
            return $extra->getMozart();
        }

        return new Mozart();
    }
}
