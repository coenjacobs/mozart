<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\Replacer;
use CoenJacobs\Mozart\Composer\MozartConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compose extends Command
{
    /** @var Mover */
    private $mover;

    /** @var Replacer */
    private $replacer;

    /** @var string */
    private $workingDir;

    /** @var MozartConfig */
    private $config;

    protected function configure()
    {
        $this->setName('compose');
        $this->setDescription('Composes all dependencies as a package inside a WordPress plugin.');
        $this->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workingDir = getcwd();
        $this->workingDir = $workingDir;

        $this->config = MozartConfig::loadFromFile($workingDir . '/composer.json');

        $this->mover = new Mover($workingDir, $this->config);
        $this->replacer = new Replacer($workingDir, $this->config);

        $require = $this->config->getPackages();

        $packages = $this->findPackages($require);

        $this->movePackages($packages);
        $this->replacePackages($packages);

        foreach ($packages as $package) {
            $this->replacer->replaceParentPackage($package, null);
        }

        return 0;
    }

    /**
     * @param $workingDir
     * @param $config
     * @param array $packages
     */
    protected function movePackages($packages)
    {
        $this->mover->deleteTargetDirs();

        foreach ($packages as $package) {
            $this->movePackage($package);
        }
    }

    /**
     * @param $workingDir
     * @param $config
     * @param array $packages
     */
    protected function replacePackages($packages)
    {
        foreach ($packages as $package) {
            $this->replacePackage($package);
        }
    }

    /**
     * Move all the packages over, one by one, starting on the deepest level of dependencies.
     */
    public function movePackage($package)
    {
        if (! empty($package->dependencies)) {
            foreach ($package->dependencies as $dependency) {
                $this->movePackage($dependency);
            }
        }

        $this->mover->movePackage($package);
    }

    /**
     * Replace contents of all the packages, one by one, starting on the deepest level of dependencies.
     */
    public function replacePackage($package)
    {
        if (! empty($package->dependencies)) {
            foreach ($package->dependencies as $dependency) {
                $this->replacePackage($dependency);
            }
        }

        $this->replacer->replacePackage($package);
    }

    /**
     * Loops through all dependencies and their dependencies and so on...
     * will eventually return a list of all packages required by the full tree.
     */
    private function findPackages($slugs)
    {
        $packages = [];

        foreach ($slugs as $package_slug) {
            $packageDir = $this->workingDir . '/vendor/' . $package_slug .'/';

            if (! is_dir($packageDir)) {
                continue;
            }

            $autoloaders = null;
            $override_autoload = $this->config->getOverrideAutoload();
            if (! empty($override_autoload) && isset($override_autoload->$package_slug)) {
                $autoloaders = $override_autoload->$package_slug;
            }

            $config = MozartConfig::loadFromFile($packageDir . 'composer.json');

            $package = new Package($packageDir, $config, $autoloaders);
            $package->findAutoloaders();

            $require = $config->getPackages();

            $package->dependencies = $this->findPackages($require);
            $packages[] = $package;
        }

        return $packages;
    }
}
