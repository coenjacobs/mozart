<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\Synchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Compose extends Command
{
    protected function configure()
    {
        $this->setName('compose');
        $this->addOption(
            'skip_dependencies',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip processing of package sub-dependencies.',
            false
        );
        $this->setDescription('Composes all dependencies as a package inside a WordPress plugin.');
        $this->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $skip_dependencies = $input->getOption('skip_dependencies') !== false;
        $workingDir = getcwd();

        $config = json_decode(file_get_contents($workingDir . '/composer.json'));
        $config = $config->extra->mozart;

        $mover = new Mover($workingDir, $config);
        $mover->deleteTargetDirs();

        $packages = new \ArrayIterator($config->packages);
        $processed_packages = [];

        foreach ($packages as $package_slug) {
            $package = new Package($workingDir . '/vendor/' . $package_slug);
            $package->findAutoloaders();
            $mover->movePackage($package);
            if ($skip_dependencies) {
                continue;
            }
            $package->findDependencies($workingDir . '/vendor');
            $processed_packages[] = $package;
            foreach ($package->dependencies as $dependency) {
                if (!in_array($dependency, $packages->getArrayCopy())) {
                    $packages->append($dependency);
                };
            }
        }

        $mover->replaceClassmapNames();

        if ($skip_dependencies) {
            return;
        }

        $synchronizer = new Synchronizer($workingDir, $config);

        foreach ($processed_packages as $package) {
            if (!$package->dependencies) {
                continue;
            }
            foreach ($package->dependencies as $dependency_name) {
                $matching_packages = array_filter(
                    $processed_packages,
                    function (Package $pack) use ($dependency_name) {
                        return $pack->config->name === $dependency_name;
                    }
                );
                $synchronizer->syncMovedPackageWithDependency($package, reset($matching_packages));
            }
        }
    }
}
