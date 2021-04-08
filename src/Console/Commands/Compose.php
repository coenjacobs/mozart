<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Composer\ComposerPackage;
use CoenJacobs\Mozart\Composer\ProjectComposerPackage;
use CoenJacobs\Mozart\Copier;
use CoenJacobs\Mozart\FileEnumerator;
use CoenJacobs\Mozart\Replacer;
use CoenJacobs\Mozart\Composer\Extra\NannerlConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compose extends Command
{
    /** @var string */
    protected string $workingDir;

    /** @var NannerlConfig */
    protected NannerlConfig $config;

    protected $projectComposerPackage;

    /** @var Copier */
    protected Copier $copier;

    /** @var Replacer */
    protected Replacer $replacer;


    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('compose');
        $this->setDescription("Copy composer's `require` and prefix their namespace and classnames.");
        $this->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workingDir = getcwd() . DIRECTORY_SEPARATOR;
        $this->workingDir = $workingDir;

        try {
            $this->loadProjectComposerPackage();

            $this->buildDependencyList();

            $this->copyFiles();

            $this->updateNamespaces();

            $this->cleanUp();
        } catch (\Exception $e) {
            $output->write($e->getMessage());
            return 1;
        }

        // What should this be?!
        return 1;
    }


    /**
     * 1. Load the composer.json.
     *
     * @throws \Exception
     */
    protected function loadProjectComposerPackage()
    {

        $this->projectComposerPackage = new ProjectComposerPackage($this->workingDir . 'composer.json');

        $config = $this->projectComposerPackage->getNannerlConfig();

        if (!isset($config->exclude_files_from_copy)) {
            $config->exclude_files_from_copy = [];
        }


        $this->config = $config;
    }


    /** @var ComposerPackage[] */
    protected array $flatDependencyTree = [];

    /**
     * 2. Built flat list of packages and dependencies.
     *
     * 2.1 Initiate getting dependencies for the project composer.json.
     *
     * @see Compose::flatDependencyTree
     */
    protected function buildDependencyList()
    {

        foreach ($this->config->getPackages() as $requiredPackageName) {
            $packageDir = $this->workingDir . 'vendor' .DIRECTORY_SEPARATOR
                . $requiredPackageName . DIRECTORY_SEPARATOR;

            $overrideAutoload = isset($this->config->getOverrideAutoload()[$requiredPackageName])
                ? $this->config->getOverrideAutoload()[$requiredPackageName]
                : null;

            $requiredComposerPackage = new ComposerPackage($packageDir, $overrideAutoload);
            $this->getAllDependencies($requiredComposerPackage);
        }
    }

    /**
     * 2.2 Recursive function to get dependencies.
     *
     * @param ComposerPackage $requiredDependency
     */
    protected function getAllDependencies(ComposerPackage $requiredDependency): void
    {
        $excludedPackagesNames = $this->config->getExcludePrefixPackages();

        $required = $requiredDependency->getRequiresNames();

        foreach ($required as $dependencyName) {
            if (in_array($dependencyName, $excludedPackagesNames)) {
                continue;
            }

            $overrideAutoload = isset($this->config->getOverrideAutoload()[$dependencyName])
                ? $this->config->getOverrideAutoload()[$dependencyName]
                : null;

            $dependencyComposerPackage = new ComposerPackage(
                $this->workingDir . 'vendor' . DIRECTORY_SEPARATOR
                . $dependencyName . DIRECTORY_SEPARATOR . 'composer.json',
                $overrideAutoload
            );

            $this->flatDependencyTree[$dependencyName] = $dependencyComposerPackage;
            $this->getAllDependencies($dependencyComposerPackage);
        }
    }

    protected function enumerateFiles()
    {

        $this->fileEnumerator = new FileEnumerator(
            $this->flatDependencyTree,
            $this->workingDir,
            $this->config->getTargetDirectory()
        );

        $this->fileEnumerator->compileFileList();
    }

    protected FileEnumerator $fileEnumerator;

    // 3. Copy autoloaded files for each
    protected function copyFiles()
    {

        $this->copier = new Copier(
            $this->fileEnumerator->getFileList(),
            $this->workingDir,
            $this->config->getTargetDirectory()
        );

        $this->copier->prepareTarget();

        $this->copier->copy();
    }


    // 4. Update individual namespaces and class names.
    // Replace references to updated namespaces and classnames throughout the dependencies.
    protected function updateNamespaces()
    {
        $this->replacer = new Replacer($this->workingDir, $this->config);

        $this->replacePackages($packages);
        $this->replaceParentInTree($packages);
        $this->replacer->replaceParentClassesInDirectory($this->config->getClassmapDirectory());
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
     * @param array $packages
     */
    protected function replaceParentInTree(array $packages): void
    {
        /** @var ComposerPackage $package */
        foreach ($packages as $package) {
            $dependencies = $this->getAllDependenciesOfPackage($package);

            /** @var ComposerPackage $dependency */
            foreach ($dependencies as $dependency) {
                $this->replacer->replaceParentPackage($dependency, $package);
            }

            $this->replaceParentInTree($package->getDependencies());
        }
    }

    /**
     * Delete source files if desired.
     * Delete empty directories in destination.
     */
    protected function cleanUp()
    {
    }


    /**
     * Deletes all the packages that are moved from the /vendor/ directory to
     * prevent packages that are prefixed/namespaced from being used or
     * influencing the output of the code. They just need to be gone.
     *
     * @return void
     */
    protected function deletePackageVendorDirectories(): void
    {
        foreach ($this->movedPackages as $movedPackage) {
            $packageDir = 'vendor' . DIRECTORY_SEPARATOR . $this->clean($movedPackage);
            if (!is_dir($packageDir) || is_link($packageDir)) {
                continue;
            }

            $this->filesystem->deleteDir($packageDir);

            //Delete parent directory too if it became empty
            //(because that package was the only one from that vendor)
            $parentDir = dirname($packageDir);
            if ($this->dirIsEmpty($parentDir)) {
                $this->filesystem->deleteDir($parentDir);
            }
        }
    }

    protected function dirIsEmpty(string $dir): bool
    {
        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        return iterator_count($di) === 0;
    }

    /**
     * For Windows & Unix file paths' compatibility.
     *
     *  * Removes duplicate `\` and `/`.
     *  * Trims them from each end.
     *  * Replaces them with the OS agnostic DIRECTORY_SEPARATOR.
     *
     * @param string $path A full or partial filepath.
     *
     * @return string
     */
    protected function clean($path)
    {
        return trim(preg_replace('/[\/\\\\]+/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
