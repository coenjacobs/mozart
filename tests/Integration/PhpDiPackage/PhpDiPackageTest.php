<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\PhpDiPackage;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for php-di/php-di package processing.
 *
 * Tests the scenario where psr/container is excluded from Mozart processing,
 * which is a common real-world pattern.
 *
 * @see https://github.com/coenjacobs/mozart/issues/177
 */
class PhpDiPackageTest extends IntegrationTestCase
{
    /**
     * Verifies that Mozart successfully processes the php-di/php-di package
     * and creates the expected dependency directories.
     */
    #[Test]
    public function it_processes_php_di_package_successfully(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // DI and Invoker directories are created
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/DI');
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Invoker');

        // psr/container stays in vendor (excluded)
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/psr/container');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . '/src/dependencies/Psr');
    }

    /**
     * Critical test for Issue #177: Verifies that nullable type hints are handled correctly.
     *
     * The bug was that the regex treated `?` as a word boundary, causing `Invoker`
     * (the class name from a use statement alias) to be incorrectly prefixed directly
     * as `?Mozart\TestProject\Dependencies\Invoker` instead of just `?Invoker`.
     *
     * @see https://github.com/coenjacobs/mozart/issues/177
     */
    #[Test]
    public function it_correctly_handles_nullable_type_hints_in_factory_resolver(): void
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
    }

    /**
     * Verifies that the php-di/invoker dependency is correctly moved and prefixed.
     */
    #[Test]
    public function it_correctly_prefixes_invoker_dependency(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // Invoker package is moved
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Invoker');

        // Check that the use statement for Invoker is correctly prefixed
        $factoryResolverPath = $this->testsWorkingDir . '/src/dependencies/DI/Definition/Resolver/FactoryResolver.php';
        $content = file_get_contents($factoryResolverPath);

        $this->assertStringContainsString(
            'use Mozart\TestProject\Dependencies\Invoker\Invoker;',
            $content,
            'Use statement for Invoker should be prefixed'
        );
    }

    /**
     * Verifies that references to the excluded psr/container package are not prefixed.
     */
    #[Test]
    public function it_keeps_excluded_psr_container_references_unchanged(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // Find a file that uses ContainerInterface
        $containerPath = $this->testsWorkingDir . '/src/dependencies/DI/Container.php';
        $this->assertFileExists($containerPath);

        $content = file_get_contents($containerPath);

        // psr/container references should NOT be prefixed (excluded package)
        $this->assertStringContainsString(
            'use Psr\Container\ContainerInterface;',
            $content,
            'Excluded psr/container references should not be prefixed'
        );

        $this->assertStringNotContainsString(
            'use Mozart\TestProject\Dependencies\Psr\Container\ContainerInterface;',
            $content,
            'psr/container should not be prefixed when excluded'
        );
    }
}
