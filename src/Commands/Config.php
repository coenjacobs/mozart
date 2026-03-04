<?php

namespace CoenJacobs\Mozart\Commands;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\PackageFactory;

class Config
{
    private string $workingDir;
    /**
     * The raw extra.mozart stdClass from composer.json, before JsonMapper
     * processes it. Used to detect which keys the user explicitly set.
     */
    private ?\stdClass $raw = null;

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

        $config = $factory->resolveConfig($package);

        $this->raw = $this->extractRawMozart($composerFile);

        $snapshot = $this->snapshot($config);
        $config->applyDefaults($package);
        $sources = $this->buildSources($snapshot, $config, $package);

        return ['config' => $config, 'sources' => $sources];
    }

    /**
     * Capture raw values before defaults are applied, so we can determine
     * which values were explicitly set by the user.
     *
     * @return array<string, string|bool>
     */
    private function snapshot(Mozart $config): array
    {
        return [
            'dep_namespace'            => $config->depNamespace,
            'dep_directory'            => $config->depDirectory,
            'classmap_directory'       => $config->classmapDir,
            'classmap_prefix'          => $config->classmapPrefix,
            'generate_autoloader'      => $config->generateAutoloader,
            'delete_vendor_directories' => $config->deleteVendorDirs,
        ];
    }

    /**
     * Build source annotations describing where each resolved value came from.
     *
     * @param array<string, string|bool> $snapshot
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

        $sources['generate_autoloader'] = $this->raw !== null
            && property_exists($this->raw, 'generate_autoloader')
            ? '(explicit)'
            : '(default)';

        $sources['delete_vendor_directories'] = $this->raw !== null
            && property_exists($this->raw, 'delete_vendor_directories')
            ? '(explicit)'
            : '(default)';

        return $sources;
    }

    /**
     * Determine source annotation for the classmap_directory setting.
     *
     * @param array<string, string|bool> $snapshot
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
     * @param array<string, string|bool> $snapshot
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
     * Extract the raw extra.mozart stdClass from the composer.json file,
     * before JsonMapper processes it. Returns null when the block is absent.
     */
    private function extractRawMozart(string $composerFile): ?\stdClass
    {
        $contents = file_get_contents($composerFile);

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents);

        if (!$decoded instanceof \stdClass) {
            return null;
        }

        return $decoded->extra->mozart ?? null;
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
}
