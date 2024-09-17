<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

abstract class NamespaceAutoloader extends AbstractAutoloader
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
     */
    public function processConfig($autoloadConfig): void
    {
        if (is_array($autoloadConfig)) {
            foreach ($autoloadConfig as $path) {
                array_push($this->paths, $path);
            }

            return;
        }
        array_push($this->paths, $autoloadConfig);
    }

    public function getNamespace(): string
    {
        return rtrim($this->namespace, '\\') . '\\';
    }

    public function getSearchNamespace(): string
    {
        return rtrim($this->namespace, '\\');
    }

    public function getNamespacePath(): string
    {
        return '';
    }
}
