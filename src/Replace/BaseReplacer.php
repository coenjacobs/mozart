<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

abstract class BaseReplacer implements Replacer
{
    /** @var Autoloader */
    public $autoloader;
    public $namespacesToSkip;

    public function setAutoloader($autoloader)
    {
        $this->autoloader = $autoloader;
    }
    public function setNamespacesToSkip(array $namespacesToSkip)
    {
        $this->namespacesToSkip = $namespacesToSkip;
    }
}
