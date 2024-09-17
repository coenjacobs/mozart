<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\AbstractAutoloader;
use Exception;

class Classmap extends AbstractAutoloader
{
    /** @var string[] */
    public $files = [];

    /** @var string[] */
    public $paths = [];

    /**
     * @inheritdoc
     */
    public function processConfig($autoloadConfig): void
    {
        foreach ($autoloadConfig as $value) {
            if ('.php' == substr($value, -4, 4)) {
                array_push($this->files, $value);
                continue;
            }

            array_push($this->paths, $value);
        }
    }

    /**
     * @throws Exception
     */
    public function getSearchNamespace(): string
    {
        throw new Exception('Classmap autoloaders do not contain a namespace and this method can not be used.');
    }
}
