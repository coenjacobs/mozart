<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

class Psr4 implements Autoloader
{
    /** @var string */
    public $namespace = '';

    /** @var array */
    public $paths = [];
}