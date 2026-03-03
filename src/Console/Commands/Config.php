<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Commands\Config as ConfigCommand;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Exceptions\MozartException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Config extends Command
{
    protected function configure(): void
    {
        $this->setName('config');
        $this->setDescription('Displays the resolved Mozart configuration for the current project.');
        $this->setHelp('Loads composer.json, applies defaults, and shows each setting with its source.');
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = getcwd();

        if (! $workingDir) {
            throw new FileOperationException('Unable to determine the working directory.');
        }

        $command = new ConfigCommand($workingDir);

        try {
            $result = $command->execute();
        } catch (MozartException $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        $output->writeln('');
        $output->writeln('Mozart Configuration (resolved from composer.json)');
        $output->writeln('');

        $this->printAllSettings($output, $result['config'], $result['sources']);

        $output->writeln('');

        return 0;
    }

    /**
     * Print all resolved configuration settings to the output.
     *
     * @param array<string, string> $sources
     */
    private function printAllSettings(
        OutputInterface $output,
        Mozart $config,
        array $sources
    ): void {
        $this->printSetting($output, 'dep_namespace', $config->depNamespace, $sources['dep_namespace']);
        $this->printSetting($output, 'dep_directory', $config->depDirectory, $sources['dep_directory']);
        $this->printSetting($output, 'classmap_directory', $config->classmapDir, $sources['classmap_directory']);
        $this->printSetting($output, 'classmap_prefix', $config->classmapPrefix, $sources['classmap_prefix']);
        $this->printOptionalSetting($output, 'constant_prefix', $config->constantPrefix);
        $this->printOptionalSetting($output, 'functions_prefix', $config->functionsPrefix);
        $autoloaderSource = $sources['generate_autoloader'];
        $this->printBoolSetting($output, 'generate_autoloader', $config->generateAutoloader, $autoloaderSource);
        $deleteSource = $sources['delete_vendor_directories'];
        $this->printBoolSetting($output, 'delete_vendor_dirs', $config->deleteVendorDir, $deleteSource);
        $this->printListSetting($output, 'packages', $config->getPackages(), '(all require dependencies)');
        $this->printListSetting($output, 'excluded_packages', $config->getExcludedPackages(), '(none)');
    }

    /**
     * Print a single config setting with alignment and source annotation.
     */
    private function printSetting(
        OutputInterface $output,
        string $name,
        string $value,
        string $source
    ): void {
        $paddedName = str_pad($name . ':', 24);
        $paddedValue = str_pad($value, 32);
        $line = '  ' . $paddedName . $paddedValue;

        if (!empty($source)) {
            $line .= $source;
        }

        $output->writeln(rtrim($line));
    }

    /**
     * Print an optional string setting, showing "(not set)" when empty.
     */
    private function printOptionalSetting(
        OutputInterface $output,
        string $name,
        string $value
    ): void {
        if (empty($value)) {
            $this->printSetting($output, $name, '(not set)', '');
            return;
        }

        $this->printSetting($output, $name, $value, '(explicit)');
    }

    /**
     * Print a boolean setting with its source annotation.
     */
    private function printBoolSetting(
        OutputInterface $output,
        string $name,
        bool $value,
        string $source
    ): void {
        $display = $value ? 'true' : 'false';
        $this->printSetting($output, $name, $display, $source);
    }

    /**
     * Print a list setting, showing a fallback label when the list is empty.
     *
     * @param string[] $values
     */
    private function printListSetting(
        OutputInterface $output,
        string $name,
        array $values,
        string $emptyLabel
    ): void {
        $display = !empty($values) ? implode(', ', $values) : $emptyLabel;
        $this->printSetting($output, $name, $display, '');
    }
}
