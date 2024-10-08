<?php

namespace CoenJacobs\Mozart\Composer\Autoload;

interface Autoloader
{
    /**
     * @param mixed $autoloadConfig
     */
    public function processConfig($autoloadConfig): void;
    public function getSearchNamespace(): string;
}
