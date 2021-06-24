<?php
/**
 * Object for getting typed values from composer.json.
 *
 * Use this for dependencies. Use ProjectComposerPackage for the primary composer.json.
 */

namespace BrianHenryIE\Strauss\Composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use stdClass;

class ComposerPackage
{
    /**
     * The composer.json file as parsed by Composer.
     *
     * @see \Composer\Factory::create
     *
     * @var \Composer\Composer
     */
    protected \Composer\Composer $composer;

    /**
     * The name of the project in composer.json.
     *
     * e.g. brianhenryie/my-project
     *
     * @var string
     */
    protected string $name;

    /**
     * Virtual packages and meta packages do not have a composer.json
     *
     * @var ?string
     */
    protected ?string $path = null;

    /**
     * The discovered files, classmap, psr0 and psr4 autoload keys discovered (as parsed by Composer).
     *
     * @var array<string, array<string, string>>
     */
    protected array $autoload = [];

    /**
     * The names in the composer.json's "requires" field (without versions).
     *
     * @var array
     */
    protected array $requiresNames = [];

    protected string $license;

    /**
     * @param string $absolutePath The absolute path to the vendor folder with the composer.json "name",
     *          i.e. the domain/package definition, which is the vendor subdir from where the package's
     *          composer.json should be read.
     * @param ?array $overrideAutoload Optional configuration to replace the package's own autoload definition with
     *                                    another which Strauss can use.
     * @return ComposerPackage
     */
    public static function fromFile(string $absolutePath, array $overrideAutoload = null): ComposerPackage
    {
        if (is_dir($absolutePath)) {
            $absolutePath = rtrim($absolutePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
        }

        $composer = Factory::create(new NullIO(), $absolutePath);

        return new ComposerPackage($composer, $overrideAutoload);
    }

    /**
     * @param array $jsonArray composer.json decoded to array
     * @param ?array $overrideAutoload
     * @return ComposerPackage
     */
    public static function fromComposerJsonArray($jsonArray, array $overrideAutoload = null): ComposerPackage
    {
        $factory = new Factory();
        $io = new NullIO();
        $composer = $factory->createComposer($io, $jsonArray);

        return new ComposerPackage($composer, $overrideAutoload);
    }

    /**
     * Create a PHP object to represent a composer package.
     *
     * @param array $overrideAutoload Optional configuration to replace the package's own autoload definition with
     *                                    another which Strauss can use.
     */
    public function __construct(Composer $composer, array $overrideAutoload = null)
    {
        $this->composer = $composer;

        $this->name = $composer->getPackage()->getName();

        $composerJsonFileAbsolute = $composer->getConfig()->getConfigSource()->getName();

        // Not every package gets installed to a folder matching its name (crewlabs/unsplash).
        if (1 === preg_match('/.*\/([^\/]*\/[^\/]*)\/composer.json/', $composerJsonFileAbsolute, $output_array)) {
            $this->path = $output_array[1];
        } else {
            // Some packages have no composer.json (woocommerce/action-scheduler).
            $vendorDirectory = $this->composer->getConfig()->get('vendor-dir');
            if (file_exists($vendorDirectory . DIRECTORY_SEPARATOR . $this->name)) {
                $this->path = $this->name;
            }
            // and meta packages have no path.
        }

        if (!is_null($overrideAutoload)) {
            $composer->getPackage()->setAutoload($overrideAutoload);
        }

        $this->autoload = $composer->getPackage()->getAutoload();

        foreach ($composer->getPackage()->getRequires() as $_name => $packageLink) {
            $this->requiresNames[] = $packageLink->getTarget();
        }

        // Try to get the license from the package's composer.json, assume proprietary (all rights reserved!).
        $this->license = !empty($composer->getPackage()->getLicense())
            ? implode(',', $composer->getPackage()->getLicense())
            : 'proprietary?';
    }

    /**
     * Composer package project name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     *
     * e.g. ['psr-4' => [ 'BrianHenryIE\Project' => 'src' ]]
     *
     * @return array<string, array<int|string, string>>
     */
    public function getAutoload(): array
    {
        return $this->autoload;
    }

    /**
     * The names of the packages in the composer.json's "requires" field (without version).
     *
     * Excludes PHP, ext-*, since we won't be copying or prefixing them.
     *
     * @return string[]
     */
    public function getRequiresNames(): array
    {
        // Unset PHP, ext-*.
        $removePhpExt = function ($element) {
            return !( 0 === strpos($element, 'ext') || 'php' === $element );
        };

        return array_filter($this->requiresNames, $removePhpExt);
    }

    public function getLicense():string
    {
        return $this->license;
    }
}
