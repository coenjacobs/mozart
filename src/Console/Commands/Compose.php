<?php

namespace CoenJacobs\Mozart\Console\Commands;

use CoenJacobs\Mozart\Commands\Compose as ComposeCommand;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Exceptions\MozartException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compose extends Command
{
    protected function configure(): void
    {
        $this->setName('compose');
        $this->setDescription('Composes all dependencies as a package inside a WordPress plugin.');
        $this->setHelp('');
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

        $compose = new ComposeCommand($workingDir);

        try {
            $compose->execute();
        } catch (MozartException $e) {
            $output->writeln($e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $output->writeln(get_class($e) . ': ' . $e->getMessage());
            $output->writeln($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
