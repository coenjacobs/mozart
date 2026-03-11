<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\SharedDependencyPreservation;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class SharedDependencyPreservationTest extends IntegrationTestCase
{
    /**
     * Verifies that Mozart still prefixes a shared dependency while preserving
     * the original vendor copy when an installed non-processed package depends
     * on it as well.
     */
    #[Test]
    public function it_preserves_shared_processed_dependencies_in_vendor(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/pimple/pimple');
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/symfony/service-contracts');
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/psr/container');
        $this->assertFileExists(
            $this->testsWorkingDir . '/src/dependencies/Psr/Container/ContainerInterface.php'
        );

        $containerInterface = file_get_contents(
            $this->testsWorkingDir . '/src/dependencies/Psr/Container/ContainerInterface.php'
        );

        $this->assertNotFalse($containerInterface);
        $this->assertStringContainsString(
            'namespace Mozart\\TestProject\\Dependencies\\Psr\\Container;',
            $containerInterface
        );
    }
}
