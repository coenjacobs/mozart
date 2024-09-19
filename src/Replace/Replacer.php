<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

interface Replacer
{
    public function setAutoloader(Autoloader $autoloader): void;
    /**
     * @param string $contents The text to make replacements in.
     */
    public function replace(string $contents): string;
}
