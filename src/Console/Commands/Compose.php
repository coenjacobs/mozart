<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Mover;
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

        $mover = new Mover($workingDir, $config);
        $mover->deleteTargetDirs();

        foreach ($config->packages as $package_slug) {
            $package = new Package($workingDir . '/vendor/' . $package_slug);
            $package->findAutoloaders();
            $mover->movePackage($package);
        }

        $mover->replaceClassmapNames();
    }
}
