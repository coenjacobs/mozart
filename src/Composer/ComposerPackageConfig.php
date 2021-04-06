<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use Composer\Factory;
use Composer\IO\NullIO;
use stdClass;

class ComposerPackageConfig
{

    /**
     * The json decoded composer.json for the package (the original).
     *
     * @var stdClass
     */
    protected stdClass $config;

    /**
     * The name of the project in composer.json.
     *
     * e.g. brianhenryie/my-project
     *
     * @var string
     */
    protected string $name;


    /** @var string */
    protected string $path = '';

    /**
     * The discovered classmap, psr0 and psr4 autoload keys discovered (files autoloaders not handled)
     *
     * @var Autoloader[]
     */
    protected array $autoloaders = [];

    /**
     * Packages this package depends on (the requires: key in the composer.json)
     * @var ComposerPackageConfig[]
     */
    protected array $dependencies = [];

    /**
     * Create a PHP object to represent a composer package.
     *
     * @param string $path The path to the vendor folder with the composer.json "name", i.e. the domain/package
     *                     definition, which is the vendor subdir from where the package's composer.json should be read.
     * @param stdClass $overrideAutoload Optional configuration to replace the package's own autoload definition with
     *                                    another which Mozart can use.
     */
    public function __construct($path, $overrideAutoload = null)
    {
        $this->path   = rtrim($path, DIRECTORY_SEPARATOR);
        $this->config = json_decode(file_get_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json'));

        $composer = Factory::create(new NullIO(), $this->path .DIRECTORY_SEPARATOR . 'composer.json');

        $this->name = $composer->getPackage()->getName();

        if (!is_null($overrideAutoload)) {
            $this->config->autoload = $overrideAutoload;
        }
    }


    public function getName()
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return stdClass
     */
    public function getConfig(): stdClass
    {
        return $this->config;
    }


    /**
     * @return Autoloader[]
     */
    public function getAutoloaders(): array
    {
        return $this->autoloaders;
    }

    /**
     * @param Autoloader[] $autoloaders
     */
    public function setAutoloaders(array $autoloaders): void
    {
        $this->autoloaders = $autoloaders;
    }

    /**
     * @return ComposerPackageConfig[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @param ComposerPackageConfig[] $dependencies
     */
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    /**
     * @return void
     */
    public function findAutoloaders(): void
    {
        $namespace_autoloaders = array(
            'psr-0'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr0',
            'psr-4'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr4',
            'classmap' => 'CoenJacobs\Mozart\Composer\Autoload\Classmap',
        );

        if (! isset($this->config->autoload) || empty($this->config->autoload)) {
            return;
        }

        $autoload = (array) $this->config->autoload;

        foreach ($namespace_autoloaders as $key => $value) {
            if (! isset($autoload[$key])) {
                continue;
            }

            $autoloadConfig = $autoload[$key];

            /** @var Autoloader $autoloader */
            $autoloader = new $value();
            $autoloader->processConfig($autoloadConfig);

            array_push($this->autoloaders, $autoloader);
        }
    }
}
