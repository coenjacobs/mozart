<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\PackageFilteringEmpty;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class PackageFilteringEmptyTest extends IntegrationTestCase
{
    /**
     * Verifies that when `packages` config is empty, all packages from require
     * are processed (backward compatibility behavior).
     */
    #[Test]
    public function it_processes_all_packages_when_config_empty(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // When packages is empty, all packages should be processed
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        // monolog/monolog should also be processed when packages is empty
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/monolog/monolog');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Monolog');
    }
}
