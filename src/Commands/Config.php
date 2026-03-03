<?php

namespace CoenJacobs\Mozart\Commands;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\PackageFactory;

class Config
{
    private string $workingDir;

    public function __construct(string $workingDir)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * Load the Mozart configuration, apply defaults, and return the resolved
     * config alongside source annotations for each setting.
     *
     * @return array{config: Mozart, sources: array<string, string>}
     */
    public function execute(): array
    {
        $composerFile = $this->workingDir . DIRECTORY_SEPARATOR . 'composer.json';

        $factory = new PackageFactory();
        $package = $factory->createPackage($composerFile);

        $config = $this->resolveConfig($package);

        $snapshot = $this->snapshot($config);
        $config->applyDefaults($package);
        $sources = $this->buildSources($snapshot, $config, $package);

        return ['config' => $config, 'sources' => $sources];
    }

    /**
     * Capture raw values before defaults are applied, so we can determine
     * which values were explicitly set by the user.
     *
     * @return array<string, string>
     */
    private function snapshot(Mozart $config): array
    {
        return [
            'dep_namespace'      => $config->depNamespace,
            'dep_directory'      => $config->depDirectory,
            'classmap_directory' => $config->classmapDir,
            'classmap_prefix'    => $config->classmapPrefix,
        ];
    }

    /**
     * Build source annotations describing where each resolved value came from.
     *
     * @param array<string, string> $snapshot
     * @return array<string, string>
     */
    private function buildSources(array $snapshot, Mozart $config, Package $package): array
    {
        $sources = [];

        $sources['dep_directory'] = !empty($snapshot['dep_directory'])
            ? '(explicit)'
            : '(default)';

        $sources['classmap_directory'] = $this->classmapDirSource($snapshot, $config);

        $sources['dep_namespace'] = !empty($snapshot['dep_namespace'])
            ? '(explicit)'
            : $this->inferNamespaceSource($package, $config->depNamespace);

        $sources['classmap_prefix'] = $this->classmapPrefixSource($snapshot, $config);

        return $sources;
    }

    /**
     * Determine source annotation for the classmap_directory setting.
     *
     * @param array<string, string> $snapshot
     */
    private function classmapDirSource(array $snapshot, Mozart $config): string
    {
        if (!empty($snapshot['classmap_directory'])) {
            return '(explicit)';
        }

        if ($config->classmapDir === $config->depDirectory) {
            return '(default, same as dep_directory)';
        }

        return '(default)';
    }

    /**
     * Determine source annotation for the classmap_prefix setting.
     *
     * @param array<string, string> $snapshot
     */
    private function classmapPrefixSource(array $snapshot, Mozart $config): string
    {
        if (!empty($snapshot['classmap_prefix'])) {
            return '(explicit)';
        }

        if (!empty($config->classmapPrefix)) {
            return '(derived from dep_namespace)';
        }

        return '(not set)';
    }

    /**
     * Determine the source annotation for an inferred dep_namespace value.
     */
    private function inferNamespaceSource(Package $package, string $resolved): string
    {
        if (empty($resolved)) {
            return '(not set)';
        }

        foreach ($package->getAutoloaders() as $autoloader) {
            if ($autoloader instanceof Psr4) {
                $namespace = $autoloader->getSearchNamespace();
                if (!empty($namespace) && str_starts_with($resolved, $namespace)) {
                    return '(inferred from PSR-4: ' . $namespace . '\\)';
                }
            }
        }

        if (isset($package->name) && !empty($package->name)) {
            return '(inferred from package name: ' . $package->name . ')';
        }

        return '(inferred)';
    }

    /**
     * Return the Mozart config from the package's extra.mozart block,
     * or a fresh empty config when the block is absent.
     */
    private function resolveConfig(Package $package): Mozart
    {
        $extra = $package->getExtra();

        if (!empty($extra) && !empty($extra->getMozart())) {
            return $extra->getMozart();
        }

        return new Mozart();
    }
}
