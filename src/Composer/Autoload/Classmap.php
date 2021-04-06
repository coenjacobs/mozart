<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Classmap implements Autoloader
{
    /**
     * The files specified in the classmap.
     *
     * @var string[]
     */
    protected array $files = [];

    /**
     * The directories specified in the classmap.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * @return string[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return array
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

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
