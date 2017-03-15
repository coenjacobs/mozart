<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Psr4 extends NamespaceAutoloader
{
    public function getSearchNamespace()
    {
        return trim($this->namespace, '\\');
    }
}