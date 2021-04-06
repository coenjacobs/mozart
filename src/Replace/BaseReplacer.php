<?php

namespace CoenJacobs\Mozart\Replace;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;

abstract class BaseReplacer implements Replacer
{

    /** @var Autoloader */
    protected Autoloader $autoloader;

    /**
     * @return Autoloader
     */
    public function getAutoloader(): Autoloader
    {
        return $this->autoloader;
    }
    /**
     * @param Autoloader $autoloader
     * @return void
     */
    public function setAutoloader(Autoloader $autoloader)
    {
        $this->autoloader = $autoloader;
    }
}
