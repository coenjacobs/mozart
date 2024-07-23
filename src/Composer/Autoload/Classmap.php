<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Classmap implements Autoloader
{
    /** @var array */
    public $files = [];

    /** @var array */
    public $paths = [];

    /**
     * @return void
     */
    public function processConfig($autoloadConfig)
    {
        foreach ($autoloadConfig as $value) {
            if ('.php' == substr($value, -4, 4)) {
                array_push($this->files, $value);
            } else {
                array_push($this->paths, $value);
            }
        }
    }

    /**
     * @throws \Exception
     *
     * @return void
     */
    public function getSearchNamespace()
    {
        throw new \Exception('Classmap autoloaders do not contain a namespace and this method can not be used.');
    }
}
