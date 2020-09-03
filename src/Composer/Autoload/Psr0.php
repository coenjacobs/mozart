<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Psr0 extends NamespaceAutoloader
{
    public static function processConfig($autoloadConfig)
    {
        $psr0s = array();

        foreach ($autoloadConfig as $key => $value) {
            $psr0s[] = new Psr0($key, $value);
        }

        return $psr0s;
    }
}
