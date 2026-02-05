<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\PackageFiltering;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class PackageFilteringTest extends IntegrationTestCase
{
    /**
     * Verifies that only packages specified in the `packages` config are processed,
     * and packages NOT in the config remain in the vendor directory.
     */
    #[Test]
    public function it_only_processes_packages_specified_in_config(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // pimple/pimple is in packages config, so it should be moved
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        // monolog/monolog is NOT in packages config, so it should remain in vendor
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/monolog/monolog');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/src/dependencies/Monolog');
    }
}
