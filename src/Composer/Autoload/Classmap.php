<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Classmap implements Autoloader
{
    /** @var array */
    public $files = [];

    /** @var array */
    public $paths = [];

    /**
     * Classmap constructor.
     *
     * @param string[] $files Individual .php files specified in the classmap.
     * @param string[] $paths Directory paths specified in the classmap.
     */
    public function __construct($files, $paths)
    {
        $this->files = $files;
        $this->paths = array_merge($this->paths, $paths);
    }

    public static function processConfig($autoloadConfig): array
    {
        $files = array();
        $paths = array();

        foreach ($autoloadConfig as $value) {
            if ('.php' == substr($value, -4, 4)) {
                array_push($files, $value);
            } else {
                array_push($paths, $value);
            }
        }

        $classmapAutoloader = new self($files, $paths);

        return [ $classmapAutoloader ] ;
    }
}
