<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;

class Psr4 extends NamespaceAutoloader
{
    /**
     * @return string
     */
    public function getSearchNamespace()
    {
        return trim($this->namespace, '\\');
    }

    /**
     * @return string
     */
    public function getNamespacePath()
    {
        return str_replace('\\', DIRECTORY_SEPARATOR, $this->namespace);
    }
}
