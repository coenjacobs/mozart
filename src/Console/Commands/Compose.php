<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\PackageFactory;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\Replacer;
use Exception;
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

    /** @var Mozart */
    private $config;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('compose');
        $this->setDescription('Composes all dependencies as a package inside a WordPress plugin.');
        $this->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = getcwd();

        if (! $workingDir) {
            throw new Exception('Could not determine working directory.');
        }

        $this->workingDir = $workingDir;

        $composerFile = $workingDir . DIRECTORY_SEPARATOR. 'composer.json';
        try {
            $package = PackageFactory::createPackage($composerFile);
        } catch (Exception $e) {
            $output->write('Unable to read the composer.json file');
            return 1;
        }

        if (! $package->isValidMozartConfig() || empty($package->getExtra())) {
            $output->write('Mozart config not readable in composer.json at extra->mozart');
            return 1;
        }

        $config = $package->getExtra()->getMozart();

        if (empty($config)) {
            $output->write('Mozart config not readable in composer.json at extra->mozart');
            return 1;
        }

        $this->config = $config;
        $this->config->set('dep_namespace', preg_replace(
            "/\\\{2,}$/",
            "\\",
            $this->config->getDependencyNamespace()."\\"
        ));

        $require = array();
        if (is_array($this->config->get('packages'))) {
            $require = $this->config->get('packages');
        } else {
            $require = $package->require;
        }

        $packagesByName = $this->findPackages($require);
        $excludedPackagesNames = $this->config->getExcludedPackages();
        $packagesToMoveByName = array_diff_key($packagesByName, array_flip($excludedPackagesNames));
        $packages = array_values($packagesToMoveByName);

        $this->mover = new Mover($workingDir, $this->config);
        $this->replacer = new Replacer($workingDir, $this->config);

        $require = $this->config->get('packages');
        $require = (is_array($require)) ? array_values($require) : array();

        $packages = $this->findPackages($require);

        $this->mover->deleteTargetDirs($packages);
        $this->movePackages($packages);
        $this->replacePackages($packages);
        $this->replaceParentInTree($packages);
        $this->replacer->replaceParentClassesInDirectory($this->config->getClassmapDirectory());

        return 0;
    }

    /**
     * @param Package[] $packages
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
     * @param Package[] $packages
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
    public function movePackage(Package $package): void
    {
        if (! empty($package->dependencies)) {
            foreach ($package->getDependencies() as $dependency) {
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
    public function replacePackage(Package $package): void
    {
        if (! empty($package->dependencies)) {
            foreach ($package->getDependencies() as $dependency) {
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
            $override_autoload = $this->config->getOverrideAutoload();
            if ($override_autoload !== false && isset($override_autoload->$package_slug)) {
                $autoloaders = $override_autoload->$package_slug;
            }

            $package = PackageFactory::createPackage($packageDir . 'composer.json', $autoloaders);

            if ($this->config->isExcludedPackage($package)) {
                continue;
            }

            $dependencies = $package->getDependencies();

            $package->registerRequirePackages($this->findPackages($dependencies));
            $packages[$package_slug] = $package;
        }

        return $packages;
    }

    /**
     * Get an array containing all the dependencies and dependencies
     * @param Package   $package
     * @param Package[] $dependencies
     * @return array
     */
    private function getAllDependenciesOfPackage(Package $package, $dependencies = []): array
    {
        if ($this->config->isExcludedPackage($package)) {
            return $dependencies;
            ;
        }

        if (empty($package->getDependencies())) {
            return $dependencies;
        }

        foreach ($package->getDependencies() as $dependency) {
            $dependencies[] = $dependency;
        }

        foreach ($package->getDependencies() as $dependency) {
            $dependencies = $this->getAllDependenciesOfPackage($dependency, $dependencies);
        }

        return $dependencies;
    }

    /**
     * @param Package[] $packages
     */
    private function replaceParentInTree(array $packages): void
    {
        foreach ($packages as $package) {
            $dependencies = $this->getAllDependenciesOfPackage($package);

            foreach ($dependencies as $dependency) {
                $this->replacer->replaceParentPackage($dependency, $package);
            }

            $this->replaceParentInTree($package->getDependencies());
        }
    }
}
