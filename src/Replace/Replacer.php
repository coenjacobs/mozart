<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

interface Replacer
{
    public function setAutoloader(Autoloader $autoloader): void;
    public function replace(string $contents): string;
}
