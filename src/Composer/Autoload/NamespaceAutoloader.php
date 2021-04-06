<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

abstract class NamespaceAutoloader implements Autoloader
{
    /**
     * The namespace, e.g. BrianHenryIE\My_Project
     *
     * '' (empty string) is a valid PSR-4 namespace.
     *
     * @var string
     */
    protected string $namespace;

    /**
     * The subdir of the vendor/domain/package directory that contains the files for this autoloader type.
     *
     * e.g. src/
     *
     * @var string[]
     */
    protected array $paths = [];

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * @return string[]
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * A package's composer.json config autoload key's value, where $key is `psr-0`|`psr-4`|`classmap`.
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

    abstract public function getNamespacePath(): string;
}
