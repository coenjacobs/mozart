<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Classmap implements Autoloader
{
    /** @var array */
    public $files = [];

    /** @var array */
    public $paths = [];

    public function processConfig($config)
    {
        foreach ($config as $value) {
            if ('.php' == substr($value, '-4', 4)) {
                array_push($this->files, $value);
            } else {
                array_push($this->paths, $value);
            }
        }
    }
}
