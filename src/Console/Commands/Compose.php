<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\ChangeEnumerator;
use CoenJacobs\Mozart\Classmap;
use CoenJacobs\Mozart\Cleanup;
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
     * @var ChangeEnumerator
     */
    protected ChangeEnumerator $changeEnumerator;


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

            $this->enumerateFiles();

            $this->copyFiles();

            $this->determineChanges();

            $this->updateNamespaces();

            $this->generateClassmap();

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

        $requiredPackageNames = $this->config->getPackages();

        // Unset PHP, ext-*.
        $removePhpExt = function ($element) {
            return !( 0 === strpos($element, 'ext') || 'php' === $element );
        };

        $requiredPackageNames = array_filter($requiredPackageNames, $removePhpExt);

        foreach ($requiredPackageNames as $requiredPackageName) {
            $packageDir = $this->workingDir . 'vendor' .DIRECTORY_SEPARATOR
                . $requiredPackageName . DIRECTORY_SEPARATOR;

            $overrideAutoload = isset($this->config->getOverrideAutoload()[$requiredPackageName])
                ? $this->config->getOverrideAutoload()[$requiredPackageName]
                : null;

            $requiredComposerPackage = new ComposerPackage($packageDir, $overrideAutoload);
            $this->flatDependencyTree[$requiredComposerPackage->getName()] = $requiredComposerPackage;
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

        // Unset PHP, ext-*.
        $removePhpExt = function ($element) {
            return !( 0 === strpos($element, 'ext') || 'php' === $element );
        };

        $required = array_filter($requiredDependency->getRequiresNames(), $removePhpExt);

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

    protected FileEnumerator $fileEnumerator;

    protected function enumerateFiles()
    {

        $this->fileEnumerator = new FileEnumerator(
            $this->flatDependencyTree,
            $this->workingDir,
            $this->config->getTargetDirectory()
        );

        $this->fileEnumerator->compileFileList();
    }

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

    // 4. Determine namespace and classname changes
    protected function determineChanges()
    {

        $this->changeEnumerator = new ChangeEnumerator();

        $relativeTargetDir = $this->config->getTargetDirectory();
        $phpFiles = $this->fileEnumerator->getPhpFileList();
        $this->changeEnumerator->findInFiles($relativeTargetDir, $phpFiles);
    }

    // 5. Update namespaces and class names.
    // Replace references to updated namespaces and classnames throughout the dependencies.
    protected function updateNamespaces()
    {
        $this->replacer = new Replacer($this->config, $this->workingDir);

        $namespaces = $this->changeEnumerator->getDiscoveredNamespaces();
        $classes = $this->changeEnumerator->getDiscoveredClasses();
        
        $phpFiles = $this->fileEnumerator->getPhpFileList();

        $this->replacer->replaceInFiles($namespaces, $classes, $phpFiles);
    }

    /**
     * 6. Generate classmap.
     */
    protected function generateClassmap()
    {

        $classmap = new Classmap($this->config, $this->workingDir);

        $classmap->generate();
    }


    /**
     * 7.
     * Delete source files if desired.
     * Delete empty directories in destination.
     */
    protected function cleanUp()
    {

        $cleanup = new Cleanup($this->config, $this->workingDir);

        $sourceFiles = array_map(function ($element) {
            return 'vendor' . DIRECTORY_SEPARATOR . $element;
        }, $this->fileEnumerator->getFileList());

        // This will check the config to check should it delete or not.
        $cleanup->cleanup($sourceFiles);
    }
}
