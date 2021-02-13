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
     * NamespaceAutoloader constructor.
     *
     * @param string $namespace
     * @param string $paths
     */
    public function __construct($namespace, $paths)
    {
        $this->namespace = $namespace;
        $this->paths[] = $paths;
    }

    /**
     * @return string
     */
    public function getSearchNamespace(): string
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
