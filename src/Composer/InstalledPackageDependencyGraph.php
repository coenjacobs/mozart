<?php

namespace CoenJacobs\Mozart\Composer;

class InstalledPackageDependencyGraph
{
    private string $workingDir;

    public function __construct(string $workingDir)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * Build a package -> required package names graph from Composer's
     * installed.json metadata.
     *
     * @return array<string, string[]>
     */
    public function getDependencyGraph(): array
    {
        $packages = $this->loadInstalledPackages();
        $graph = [];

        foreach ($packages as $package) {
            if (empty($package['name']) || !is_string($package['name'])) {
                continue;
            }

            $graph[$package['name']] = $this->extractRequiredPackageNames($package);
        }

        return $graph;
    }

    /**
     * Find processed packages that must stay in vendor because they are still
     * required by installed packages outside the processed set.
     *
     * @param string[] $processedPackages
     * @return string[]
     */
    public function getSharedProcessedPackages(array $processedPackages): array
    {
        $graph = $this->getDependencyGraph();
        $processedLookup = array_fill_keys($processedPackages, true);
        $sharedPackages = [];
        $visited = [];
        $queue = [];

        foreach (array_keys($graph) as $packageName) {
            if (!isset($processedLookup[$packageName])) {
                $queue[] = $packageName;
            }
        }

        while (!empty($queue)) {
            $packageName = array_shift($queue);

            if (isset($visited[$packageName])) {
                continue;
            }

            $visited[$packageName] = true;

            foreach ($graph[$packageName] ?? [] as $dependency) {
                if (isset($processedLookup[$dependency])) {
                    $sharedPackages[$dependency] = true;
                }

                if (!isset($visited[$dependency])) {
                    $queue[] = $dependency;
                }
            }
        }

        return array_keys($sharedPackages);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadInstalledPackages(): array
    {
        $installedPath = $this->workingDir
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer'
            . DIRECTORY_SEPARATOR . 'installed.json';

        if (!is_readable($installedPath)) {
            return [];
        }

        $installedJson = file_get_contents($installedPath);
        if ($installedJson === false) {
            return [];
        }

        $decoded = json_decode($installedJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['packages']) && is_array($decoded['packages'])) {
            return $decoded['packages'];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $package
     * @return string[]
     */
    private function extractRequiredPackageNames(array $package): array
    {
        $requires = $package['require'] ?? [];
        if (!is_array($requires)) {
            return [];
        }

        return array_values(array_filter(array_keys($requires), function ($require): bool {
            return is_string($require) && str_contains($require, '/');
        }));
    }
}
