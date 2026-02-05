<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\PackageFilteringWithExcluded;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class PackageFilteringWithExcludedTest extends IntegrationTestCase
{
    /**
     * Verifies that excluded_packages still works correctly when packages config
     * is specified. Excluded packages should not be processed even if they're
     * dependencies of included packages.
     */
    #[Test]
    public function it_respects_excluded_packages_when_filtering(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // pimple/pimple is in packages config, so it should be moved
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        // psr/container is in excluded_packages, so it should remain in vendor
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/psr/container');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/src/dependencies/Psr');

        // Verify that excluded package classes are not replaced in included packages
        $testFile = file_get_contents($this->testsWorkingDir . '/src/dependencies/Pimple/Psr11/Container.php');
        $this->assertStringContainsString('namespace Mozart\TestProject\Dependencies\Pimple\Psr11;', $testFile);
        $this->assertStringContainsString('use Mozart\TestProject\Dependencies\Pimple\Container as PimpleContainer;', $testFile);
        $this->assertStringContainsString('use Psr\Container\ContainerInterface;', $testFile);
    }
}
