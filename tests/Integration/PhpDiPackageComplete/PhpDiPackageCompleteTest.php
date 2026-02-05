<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\PhpDiPackageComplete;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for php-di/php-di package processing without exclusions.
 *
 * Tests the scenario where all dependencies including psr/container are processed,
 * providing complete namespace isolation.
 *
 * @see https://github.com/coenjacobs/mozart/issues/177
 */
class PhpDiPackageCompleteTest extends IntegrationTestCase
{
    /**
     * Verifies that all dependencies including psr/container are processed
     * when no packages are excluded.
     */
    #[Test]
    public function it_processes_all_dependencies_completely(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // ALL packages moved to dependencies including psr/container
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/DI');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Invoker');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Psr');

        // All vendor directories removed (delete_vendor_directories is true)
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/php-di');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/vendor/psr');
    }

    /**
     * Verifies that psr/container references are prefixed when not excluded.
     */
    #[Test]
    public function it_prefixes_psr_container_references(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // Find a file that uses ContainerInterface
        $containerPath = $this->testsWorkingDir . '/src/dependencies/DI/Container.php';
        $this->assertFileExists($containerPath);

        $content = file_get_contents($containerPath);

        // psr/container references SHOULD be prefixed (not excluded)
        $this->assertStringContainsString(
            'use Mozart\TestProject\Dependencies\Psr\Container\ContainerInterface;',
            $content,
            'psr/container references should be prefixed when not excluded'
        );

        $this->assertStringNotContainsString(
            'use Psr\Container\ContainerInterface;',
            $content,
            'Original psr/container namespace should not remain when not excluded'
        );
    }

    /**
     * Critical test for Issue #177: Verifies nullable type hints work correctly
     * even when all packages are processed without exclusions.
     *
     * @see https://github.com/coenjacobs/mozart/issues/177
     */
    #[Test]
    public function it_correctly_handles_nullable_type_hints_without_exclusions(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $factoryResolverPath = $this->testsWorkingDir . '/src/dependencies/DI/Definition/Resolver/FactoryResolver.php';
        $this->assertFileExists($factoryResolverPath);

        $content = file_get_contents($factoryResolverPath);

        // Namespace is correctly prefixed
        $this->assertStringContainsString(
            'namespace Mozart\TestProject\Dependencies\DI\Definition\Resolver;',
            $content
        );

        // CRITICAL: The nullable type hint should NOT have an incorrect full prefix
        // The bug was: ?Mozart\TestProject\Dependencies\Invoker (wrong - directly prefixed)
        // Should be: ?Invoker (using the alias from use statement)
        $this->assertStringNotContainsString(
            '?Mozart\TestProject\Dependencies\Invoker',
            $content,
            'Nullable type hint should not be directly prefixed with full namespace'
        );

        // The property declaration should use the short class name from use statement
        $this->assertStringContainsString(
            'private ?Invoker $invoker',
            $content,
            'Nullable property type should use the short class name from use statement'
        );

        // Use statement for Invoker should be correctly prefixed
        $this->assertStringContainsString(
            'use Mozart\TestProject\Dependencies\Invoker\Invoker;',
            $content,
            'Use statement for Invoker should be prefixed'
        );
    }
}
