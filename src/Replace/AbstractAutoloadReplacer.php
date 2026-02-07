<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

abstract class AbstractAutoloadReplacer implements AutoloadReplacer
{
    public Autoloader $autoloader;

    public function setAutoloader(Autoloader $autoloader): void
    {
        $this->autoloader = $autoloader;
    }
}
