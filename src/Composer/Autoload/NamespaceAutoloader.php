<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

abstract class NamespaceAutoloader implements Autoloader
{
    /** @var string */
    public $namespace = '';

    /** @var array */
    public $paths = [];

    public function processConfig($config)
    {
        foreach ($config as $key => $value) {
            $this->namespace = $key;
            array_push($this->paths, $value);
        }
    }

    public function getSearchNamespace()
    {
        return $this->namespace;
    }

    public function getNamespacePath()
    {
        return '';
    }
}
