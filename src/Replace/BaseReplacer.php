<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

abstract class BaseReplacer implements Replacer
{
    /** @var Autoloader */
    public $autoloader;

    public function setAutoloader($autoloader)
    {
        $this->autoloader = $autoloader;
    }
}
