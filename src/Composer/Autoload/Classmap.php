<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Classmap implements Autoloader
{
    /** @var array */
    public $paths = [];

    public function processConfig($config)
    {
        foreach( $config as $value) {
            array_push( $this->paths, $value);
        }
    }
}