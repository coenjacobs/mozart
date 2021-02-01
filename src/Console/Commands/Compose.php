<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Composer\Autoload\Classmap;
use CoenJacobs\Mozart\Composer\Autoload\NamespaceAutoloader;
use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\Replacer;
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

    /** @var */
    private $config;

    /**
     * @return void
     */
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

        $composerFile = $workingDir . DIRECTORY_SEPARATOR. 'composer.json';
        if (!file_exists($composerFile)) {
            $output->write('No composer.json found at current directory: ' . $workingDir);
            return 1;
        }

        $composer = json_decode(file_get_contents($composerFile));
        // If the json was malformed.
        if (!is_object($composer)) {
            $output->write('Unable to parse composer.json read at: ' . $workingDir);
            return 1;
        }

        // if `extra` is missing or not an object or if it does not have a `mozart` key which is an object.
        if (!isset($composer->extra) || !is_object($composer->extra)
            || !isset($composer->extra->mozart) || !is_object($composer->extra->mozart)) {
            $output->write('Mozart config not readable in composer.json at extra->mozart');
            return 1;
        }
        $config = $composer->extra->mozart;

        $config->dep_namespace = preg_replace("/\\\{2,}$/", "\\", "$config->dep_namespace\\");

        $this->config = $config;

        $require = array();
        if (isset($config->packages) && is_array($config->packages)) {
            $require = $config->packages;
        } elseif (isset($composer->require) && is_object($composer->require)) {
            $require = array_keys(get_object_vars($composer->require));
        }

        $packagesByName = $this->findPackages($require);
        $excludedPackagesNames = isset($config->excluded_packages) ? $config->excluded_packages : [];
        $packagesToMoveByName = array_diff_key($packagesByName, array_flip($excludedPackagesNames));
        $packages = array_values($packagesToMoveByName);

        foreach ($packages as $package) {
            $package->dependencies = array_diff_key($package->dependencies, array_flip($excludedPackagesNames));
        }

        $this->mover = new Mover($workingDir, $config);
        $this->replacer = new Replacer($workingDir, $config);

        $this->mover->deleteTargetDirs($packages);
        $this->movePackages($packages);
        $this->replacePackages($packages);
        $this->replaceParentInTree($packages);
        $this->replacer->replaceParentClassesInDirectory($this->config->classmap_directory);

        return 0;
    }

    /**
     * @param $workingDir
     * @param $config
     * @param array $packages
     *
     * @return void
     */
    protected function movePackages($packages): void
    {
        foreach ($packages as $package) {
            $this->movePackage($package);
        }

        $this->mover->deleteEmptyDirs();
    }

    /**
     * @param $workingDir
     * @param $config
     * @param array $packages
     *
     * @return void
     */
    protected function replacePackages($packages): void
    {
        foreach ($packages as $package) {
            $this->replacePackage($package);
        }
    }

    /**
     * Move all the packages over, one by one, starting on the deepest level of dependencies.
     *
     * @return void
     */
    public function movePackage($package): void
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
     *
     * @return void
     */
    public function replacePackage($package): void
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
     *
     * @param ((int|string)|mixed)[] $slugs
     *
     * @return Package[]
     *
     * @psalm-return array<array-key, Package>
     */
    private function findPackages(array $slugs): array
    {
        $packages = [];

        foreach ($slugs as $package_slug) {
            $packageDir = $this->workingDir . DIRECTORY_SEPARATOR . 'vendor'
                          . DIRECTORY_SEPARATOR . $package_slug . DIRECTORY_SEPARATOR;

            if (! is_dir($packageDir)) {
                continue;
            }

            $autoloaders = null;
            if (isset($this->config->override_autoload) && isset($this->config->override_autoload->$package_slug)) {
                $autoloaders = $this->config->override_autoload->$package_slug;
            }

            $package = new Package($packageDir, $autoloaders);
            $package->findAutoloaders();

            $config = json_decode(file_get_contents($packageDir . 'composer.json'));

            $dependencies = [];
            if (isset($config->require)) {
                $dependencies = array_keys((array)$config->require);
            }

            $package->dependencies = $this->findPackages($dependencies);
            $packages[$package_slug] = $package;
        }

        return $packages;
    }

    /**
     * Get an array containing all the dependencies and dependencies
     * @param Package $package
     * @param array   $dependencies
     * @return array
     */
    private function getAllDependenciesOfPackage(Package $package, $dependencies = []): array
    {
        if (empty($package->dependencies)) {
            return $dependencies;
        }

        /** @var Package $dependency */
        foreach ($package->dependencies as $dependency) {
            $dependencies[] = $dependency;
        }

        foreach ($package->dependencies as $dependency) {
            $dependencies = $this->getAllDependenciesOfPackage($dependency, $dependencies);
        }

        return $dependencies;
    }

    /**
     * @param array $packages
     */
    private function replaceParentInTree(array $packages): void
    {
        /** @var Package $package */
        foreach ($packages as $package) {
            $dependencies = $this->getAllDependenciesOfPackage($package);

            /** @var Package $dependency */
            foreach ($dependencies as $dependency) {
                $this->replacer->replaceParentPackage($dependency, $package);
            }

            $this->replaceParentInTree($package->dependencies);
        }
    }
}
