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
     * @var array<string>
     */
    public $paths = [];

    /**
     * A package's composer.json config autoload key's value, where $key is `psr-1`|`psr-4`|`classmap`.
     *
     * @param $autoloadConfig
     *
     * @return void
     */
    public function processConfig($autoloadConfig): void
    {
        if (is_array($autoloadConfig)) {
            foreach ($autoloadConfig as $path) {
                array_push($this->paths, $path);
            }
        } else {
            array_push($this->paths, $autoloadConfig);
        }
    }

    public function getSearchNamespace(): string
    {
        return $this->namespace;
    }

    public function getNamespacePath(): string
    {
        return '';
    }
}
