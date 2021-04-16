<?php

namespace BrianHenryIE\Strauss\Console;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    /**
     * @param string $version
     */
    public function __construct($version)
    {
        parent::__construct('mozart', $version);

        $composeCommand = new Compose();
        $this->add($composeCommand);
    }
}
