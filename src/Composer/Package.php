<?php

namespace CoenJacobs\Mozart\Composer;

class Package
{
    /** @var string */
    public $path = '';

    /** @var */
    public $config;

    /** @var array */
    public $autoloaders = [];

    public function __construct( $path )
    {
        $this->path   = $path;
        $this->config = json_decode(file_get_contents($this->path . '/composer.json'));
    }

    public function findAutoloaders()
    {
        $namespace_autoloaders = array(
            'psr-0' => 'CoenJacobs\Mozart\Composer\Autoload\Psr0',
            'psr-4' => 'CoenJacobs\Mozart\Composer\Autoload\Psr4',
        );

        if ( ! isset( $this->config->autoload ) ) return;

        foreach( $namespace_autoloaders as $key => $value ) {
            if ( ! isset( $this->config->autoload->$key) ) continue;

            $autoconfigs = (array)$this->config->autoload->$key;

            $autoloader = new $value();

            foreach( $autoconfigs as $key2 => $value2) {
                $autoloader->namespace = $key2;
                array_push( $autoloader->paths, $value2);
            }

            array_push($this->autoloaders, $autoloader);
        }
    }
}