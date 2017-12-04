<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

class Package
{
    /** @var string */
    public $path = '';

    /** @var */
    public $config;

    /** @var array */
    public $autoloaders = [];

    public function __construct($path)
    {
        $this->path   = $path;
        $this->config = json_decode(file_get_contents($this->path . '/composer.json'));
    }

    public function findAutoloaders()
    {
        $namespace_autoloaders = array(
            'psr-0'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr0',
            'psr-4'    => 'CoenJacobs\Mozart\Composer\Autoload\Psr4',
            'classmap' => 'CoenJacobs\Mozart\Composer\Autoload\Classmap',
        );

        if (! isset($this->config->autoload)) {
            return;
        }

        foreach ($namespace_autoloaders as $key => $value) {
            if (! isset($this->config->autoload->$key)) {
                continue;
            }

            $autoconfigs = (array)$this->config->autoload->$key;

            /** @var $autoloader Autoloader */
            $autoloader = new $value();
            $autoloader->processConfig($autoconfigs);

            array_push($this->autoloaders, $autoloader);
        }
    }
}
