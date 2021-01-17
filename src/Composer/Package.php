<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\Psr0;
use CoenJacobs\Mozart\Composer\Autoload\Psr4;
use stdClass;

class Package
{
    /** @var string */
    public $path = '';

    /** @var */
    public $config;

    /** @var Autoloader[] */
    public $autoloaders = [];

    /** @var array */
    public $dependencies = [];

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
        $this->path   = $path;
        $this->config = json_decode(file_get_contents($this->path . '/composer.json'));

        if (isset($overrideAutoload)) {
            $this->config->autoload = $overrideAutoload;
        }
    }

    /**
     * @return void
     */
    public function findAutoloaders()
    {
        $namespaceAutoloaders = array(
            'psr-0'    => Psr0::class,
            'psr-4'    => Psr4::class,
            'classmap' => Classmap::class,
        );

        if (! isset($this->config->autoload)) {
            return;
        }

        foreach ($namespaceAutoloaders as $autoloaderType => $className) {
            if (! isset($this->config->autoload->$autoloaderType)) {
                continue;
            }

            /** @var $autoloader Autoloader */
            $autoloader = new $className();
            $autoloader->processConfig((array)$this->config->autoload->$autoloaderType);

            array_push($this->autoloaders, $autoloader);
        }
    }
}
