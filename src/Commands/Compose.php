<?php

namespace CoenJacobs\Mozart\Commands;

use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\PackageFinder;
use CoenJacobs\Mozart\Replace\ParentReplacer;
use CoenJacobs\Mozart\Replace\Replacer;

class Compose
{
    private string $workingDir;

    public function __construct(string $workingDir)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * Main logic of the moving and replacing of the files, that form the basic
     * functionality of Mozart. Finds and loads the main package, from which
     * the Mozart configuration is read. Then the dependencies that need to be
     * transformed by Mozart are detected and sent to the respective handlers.
     */
    public function execute(): void
    {
        $composerFile = $this->workingDir . DIRECTORY_SEPARATOR . 'composer.json';

        $factory = new PackageFactory();
        $package = $factory->createPackage($composerFile);

        if (! $package->isValidMozartConfig() || empty($package->getExtra())) {
            throw new ConfigurationException('Mozart config not readable in composer.json at extra->mozart');
        }

        $config = $package->getExtra()->getMozart();

        if (empty($config)) {
            throw new ConfigurationException('Mozart config not readable in composer.json at extra->mozart');
        }

        $config->setWorkingDir($this->workingDir);

        $finder = new PackageFinder();
        $finder->setConfig($config);

        // Determine which packages to process based on config
        $packagesToProcess = $config->getPackages();
        if (!empty($packagesToProcess)) {
            // Only process the specified packages (and their dependencies)
            $packages = $finder->getPackagesBySlugs($packagesToProcess);
            $packages = $finder->findPackages($packages);
        }

        // If no packages specified, use all dependencies from root package
        if (empty($packagesToProcess)) {
            $package->loadDependencies($finder);
            $packages = $finder->findPackages($package->getDependencies());
        }

        $mover = new Mover($config);
        $replacer = new Replacer($config);

        $mover->deleteTargetDirs($packages);
        $mover->movePackages($packages);
        $replacer->replacePackages($packages);

        $parentReplacer = new ParentReplacer($config, $replacer);
        $parentReplacer->setReplacedClasses($replacer->getReplacedClasses());
        $parentReplacer->replaceParentInTree($packages);
        $parentReplacer->replaceParentClassesInDirectory($config->getClassmapDirectory());

        if ($config->getDeleteVendorDirectories()) {
            $mover->deletePackageVendorDirectories();
        }
    }
}
