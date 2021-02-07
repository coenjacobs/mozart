<?php

namespace CoenJacobs\Mozart\Composer;

use JsonMapper\JsonMapperFactory;
use JsonMapper\Middleware\Rename\Rename;
use stdClass;

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
    protected $excludedPackages;

    /**
     * @var array|null
     */
    protected $overrideAutoload;

    /**
     * @var bool
     */
    protected $delete_vendor_directories;

    /**
     *
     */
    public function __construct(stdClass $composer = null)
    {

        if (!is_null($composer)) {
            if (!isset($composer->extra)) {
                throw new \Exception('`extra` key not set in `composer.json`.');
            }

            if (!isset($composer->extra->mozart)) {
                throw new \Exception('`extra->mozart` key not set in `composer.json`.');
            }

            if (!is_object($composer->extra->mozart)) {
                throw new \Exception('`extra->mozart` key in `composer.json` is not an object.');
            }

            // @see JsonMapper\Middleware\CaseConversion wasn't working.
            $rename = new Rename();
            $rename->addMapping(MozartConfig::class, 'dep_namespace', 'depNamespace');
            $rename->addMapping(MozartConfig::class, 'classmap_directory', 'classmapDirectory');
            $rename->addMapping(MozartConfig::class, 'dep_directory', 'depDirectory');
            $rename->addMapping(MozartConfig::class, 'classmap_prefix', 'classmapPrefix');
            $rename->addMapping(MozartConfig::class, 'override_autoload', 'overrideAutoload');
            $rename->addMapping(MozartConfig::class, 'delete_vendor_directories', 'deleteVendorDirectories');
            $rename->addMapping(MozartConfig::class, 'exclude_packages', 'excludedPackages');

            $mapper = ( new JsonMapperFactory() )->bestFit();
            $mapper->unshift($rename);

            $mapper->mapObject($composer->extra->mozart, $this);

            if (empty($this->getPackages())) {
                $this->setPackages(array_keys((array) $composer->require));
            }
        }
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

        if (!is_file($filePath)) {
            throw new \Exception("Expected `composer.json` not found at {$filePath}");
        }

        $composer = json_decode(file_get_contents($filePath));

        if (!is_object($composer)) {
            throw new \Exception("Expected `composer.json` at {$filePath} was not valid JSON.");
        }

        return new MozartConfig($composer);
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
        return trim($this->depDirectory, '/\\'.DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
        return trim($this->classmapDirectory, '/\\'.DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
    public function getExcludedPackages(): array
    {
        return $this->excludedPackages ?? array();
    }

    /**
     * @param array|null $excludedPackages
     */
    public function setExcludedPackages(?array $excludedPackages): void
    {
        $this->excludedPackages = $excludedPackages ?? array();
    }

    /**
     * @return array
     */
    public function getOverrideAutoload(): ?array
    {
        return $this->overrideAutoload ?? array();
    }

    /**
     * @param array $overrideAutoload
     */
    public function setOverrideAutoload(?array $overrideAutoload): void
    {
        $this->overrideAutoload = $overrideAutoload ?? array();
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
