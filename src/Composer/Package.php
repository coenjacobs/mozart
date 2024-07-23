<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use stdClass;

class Package
{
    /** @var string */
    public $path = '';

    /** @var Config */
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
    public function __construct($path, Config $config = null, $overrideAutoload = null)
    {
        $this->path   = $path;

        if (isset($config)) {
            $config = Config::loadFromFile($this->path . '/composer.json');
        }
        $this->config = $config;

        if (isset($overrideAutoload)) {
            $this->config->set('autoload', $overrideAutoload);
        }
    }

    /**
     * @return void
     */
    public function findAutoloaders()
    {
        $namespace_autoloaders = array(
            'psr-0'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr0',
            'psr-4'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr4',
            'classmap' => 'CoenJacobs\Mozart\Composer\Autoload\Classmap',
        );

        $autoload = $this->config->get('autoload');

        if ($autoload === false) {
            return;
        }

        foreach ($namespace_autoloaders as $key => $value) {
            if (! isset($autoload->$key)) {
                continue;
            }

            $autoloadConfig = (array)$autoload->$key;

            /** @var Autoloader $autoloader */
            $autoloader = new $value();
            $autoloader->processConfig($autoloadConfig);

            array_push($this->autoloaders, $autoloader);
        }
    }

    public function setConfig(Config $config)
    {
        $this->config = $config;
    }
}
