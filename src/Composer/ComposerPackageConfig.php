<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

class Package
{
    /** @var string */
    public $path = '';

    /** @var MozartConfig */
    public $config;

    /** @var array */
    public $autoloaders = [];

    /** @var array */
    public $dependencies = [];

    public function __construct($path, MozartConfig $config = null, $overrideAutoload = null)
    {
        $this->path   = $path;

        if (!isset($config)) {
            $config = MozartConfig::loadFromFile($this->path . '/composer.json');
        }
        $this->config = $config;

        $this->config->setOverrideAutoload($overrideAutoload);
    }

    public function findAutoloaders()
    {
        $namespace_autoloaders = array(
            'psr-0'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr0',
            'psr-4'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr4',
            'classmap' => 'CoenJacobs\Mozart\Composer\Autoload\Classmap',
        );

        if (! isset($this->config->autoload) || empty($this->config->autoload)) {
            return;
        }

        $autoload = $this->config->autoload;

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

    public function setConfig(MozartConfig $config)
    {
        $this->config = $config;
    }
}
