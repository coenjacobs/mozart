<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Composer\Autoload\Autoloader;
use stdClass;

class Autoload
{
    /** @var array<Autoloader> */
    public array $autoloaders = [];

    /**
     * Loads the autoloaders provided in the loaded composer.json file, which is
     * then passed to this method as a stdClass. It registers each autoloader,
     * which are then used to access the paths to read and replace contents of
     * files that these autoloaders allow access to.
     */
    public function setupAutoloaders(stdClass $autoloadData, Package $package): void
    {
        $autoloaders = [];

        if (isset($autoloadData->{'psr-4'})) {
            $psr4Autoloaders = (array) $autoloadData->{'psr-4'};
            foreach ($psr4Autoloaders as $key => $value) {
                $autoloader = new Psr4();
                $autoloader->setNamespace($key);
                $autoloader->processConfig($value);
                $autoloader->setPackage($package);
                $autoloaders[] = $autoloader;
            }
        }

        if (isset($autoloadData->{'psr-0'})) {
            $psr0Autoloaders = (array) $autoloadData->{'psr-0'};
            foreach ($psr0Autoloaders as $key => $value) {
                $autoloader = new Psr0();
                $autoloader->setNamespace($key);
                $autoloader->processConfig($value);
                $autoloader->setPackage($package);
                $autoloaders[] = $autoloader;
            }
        }

        if (isset($autoloadData->classmap)) {
            $autoloader = new Classmap();
            $autoloader->processConfig($autoloadData->classmap);
            $autoloader->setPackage($package);
            $autoloaders[] = $autoloader;
        }

        $this->setAutoloaders($autoloaders);
    }

    /**
     * @param array<Autoloader> $autoloaders
     */
    public function setAutoloaders(array $autoloaders): void
    {
        foreach ($autoloaders as $autoloader) {
            if (! $autoloader instanceof Autoloader) {
                continue;
            }

            array_push($this->autoloaders, $autoloader);
        }
    }

    /**
     * @return Autoloader[]
     */
    public function getAutoloaders(): array
    {
        return $this->autoloaders;
    }
}
