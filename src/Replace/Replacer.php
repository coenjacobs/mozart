<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

interface Replacer extends StringReplacer
{
    public function setAutoloader(Autoloader $autoloader): void;
}
