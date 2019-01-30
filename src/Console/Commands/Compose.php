<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\Replacer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compose extends Command
{
    protected function configure()
    {
        $this->setName('compose');
        $this->setDescription('Composes all dependencies as a package inside a WordPress plugin.');
        $this->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workingDir = getcwd();

        $config = json_decode(file_get_contents($workingDir . '/composer.json'));
        $config = $config->extra->mozart;

        $packages = $this->findPackages($workingDir, $config->packages);

        $this->movePackages($workingDir, $config, $packages);
        $this->replacePackages($workingDir, $config, $packages);
    }

    /**
     * @param $workingDir
     * @param $config
     * @param array $packages
     */
    protected function movePackages($workingDir, $config, $packages)
    {
        $mover = new Mover($workingDir, $config);
        $mover->deleteTargetDirs();

        foreach ($packages as $package) {
            $this->movePackage($package, $mover);
        }
    }

    /**
     * @param $workingDir
     * @param $config
     * @param array $packages
     */
    protected function replacePackages($workingDir, $config, $packages)
    {
        $replacer = new Replacer($workingDir, $config);

        foreach ($packages as $package) {
            $this->replacePackage($package, $replacer);
        }
    }

    /**
     * Move all the packages over, one by one, starting on the deepest level of dependencies.
     */
    public function movePackage($package, $mover)
    {
        if ( ! empty( $package->dependencies ) ) {
            foreach( $package->dependencies as $dependency ) {
                $this->movePackage($dependency, $mover);
            }
        }

        $mover->movePackage($package);
    }

    /**
     * Replace contents of all the packages, one by one, starting on the deepest level of dependencies.
     */
    public function replacePackage($package, $replacer)
    {
        if ( ! empty( $package->dependencies ) ) {
            foreach( $package->dependencies as $dependency ) {
                $this->replacePackage($dependency, $replacer);
            }
        }

        $replacer->replacePackage($package);
    }

    /**
     * Loops through all dependencies and their dependencies and so on...
     * will eventually return a list of all packages required by the full tree.
     */
    private function findPackages($workingDir, $slugs)
    {
        $packages = [];

        foreach ($slugs as $package_slug) {
            $packageDir = $workingDir . '/vendor/' . $package_slug .'/';

            if (! is_dir($packageDir) ) {
                continue;
            }

            $package = new Package($packageDir);
            $package->findAutoloaders();

            $config = json_decode(file_get_contents($packageDir . 'composer.json'));
            $dependencies = array_keys( (array) $config->require);

            $package->dependencies = $this->findPackages($workingDir, $dependencies);
            $packages[] = $package;
        }

        return $packages;
    }
}
