<?php
/**
 * The extra/strauss key in composer.json.
 */

namespace BrianHenryIE\Strauss\Composer\Extra;

use Composer\Composer;
use Exception;
use JsonMapper\JsonMapperFactory;
use JsonMapper\Middleware\Rename\Rename;

class StraussConfig
{
    /**
     * The output directory.
     *
     * Probably `strauss/` or `src/strauss/`.
     *
     * @var string
     */
    protected $targetDirectory = 'strauss';

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

    // Back-compatibility with Mozart.
    private array $excludePackages;

    /**
     * @var array{packages?: string[], namespaces?: string[], filePatterns?: string[]}
     */
    protected array $excludeFromCopy = array();

    /**
     * @var array{packages?: string[], namespaces?: string[], filePatterns?: string[]}
     */
    protected array $excludeFromPrefix = array('filePatterns'=>array('/psr.*/'));


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
     * After completing `strauss compose` should the source files be deleted?
     * This does not affect symlinked directories.
     *
     * @var bool
     */
    protected $deleteVendorFiles = false;

    protected bool $classmapOutput;

    protected array $namespaceReplacementPatterns = array();


    /**
     * Read any existing Mozart config.
     * Overwrite it with any Strauss config.
     * Provide sensible defaults.
     *
     * @throws Exception
     */
    public function __construct(Composer $composer)
    {

        // Backwards compatibility with Mozart.
        if (isset($composer->getPackage()->getExtra()['mozart'])) {
            $mozartExtra = (object) $composer->getPackage()->getExtra()['mozart'];

            // Default setting for Mozart.
            $this->setDeleteVendorFiles(true);

            $mapper = ( new JsonMapperFactory() )->bestFit();

            $rename = new Rename();
            $rename->addMapping(StraussConfig::class, 'dep_directory', 'targetDirectory');
            $rename->addMapping(StraussConfig::class, 'dep_namespace', 'namespacePrefix');

            $rename->addMapping(StraussConfig::class, 'exclude_packages', 'excludePackages');
            $rename->addMapping(StraussConfig::class, 'delete_vendor_directories', 'deleteVendorFiles');

            $mapper->unshift($rename);
            $mapper->push(new \JsonMapper\Middleware\CaseConversion(
                \JsonMapper\Enums\TextNotation::UNDERSCORE(),
                \JsonMapper\Enums\TextNotation::CAMEL_CASE()
            ));

            $mapper->mapObject($mozartExtra, $this);
        }

        if (isset($composer->getPackage()->getExtra()['strauss'])) {
            $straussExtra = (object) $composer->getPackage()->getExtra()['strauss'];

            $mapper = ( new JsonMapperFactory() )->bestFit();

            $mapper->push(new \JsonMapper\Middleware\CaseConversion(
                \JsonMapper\Enums\TextNotation::UNDERSCORE(),
                \JsonMapper\Enums\TextNotation::CAMEL_CASE()
            ));

            $mapper->mapObject($straussExtra, $this);
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
            } elseif ('__root__' !== $composer->getPackage()->getName()) {
                $packageName = $composer->getPackage()->getName();
                $namespacePrefix = preg_replace('/[^\w\/]+/', '_', $packageName);
                $namespacePrefix = str_replace('/', '\\', $namespacePrefix) . '\\';
                $namespacePrefix = preg_replace_callback('/(?<=^|_|\\\\)[a-z]/', function ($match) {
                    return strtoupper($match[0]);
                }, $namespacePrefix);
                $this->setNamespacePrefix($namespacePrefix);
            } elseif (isset($this->classmapPrefix)) {
                $namespacePrefix = rtrim($this->getClassmapPrefix(), '_');
                $this->setNamespacePrefix($namespacePrefix);
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
            } elseif ('__root__' !== $composer->getPackage()->getName()) {
                $packageName = $composer->getPackage()->getName();
                $classmapPrefix = preg_replace('/[^\w\/]+/', '_', $packageName);
                $classmapPrefix = str_replace('/', '\\', $classmapPrefix);
                // Uppercase the first letter of each word.
                $classmapPrefix = preg_replace_callback('/(?<=^|_|\\\\)[a-z]/', function ($match) {
                    return strtoupper($match[0]);
                }, $classmapPrefix);
                $classmapPrefix = str_replace("\\", "_", $classmapPrefix);
                $this->setClassmapPrefix($classmapPrefix);
            } elseif (isset($this->namespacePrefix)) {
                $classmapPrefix = preg_replace('/[^\w\/]+/', '_', $this->getNamespacePrefix());
                $classmapPrefix = rtrim($classmapPrefix, '_') . '_';
                $this->setClassmapPrefix($classmapPrefix);
            }
        }

        if (!isset($this->namespacePrefix) || !isset($this->classmapPrefix)) {
            throw new Exception('Prefix not set. Please set `namespace_prefix`, `classmap_prefix` in composer.json/extra/strauss.');
        }

        if (empty($this->packages)) {
            $this->packages = array_map(function (\Composer\Package\Link $element) {
                return $element->getTarget();
            }, $composer->getPackage()->getRequires());
        }

        // If the bool flag for classmapOutput wasn't set in the Json config.
        if (!isset($this->classmapOutput)) {
            $this->classmapOutput = true;
            // Check each autoloader.
            foreach ($composer->getPackage()->getAutoload() as $autoload) {
                // To see if one of its paths.
                foreach ($autoload as $path) {
                    // Matches the target directory.
                    if (trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR === $this->getTargetDirectory()) {
                        $this->classmapOutput = false;
                        break 2;
                    }
                }
            }
        }

        // TODO: Throw an exception if any regex patterns in config are invalid.
        // https://stackoverflow.com/questions/4440626/how-can-i-validate-regex
        // preg_match('~Valid(Regular)Expression~', null) === false);
    }

    /**
     * `target_directory` will always be returned without a leading slash and with a trailing slash.
     *
     * @return string
     */
    public function getTargetDirectory(): string
    {
        return trim($this->targetDirectory, DIRECTORY_SEPARATOR . '\\/') . DIRECTORY_SEPARATOR;
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
        return trim($this->namespacePrefix, '\\');
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

    public function setExcludeFromCopy(array $excludeFromCopy): void
    {
        $this->excludeFromCopy = $excludeFromCopy;
    }
    public function getExcludePackagesFromCopy(): array
    {
        return $this->excludeFromCopy['packages'] ?? array();
    }

    public function getExcludeNamespacesFromCopy(): array
    {
        return $this->excludeFromCopy['namespaces'] ?? array();
    }

    public function getExcludeFilePatternsFromCopy(): array
    {
        return $this->excludeFromCopy['filePatterns'] ?? array();
    }


    public function setExcludeFromPrefix(array $excludeFromPrefix): void
    {
        $this->excludeFromPrefix = $excludeFromPrefix;
    }

    /**
     * When prefixing, do not prefix these packages (which have been copied).
     *
     * @var string[]
     */
    public function getExcludePackagesFromPrefixing(): array
    {
        return $this->excludeFromPrefix['packages'] ?? array();
    }

    public function getExcludeNamespacesFromPrefixing(): array
    {
        return $this->excludeFromPrefix['namespaces'] ?? array();
    }

    public function getExcludeFilePatternsFromPrefixing(): array
    {
        return $this->excludeFromPrefix['filePatterns'] ?? array();
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
    public function isDeleteVendorFiles(): bool
    {
        return $this->deleteVendorFiles;
    }

    /**
     * @param bool $deleteVendorFiles
     */
    public function setDeleteVendorFiles(bool $deleteVendorFiles): void
    {
        $this->deleteVendorFiles = $deleteVendorFiles;
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

    /**
     * @return bool
     */
    public function isClassmapOutput(): bool
    {
        return $this->classmapOutput;
    }

    /**
     * @param bool $classmapOutput
     */
    public function setClassmapOutput(bool $classmapOutput): void
    {
        $this->classmapOutput = $classmapOutput;
    }

    /**
     * Backwards compatability with Mozart.
     */
    public function setExcludePackages(array $excludePackages)
    {

        if (! isset($this->excludeFromPrefix)) {
            $this->excludeFromPrefix = array();
        }

        $this->excludeFromPrefix['packages'] = $excludePackages;
    }


    /**
     * @return array
     */
    public function getNamespaceReplacementPatterns(): array
    {
        return $this->namespaceReplacementPatterns;
    }

    /**
     * @param array $namespaceReplacementPatterns
     */
    public function setNamespaceReplacementPatterns(array $namespaceReplacementPatterns): void
    {
        $this->namespaceReplacementPatterns = $namespaceReplacementPatterns;
    }
}
