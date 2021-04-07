<?php
/**
 * The extra/mozart key in composer.json.
 */

namespace CoenJacobs\Mozart\Composer\Extra;

use Composer\Composer;
use JsonMapper\JsonMapperFactory;
use JsonMapper\Middleware\Rename\Rename;

class NannerlConfig
{
    /**
     * The output directory.
     *
     * Probably `nannerl/` or `src/nannerl/`.
     *
     * @var string
     */
    protected $targetDirectory = 'nannerl' . DIRECTORY_SEPARATOR;

    /**
     * `namespacePrefix` is the prefix to be given to any namespaces.
     * Presumably this will take the form `My_Project_Namespace\dep_directory`.
     *
     * @link https://www.php-fig.org/psr/psr-4/
     *
     * @var string
     */
    protected $namespacePrefix;

    /**
     * @var string
     */
    protected $classmapPrefix;

    /**
     * Packages to copy and (maybe) prefix.
     *
     * If this is empty, the "requires" list in the project composer.json is used.
     *
     * @var array
     */
    protected array $packages = [];

    /**
     * When prefixing, do not prefix these packages (which have been copied).
     *
     * @var array
     */
    protected $excludePrefixPackages = [];

    /**
     * An array of autoload keys to replace packages' existing autoload key.
     *
     * e.g. when
     * * A package has no autoloader
     * * A package specified both a PSR-4 and a classmap but only needs one
     * ...
     *
     * @var array
     */
    protected $overrideAutoload = [];

    /**
     * After completing `nannerl compose` should the source files be deleted?
     * This does not affect symlinked directories.
     *
     * @var bool
     */
    protected $deleteVendorDirectories = false;

    /**
     * Read any existing Mozart config.
     * Overwrite it with any Nannerl config.
     * Provide sensible defaults.
     *
     * @throws \Exception
     */
    public function __construct(Composer $composer)
    {

        // Backwards compatibility with Mozart.
        if (isset($composer->getPackage()->getExtra()['mozart'])) {
            $mozartExtra = (object) $composer->getPackage()->getExtra()['mozart'];

            // Default setting for Mozart.
            $this->setDeleteVendorDirectories(true);

            $mapper = ( new JsonMapperFactory() )->bestFit();

            $rename = new Rename();
            $rename->addMapping(NannerlConfig::class, 'dep_directory', 'targetDirectory');
            $rename->addMapping(NannerlConfig::class, 'dep_namespace', 'namespacePrefix');
            $rename->addMapping(NannerlConfig::class, 'exclude_packages', 'excludePrefixPackages');

            $mapper->unshift($rename);
            $mapper->push(new \JsonMapper\Middleware\CaseConversion(
                \JsonMapper\Enums\TextNotation::UNDERSCORE(),
                \JsonMapper\Enums\TextNotation::CAMEL_CASE()
            ));

            $mapper->mapObject($mozartExtra, $this);
        }

        if (isset($composer->getPackage()->getExtra()['nannerl'])) {
            $nannerlExtra = (object) $composer->getPackage()->getExtra()['nannerl'];

            $mapper = ( new JsonMapperFactory() )->bestFit();

            $mapper->push(new \JsonMapper\Middleware\CaseConversion(
                \JsonMapper\Enums\TextNotation::UNDERSCORE(),
                \JsonMapper\Enums\TextNotation::CAMEL_CASE()
            ));

            $mapper->mapObject($nannerlExtra, $this);
        }

        // Defaults.
        // * Use PSR-4 autoloader key
        // * Use PSR-0 autoloader key
        // * Use the package name
        if (! isset($this->namespacePrefix)) {
            if (isset($composer->getPackage()->getAutoload()['psr-4'])) {
                $this->setNamespacePrefix(array_key_first($composer->getPackage()->getAutoload()['psr-4']));
            } elseif (isset($composer->getPackage()->getAutoload()['psr-0'])) {
                $this->setNamespacePrefix(array_key_first($composer->getPackage()->getAutoload()['psr-0']));
            } else {
                $packageName = $composer->getPackage()->getName();
                $classmapPrefix = preg_replace('/[^\w\/]+/', '_', $packageName);
                $classmapPrefix = str_replace('/', '\\', $classmapPrefix) . '\\';
                $classmapPrefix = preg_replace_callback('/(?<=^|_|\\\\)[a-z]/', function ($match) {
                    return strtoupper($match[0]);
                }, $classmapPrefix);
                $this->setNamespacePrefix($classmapPrefix);
            }
        }

        if (! isset($this->classmapPrefix)) {
            if (isset($composer->getPackage()->getAutoload()['psr-4'])) {
                $autoloadKey = array_key_first($composer->getPackage()->getAutoload()['psr-4']);
                $classmapPrefix = str_replace("\\", "_", $autoloadKey);
                $this->setClassmapPrefix($classmapPrefix);
            } elseif (isset($composer->getPackage()->getAutoload()['psr-0'])) {
                $autoloadKey = array_key_first($composer->getPackage()->getAutoload()['psr-0']);
                $classmapPrefix = str_replace("\\", "_", $autoloadKey);
                $this->setClassmapPrefix($classmapPrefix);
            } else {
                $packageName = $composer->getPackage()->getName();
                $classmapPrefix = preg_replace('/[^\w\/]+/', '_', $packageName);
                $classmapPrefix = str_replace('/', '\\', $classmapPrefix);
                $classmapPrefix = preg_replace_callback('/(?<=^|_|\\\\)[a-z]/', function ($match) {
                    return strtoupper($match[0]);
                }, $classmapPrefix);
                $classmapPrefix = str_replace("\\", "_", $classmapPrefix);
                $this->setClassmapPrefix($classmapPrefix);
            }
        }

        if (empty($this->packages)) {
            $this->packages = array_map(function (\Composer\Package\Link $element) {
                return $element->getTarget();
            }, $composer->getPackage()->getRequires());
        }
    }

    /**
     * `target_directory` will always be returned without a leading slash and with a trailing slash.
     *
     * @return string
     */
    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    /**
     * @param string $targetDirectory
     */
    public function setTargetDirectory(string $targetDirectory): void
    {
        $this->targetDirectory = trim(
            preg_replace(
                '/[\/\\\\]+/',
                DIRECTORY_SEPARATOR,
                $targetDirectory
            ),
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR ;
    }

    /**
     * @return string
     */
    public function getNamespacePrefix(): string
    {
        return $this->namespacePrefix;
    }

    /**
     * @param string $namespacePrefix
     */
    public function setNamespacePrefix(string $namespacePrefix): void
    {
        $this->namespacePrefix = $namespacePrefix;
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
    public function getExcludePrefixPackages(): array
    {
        return $this->excludePrefixPackages;
    }

    /**
     * @param array $excludePrefixPackages
     */
    public function setExcludePrefixPackages(array $excludePrefixPackages): void
    {
        $this->excludePrefixPackages = $excludePrefixPackages;
    }

    /**
     * @return array
     */
    public function getOverrideAutoload(): array
    {
        return $this->overrideAutoload;
    }

    /**
     * @param array $overrideAutoload
     */
    public function setOverrideAutoload(array $overrideAutoload): void
    {
        $this->overrideAutoload = $overrideAutoload;
    }

    /**
     * @return bool
     */
    public function isDeleteVendorDirectories(): bool
    {
        return $this->deleteVendorDirectories;
    }

    /**
     * @param bool $deleteVendorDirectories
     */
    public function setDeleteVendorDirectories(bool $deleteVendorDirectories): void
    {
        $this->deleteVendorDirectories = $deleteVendorDirectories;
    }

    /**
     * @return array
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * @param array $packages
     */
    public function setPackages(array $packages): void
    {
        $this->packages = $packages;
    }
}
