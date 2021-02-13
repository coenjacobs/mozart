<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

interface Autoloader
{

    /**
     *
     * @param $autoloadConfig A package's composer.json config autoload key's value, where $key is `psr-0`|`psr-4`|`classmap`.
     *
     * @return Autoloader[]
     */
    public static function processConfig($autoloadConfig);

    public function getSearchNamespace();

}
