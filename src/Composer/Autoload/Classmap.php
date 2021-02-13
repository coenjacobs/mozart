<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Classmap implements Autoloader
{
    /** @var array */
    public $files = [];

    /** @var array */
    public $paths = [];


    public function __construct($files, $paths)
    {

        $this->files = $files;
        $this->paths = $paths;
    }

    public static function processConfig($autoloadConfig)
    {
        $files = array();
        $paths = array();

        foreach ($autoloadConfig as $value) {
            if ('.php' == substr($value, '-4', 4)) {
                array_push($files, $value);
            } else {
                array_push($paths, $value);
            }
        }

        $classmapAutoloader = new self($files, $paths);

        return [ $classmapAutoloader ] ;
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
