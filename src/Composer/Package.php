<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use Mozart\Composer\Config;

class Package
{
    /** @var string */
    public $path = '';

    /** @var Config */
    public $config;

    /** @var array */
    public $autoloaders = [];

    /** @var array */
    public $dependencies = [];

    public function __construct($path, Config $config = null, $overrideAutoload = null)
    {
        $this->path   = $path;

        if (isset($config)) {
            $config = json_decode(file_get_contents($this->path . '/composer.json'));
            $config = new Config($config);
        }
        $this->config = $config;

        if (isset($overrideAutoload)) {
            $this->config->set('autoload', $overrideAutoload);
        }
    }

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

            $autoconfigs = (array)$autoload->$key;

            /** @var $autoloader Autoloader */
            $autoloader = new $value();
            $autoloader->processConfig($autoconfigs);

            array_push($this->autoloaders, $autoloader);
        }
    }

    public function setConfig(Config $config)
    {
        $this->config = $config;
    }
}
