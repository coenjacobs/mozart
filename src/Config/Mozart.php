<?php

namespace CoenJacobs\Mozart\Config;

use stdClass;
use CoenJacobs\Mozart\Config\OverrideAutoload;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\Psr4;
use CoenJacobs\Mozart\Exceptions\ConfigurationException;

class Mozart
{
    use ReadsConfig;

    public string $depNamespace = '';
    public string $depDirectory = '';
    public string $classmapDir = '';
    public string $classmapPrefix = '';
    public string $constantPrefix = '';
    public string $functionsPrefix = '';

    /** @var string[] */
    public array $packages = [];

    /** @var string[] */
    public array $excludedPackages = [];

    public OverrideAutoload $overrideAutoload;
    public bool $generateAutoloader = true;
    public bool $deleteVendorDir = true;

    public string $workingDir = '';

    public function __construct()
    {
        $this->overrideAutoload = new OverrideAutoload(new stdClass());
    }

    public function setDepNamespace(string $depNamespace): void
    {
        $this->depNamespace = $depNamespace;
    }

    public function setDepDirectory(string $depDirectory): void
    {
        $this->depDirectory = $depDirectory;
    }

    public function setClassmapDirectory(string $classmapDirectory): void
    {
        $this->classmapDir = $classmapDirectory;
    }

    public function setClassmapPrefix(string $classmapPrefix): void
    {
        $this->classmapPrefix = $classmapPrefix;
    }

    /**
     * Set the prefix for global-scope constant renaming.
     */
    public function setConstantPrefix(string $constantPrefix): void
    {
        $this->constantPrefix = $constantPrefix;
    }

    /**
     * Set the prefix for global-scope function renaming.
     */
    public function setFunctionsPrefix(string $functionsPrefix): void
    {
        $this->functionsPrefix = $functionsPrefix;
    }

    /**
     * @return string[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * @param string[] $packages
     */
    public function setPackages(array $packages): void
    {
        $this->packages = $packages;
    }

    /**
     * @param string[] $excludedPackages
     */
    public function setExcludedPackages(array $excludedPackages): void
    {
        $this->excludedPackages = $excludedPackages;
    }

    public function setOverrideAutoload(stdClass $object): void
    {
        $this->overrideAutoload = new OverrideAutoload($object);
    }

    /**
     * Enable or disable autoloader generation for prefixed dependencies.
     */
    public function setGenerateAutoloader(bool $generateAutoloader): void
    {
        $this->generateAutoloader = $generateAutoloader;
    }

    public function setDeleteVendorDir(bool $deleteVendorDir): void
    {
        $this->deleteVendorDir = $deleteVendorDir;
    }

    public function isValidMozartConfig(): bool
    {
        $required = [ 'depNamespace', 'depDirectory', 'classmapDir', 'classmapPrefix' ];

        foreach ($required as $requiredProp) {
            if (empty($this->$requiredProp)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fill empty configuration properties with sensible defaults, using
     * information from the root package when available. Properties that
     * already have explicit values are never overwritten.
     *
     * Derivation order matters: dep_directory -> classmap_directory ->
     * dep_namespace -> classmap_prefix.
     */
    public function applyDefaults(Package $package): void
    {
        if (empty($this->depDirectory)) {
            $this->depDirectory = 'vendor-prefixed/';
        }

        if (empty($this->classmapDir)) {
            $this->classmapDir = $this->depDirectory;
        }

        if (empty($this->depNamespace)) {
            $this->depNamespace = $this->inferDepNamespace($package);
        }

        if (empty($this->classmapPrefix)) {
            $this->classmapPrefix = $this->deriveClassmapPrefix($this->depNamespace);
        }
    }

    /**
     * Return the names of required configuration fields that are still empty.
     *
     * @return string[]
     */
    public function getMissingConfigFields(): array
    {
        $fields = [
            'depNamespace'  => 'dep_namespace',
            'depDirectory'  => 'dep_directory',
            'classmapDir'   => 'classmap_directory',
            'classmapPrefix' => 'classmap_prefix',
        ];

        $missing = [];
        foreach ($fields as $prop => $label) {
            if (empty($this->$prop)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * Infer the dependency namespace from the root package's PSR-4 autoload
     * entry or, failing that, from the package name.
     */
    private function inferDepNamespace(Package $package): string
    {
        foreach ($package->getAutoloaders() as $autoloader) {
            if ($autoloader instanceof Psr4) {
                $namespace = $autoloader->getSearchNamespace();
                if (!empty($namespace)) {
                    return $namespace . '\\Dependencies';
                }
            }
        }

        if (isset($package->name) && !empty($package->name) && str_contains($package->name, '/')) {
            $parts = explode('/', $package->name);
            $converted = array_map(function (string $part): string {
                return str_replace(' ', '', ucwords(str_replace('-', ' ', $part)));
            }, $parts);

            return implode('\\', $converted) . '\\Dependencies';
        }

        return '';
    }

    /**
     * Derive a classmap prefix from a namespace by replacing backslashes
     * with underscores.
     */
    private function deriveClassmapPrefix(string $namespace): string
    {
        if (empty($namespace)) {
            return '';
        }

        return str_replace('\\', '_', trim($namespace, '\\')) . '_';
    }

    public function isExcludedPackage(Package $package): bool
    {
        return in_array($package->getName(), $this->getExcludedPackages());
    }

    /**
     * Returns the configured dependency directory, with an appended directory
     * separator, if one isn't at the end of the configured string yet.
     */
    public function getDepDirectory(): string
    {
        $dir = str_replace('/', DIRECTORY_SEPARATOR, $this->depDirectory);
        return trim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function getClassmapDirectory(): string
    {
        $dir = str_replace('/', DIRECTORY_SEPARATOR, $this->classmapDir);
        return trim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Check whether autoloader generation is enabled.
     */
    public function getGenerateAutoloader(): bool
    {
        return $this->generateAutoloader;
    }

    public function getDeleteVendorDirectories(): bool
    {
        return $this->deleteVendorDir;
    }

    public function getDependencyNamespace(): string
    {
        $namespace = preg_replace("/\\\{2,}$/", "\\", $this->depNamespace . "\\");

        if (empty($namespace)) {
            throw new ConfigurationException('Could not get target dependency namespace');
        }

        return $namespace;
    }

    public function getClassmapPrefix(): string
    {
        return $this->classmapPrefix;
    }

    /**
     * Get the prefix for global-scope constant renaming.
     */
    public function getConstantPrefix(): string
    {
        return $this->constantPrefix;
    }

    /**
     * Get the prefix for global-scope function renaming.
     */
    public function getFunctionsPrefix(): string
    {
        return $this->functionsPrefix;
    }

    public function getOverrideAutoload(): OverrideAutoload
    {
        return $this->overrideAutoload;
    }

    /**
     * @return string[]
     */
    public function getExcludedPackages(): array
    {
        return $this->excludedPackages;
    }

    public function setWorkingDir(string $workingDir): void
    {
        $this->workingDir = $workingDir;
    }

    public function getWorkingDir(): string
    {
        return rtrim($this->workingDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
