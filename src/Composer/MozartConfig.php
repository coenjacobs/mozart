<?php

namespace CoenJacobs\Mozart\Composer;

use JsonMapper\JsonMapperFactory;
use JsonMapper\Middleware\Rename\Rename;

class MozartConfig
{
    /**
     * @var string
     */
    protected $depNamespace;

    /**
     * @var string
     */
    protected $depDirectory;

    /**
     * @var string
     */
    protected $classmapDirectory;

    /**
     * @var string
     */
    protected $classmapPrefix;

    /**
     * @var array|null
     */
    protected $packages;

    /**
     * @var array|null
     */
    protected $excludePackages;

    /**
     * @var array|null
     */
    protected $override_autoload;

    /**
     * @var bool
     */
    protected $delete_vendor_directories;

    /**
     * @param array $data
     */
    public function __construct(?array $data = null)
    {
        $this->data = $data;
    }

    /**
     *
     * @link https://jsonmapper.net/
     *
     * @param $filePath
     *
     * @return MozartConfig
     */
    public static function loadFromFile($filePath)
    {

        // @see JsonMapper\Middleware\CaseConversion wasn't working.
        $rename = new Rename();
        $rename->addMapping(MozartConfig::class, 'dep_namespace', 'depNamespace');
        $rename->addMapping(MozartConfig::class, 'classmap_directory', 'classmapDirectory');
        $rename->addMapping(MozartConfig::class, 'dep_directory', 'depDirectory');
        $rename->addMapping(MozartConfig::class, 'classmap_prefix', 'classmapPrefix');
        $rename->addMapping(MozartConfig::class, 'override_autoload', 'overrideAutoload');
        $rename->addMapping(MozartConfig::class, 'delete_vendor_directories', 'deleteVendorDirectories');

        $mapper = (new JsonMapperFactory())->bestFit();
        $mapper->unshift($rename);

        $object = new MozartConfig();

        $composer = json_decode(file_get_contents($filePath));

        $extraMozart = $composer->extra->mozart;

        $mapper->mapObject($extraMozart, $object);

        return $object;
    }

    /**
     * @return string
     */
    public function getDepNamespace(): string
    {
        return $this->depNamespace;
    }

    /**
     * @param string $depNamespace
     */
    public function setDepNamespace(string $depNamespace): void
    {
        $this->depNamespace = $depNamespace;
    }

    /**
     * @return string
     */
    public function getDepDirectory(): string
    {
        return $this->depDirectory;
    }

    /**
     * @param string $depDirectory
     */
    public function setDepDirectory(string $depDirectory): void
    {
        $this->depDirectory = $depDirectory;
    }

    /**
     * @return string
     */
    public function getClassmapDirectory(): string
    {
        return $this->classmapDirectory;
    }

    /**
     * @param string $classmapDirectory
     */
    public function setClassmapDirectory(string $classmapDirectory): void
    {
        $this->classmapDirectory = $classmapDirectory;
    }

    /**
     * @return string
     */
    public function getClassmapPrefix(): string
    {
        return $this->classmapPrefix;
    }

    /**
     * @param string $classmapPrefix
     */
    public function setClassmapPrefix(string $classmapPrefix): void
    {
        $this->classmapPrefix = $classmapPrefix;
    }

    /**
     * @return array
     */
    public function getPackages(): array
    {
        return $this->packages ?? array();
    }

    /**
     * @param array|null $packages
     */
    public function setPackages(?array $packages): void
    {
        $this->packages = $packages ?? array();
    }

    /**
     * @return array
     */
    public function getExcludePackages(): array
    {
        return $this->excludePackages ?? array();
    }

    /**
     * @param array|null $excludePackages
     */
    public function setExcludePackages(?array $excludePackages): void
    {
        $this->excludePackages = $excludePackages ?? array();
    }

    /**
     * @return array
     */
    public function getOverrideAutoload(): ?array
    {
        return $this->override_autoload ?? array();
    }

    /**
     * @param array $override_autoload
     */
    public function setOverrideAutoload(?array $override_autoload): void
    {
        $this->override_autoload = $override_autoload ?? array();
    }

    /**
     * @return bool
     */
    public function isDeleteVendorDirectories(): bool
    {
        return $this->delete_vendor_directories ?? true;
    }

    /**
     * @param bool $delete_vendor_directories
     */
    public function setDeleteVendorDirectories(bool $delete_vendor_directories): void
    {
        $this->delete_vendor_directories = $delete_vendor_directories;
    }
}
