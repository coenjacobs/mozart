<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Composer\ComposerPackageConfig;
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
        $workingDir = getcwd() . DIRECTORY_SEPARATOR;
        $this->workingDir = $workingDir;

        try {
            $this->config = MozartConfig::loadFromFile($workingDir . 'composer.json');
        } catch (\Exception $e) {
            $output->write($e->getMessage());
            return 1;
        }

        $this->mover = new Mover($workingDir, $this->config);
        $this->replacer = new Replacer($workingDir, $this->config);


        $require = $this->config->getPackages();

        $packagesByName = $this->findPackages($require);
        $excludedPackagesNames = $this->config->getExcludedPackages();
        $packagesToMoveByName = array_diff_key($packagesByName, array_flip($excludedPackagesNames));
        $packages = array_values($packagesToMoveByName);

        foreach ($packages as $package) {
            $package->dependencies = array_diff_key($package->dependencies, array_flip($excludedPackagesNames));
        }

        $this->mover->deleteTargetDirs($packages);
        $this->movePackages($packages);
        $this->replacePackages($packages);
        $this->replaceParentInTree($packages);
        $this->replacer->replaceParentClassesInDirectory($this->config->getClassmapDirectory());

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
     * @return ComposerPackageConfig[]
     *
     * @psalm-return array<array-key, ComposerPackageConfig>
     */
    private function findPackages(array $slugs): array
    {
        $packages = [];

        foreach ($slugs as $package_slug) {
            $packageDir = $this->workingDir . 'vendor'
                          . DIRECTORY_SEPARATOR . $package_slug . DIRECTORY_SEPARATOR;

            if (! is_dir($packageDir)) {
                continue;
            }

            $autoloaders = null;
            $override_autoload = $this->config->getOverrideAutoload();
            if (! empty($override_autoload) && isset($override_autoload->$package_slug)) {
                $autoloaders = $override_autoload->$package_slug;
            }

            $config = json_decode(file_get_contents($packageDir . 'composer.json'));

            $package = new ComposerPackageConfig($packageDir, $autoloaders);
            $package->findAutoloaders();

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
     *
     * @param ComposerPackageConfig $package
     * @param array   $dependencies
     *
     * @return array
     */
    private function getAllDependenciesOfPackage(ComposerPackageConfig $package, $dependencies = []): array
    {
        if (empty($package->dependencies)) {
            return $dependencies;
        }

        /** @var ComposerPackageConfig $dependency */
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
        /** @var ComposerPackageConfig $package */
        foreach ($packages as $package) {
            $dependencies = $this->getAllDependenciesOfPackage($package);

            /** @var ComposerPackageConfig $dependency */
            foreach ($dependencies as $dependency) {
                $this->replacer->replaceParentPackage($dependency, $package);
            }

            $this->replaceParentInTree($package->dependencies);
        }
    }
}
