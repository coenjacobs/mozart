<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

abstract class BaseReplacer implements Replacer
{
    /** @var Autoloader */
    public $autoloader;

    /**
     * @return void
     */
    public function setAutoloader($autoloader)
    {
        $this->autoloader = $autoloader;
    }
}
