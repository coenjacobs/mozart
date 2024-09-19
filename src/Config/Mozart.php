<?php

namespace CoenJacobs\Mozart\Config;

use stdClass;
use CoenJacobs\Mozart\Config\OverrideAutoload;
use CoenJacobs\Mozart\Config\Package;
use Exception;

class Mozart
{
    use ReadsConfig;

    public string $depNamespace;
    public string $depDirectory;
    public string $classmapDir;
    public string $classmapPrefix;

    /** @var string[] */
    public array $packages = [];

    /** @var string[] */
    public array $excludedPackages = [];

    public OverrideAutoload $overrideAutoload;
    public bool $deleteVendorDir = true;

    public string $workingDir = '';

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
        return trim($this->depDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function getClassmapDirectory(): string
    {
        return trim($this->classmapDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function getDeleteVendorDirectories(): bool
    {
        return $this->deleteVendorDir;
    }

    public function getDependencyNamespace(): string
    {
        $namespace = preg_replace("/\\\{2,}$/", "\\", $this->depNamespace . "\\");

        if (empty($namespace)) {
            throw new Exception('Could not get target dependency namespace');
        }

        return $namespace;
    }

    public function getClassmapPrefix(): string
    {
        return $this->classmapPrefix;
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
