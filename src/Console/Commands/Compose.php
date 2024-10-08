<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\PackageFinder;
use CoenJacobs\Mozart\Replacer;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compose extends Command
{
    private Mover $mover;
    private Replacer $replacer;
    private Mozart $config;
    private PackageFinder $finder;
    private string $workingDir;

    public function __construct()
    {
        $this->workingDir = getcwd();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('compose');
        $this->setDescription('Composes all dependencies as a package inside a WordPress plugin.');
        $this->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->workingDir) {
            throw new Exception('Could not determine working directory.');
        }

        $composerFile = $this->workingDir . DIRECTORY_SEPARATOR. 'composer.json';
        try {
            $package = PackageFactory::createPackage($composerFile, null, false);
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
        $this->config->setWorkingDir($this->workingDir);

        $require = $this->config->getPackages();
        if (empty($require)) {
            $require = $package->getRequire();
        }

        $this->finder = PackageFinder::instance();
        $this->finder->setConfig($this->config);

        $package->loadDependencies();

        $packages = $this->finder->getPackagesBySlugs($require);
        $packages = $this->finder->findPackages($packages);

        $this->mover = new Mover($this->workingDir, $this->config);
        $this->replacer = new Replacer($this->workingDir, $this->config);

        $this->mover->deleteTargetDirs($packages);
        $this->mover->movePackages($packages);
        $this->replacer->replacePackages($packages);
        $this->replacer->replaceParentInTree($packages);
        $this->replacer->replaceParentClassesInDirectory($this->config->getClassmapDirectory());

        return 0;
    }
}
