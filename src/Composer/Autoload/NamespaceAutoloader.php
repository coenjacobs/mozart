<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

abstract class NamespaceAutoloader implements Autoloader
{
    /** @var string */
    public $namespace = '';

    /**
     * The subdir of the vendor/domain/package directory that contains the files for this autoloader type.
     *
     * e.g. src/
     *
     * @var string[]
     */
    public $paths = [];

    /**
     * A package's composer.json config autoload key's value, where $key is `psr-1`|`psr-4`|`classmap`.
     *
     * @param $autoloadConfig
     *
     * @return void
     */
    public function processConfig($autoloadConfig)
    {
        foreach ($autoloadConfig as $key => $value) {
            $this->namespace = $key;
            array_push($this->paths, $value);
        }
    }

    /**
     * @return string
     */
    public function getSearchNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getNamespacePath()
    {
        return '';
    }
}
