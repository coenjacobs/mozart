<?php

namespace CoenJacobs\Mozart\Console;

use CoenJacobs\Mozart\Console\Commands\Compose;
use CoenJacobs\Mozart\Console\Commands\Config;
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
        $this->addCommand($composeCommand);

        $configCommand = new Config();
        $this->addCommand($configCommand);
    }
}
