<?php

namespace CoenJacobs\Mozart\Composer;

use Composer\Factory;
use Composer\IO\NullIO;
use JsonMapper\JsonMapperFactory;
use JsonMapper\Middleware\Rename\Rename;
use stdClass;

class MozartConfig
{
    /**
     * `dep_namespace` is the prefix to be given to PSR-4 namespaced classes.
     * Presumably this will take the form `My_Project_Namespace\dep_directory`.
     *
     * @link https://www.php-fig.org/psr/psr-4/
     *
     * @var string
     */
    protected $depNamespace;

    /**
     * `dep_directory` is the directory Mozart will create and copy PSR-4 autoloading classes into.
     * It is defined relative to your project's composer.json, i.e. relative to where Mozart will be run, with no
     * leading slash.
     *
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
     * After completing `mozart compose` should the source files be deleted?
     * This does not affect symlinked directories.
     *
     * @var bool
     */
    protected $delete_vendor_directories = true;

    /**
     *
     * @throws \Exception
     */
    public function __construct(stdClass $mozartExtra = null)
    {

        if (!is_null($mozartExtra)) {
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

            $mapper->mapObject($mozartExtra, $this);
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

        $io = new NullIO();
        $composer = Factory::create($io, $filePath);

        if (!isset($composer->getPackage()->getExtra()['mozart'])) {
            throw new \Exception('`extra->mozart` key not set in `composer.json`.');
        }

        $mozartExtra = (object) $composer->getPackage()->getExtra()['mozart'];

        $mozartConfig = new MozartConfig($mozartExtra);



        if (empty($mozartConfig->getPackages())) {
            $composerRequire = $composer->getPackage()->getRequires();

            $mozartConfig->setPackages(array_keys($composerRequire));
        }

        return $mozartConfig;
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
     * `dep_directory` will always be returned without a leading slash and with a trailing slash.
     *
     * @return string
     */
    public function getDepDirectory(): string
    {
        return trim(preg_replace('/[\/\\\\]+/', DIRECTORY_SEPARATOR, $this->depDirectory), DIRECTORY_SEPARATOR). DIRECTORY_SEPARATOR ;
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
        return $this->delete_vendor_directories;
    }

    /**
     * @param bool $delete_vendor_directories
     */
    public function setDeleteVendorDirectories(bool $delete_vendor_directories): void
    {
        $this->delete_vendor_directories = $delete_vendor_directories;
    }
}
