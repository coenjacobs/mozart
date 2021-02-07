<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

interface Autoloader
{
    public function processConfig($autoloadConfig);
    public function getSearchNamespace();
}
