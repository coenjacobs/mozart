<?php
/**
 * Object for getting typed values from composer.json.
 *
 * Use this for dependencies. Use ProjectComposerPackage for the primary composer.json.
 */

namespace BrianHenryIE\Strauss\Composer;

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

    /**
     * Create a PHP object to represent a composer package.
     *
     * @param string $path The absolute path to the vendor folder with the composer.json "name", i.e. the domain/package
     *                     definition, which is the vendor subdir from where the package's composer.json should be read.
     * @param stdClass $overrideAutoload Optional configuration to replace the package's own autoload definition with
     *                                    another which Mozart can use.
     */
    public function __construct(string $path, array $overrideAutoload = null)
    {

        if (is_dir($path)) {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
        }

        $composer = Factory::create(new NullIO(), $path);

        $this->composer = $composer;

        $this->name = $composer->getPackage()->getName();

        if (!is_null($overrideAutoload)) {
            $composer->getPackage()->setAutoload($overrideAutoload);
        }

        $this->autoload = $composer->getPackage()->getAutoload();

        foreach ($composer->getPackage()->getRequires() as $name => $packageLink) {
            $this->requiresNames[] = $packageLink->getTarget();
        }
    }

    /**
     * Composer package project name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
}
