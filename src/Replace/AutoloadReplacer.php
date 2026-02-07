<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

interface AutoloadReplacer extends StringReplacer
{
    public function setAutoloader(Autoloader $autoloader): void;
}
