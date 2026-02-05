<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\ExcludedPackages;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class ExcludedPackagesTest extends IntegrationTestCase
{
    /**
     * Verifies that the explicitly excluded packages from the Mozart config
     * are _not_ being moved to the provided dependency directory and the files
     * will stay present in the vendor directory. At the same time, the other
     * package is being moved to the dependency directory and after that the
     * originating directory in the vendor directory is deleted (as the
     * `delete_vendor_directories` parameter is set to `true`).
     */
    #[Test]
    public function it_excludes_moving_specified_packages(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/psr/container');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/src/dependencies/Psr');
    }

    /**
     * Verifies that the excluded package `psr/container` is _not_ having its
     * classes replaced in the implementing `pimple/pimple` package when the
     * former is explicitly excluded and the latter is added to the list of
     * packages for Mozart to rewrite.
     */
    #[Test]
    public function it_excludes_replacing_classes_from_specified_packages(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $testFile = file_get_contents($this->testsWorkingDir . '/src/dependencies/Pimple/Psr11/Container.php');
        $this->assertStringContainsString('namespace Mozart\TestProject\Dependencies\Pimple\Psr11;', $testFile);
        $this->assertStringContainsString('use Mozart\TestProject\Dependencies\Pimple\Container as PimpleContainer;', $testFile);
        $this->assertStringContainsString('use Psr\Container\ContainerInterface;', $testFile);
    }
}
