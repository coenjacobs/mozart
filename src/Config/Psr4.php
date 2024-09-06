<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;

class Psr4 extends NamespaceAutoloader
{
    public function getSearchNamespace(): string
    {
        return trim($this->namespace, '\\');
    }

    public function getNamespacePath(): string
    {
        return str_replace('\\', DIRECTORY_SEPARATOR, $this->namespace);
    }
}
