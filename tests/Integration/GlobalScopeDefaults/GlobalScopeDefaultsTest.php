<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\GlobalScopeDefaults;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for zero-config global-scope function and constant
 * prefixing via sensible defaults.
 *
 * Uses mustangostang/spyc as a real-world package that defines global-scope
 * functions and a class via a files autoloader. Unlike the GlobalScopeFunctions
 * test, this fixture does NOT set functions_prefix or constant_prefix
 * explicitly — they are derived from classmap_prefix by ConfigDefaultsResolver.
 *
 * The PSR-4 namespace TestProject\ produces:
 *   classmap_prefix  = TestProject_
 *   constant_prefix  = TESTPROJECT_
 *   functions_prefix = testproject_
 *
 * @see https://github.com/coenjacobs/mozart/issues/328
 */
class GlobalScopeDefaultsTest extends IntegrationTestCase
{
    private string $spycFile;

    /**
     * Set up the expected path for the Spyc file.
     */
    public function setUp(): void
    {
        parent::setUp();
        // classmap_directory defaults to dep_directory (vendor-prefixed/)
        $this->spycFile = $this->testsWorkingDir
            . '/vendor-prefixed/mustangostang/spyc/Spyc.php';
    }

    /**
     * Run Mozart once for assertions.
     */
    private function runMozartOnSpyc(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result, 'Mozart should exit with code 0');
    }

    #[Test]
    public function it_moves_spyc_to_classmap_directory(): void
    {
        $this->runMozartOnSpyc();

        $this->assertFileExists($this->spycFile);
    }

    #[Test]
    public function it_prefixes_class_declaration_with_derived_prefix(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            'class TestProject_Spyc',
            $content,
            'The Spyc class declaration should use the derived classmap_prefix'
        );
    }

    #[Test]
    public function it_prefixes_function_declarations_with_derived_lowercase_prefix(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            'function testproject_spyc_load(',
            $content,
            'spyc_load function declaration should use the derived lowercase functions_prefix'
        );

        $this->assertStringContainsString(
            'function testproject_spyc_load_file(',
            $content,
            'spyc_load_file function declaration should use the derived lowercase functions_prefix'
        );

        $this->assertStringContainsString(
            'function testproject_spyc_dump(',
            $content,
            'spyc_dump function declaration should use the derived lowercase functions_prefix'
        );
    }

    #[Test]
    public function it_updates_function_exists_guards_with_derived_prefix(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            "function_exists('testproject_spyc_load')",
            $content,
            'function_exists guard for spyc_load should use the derived functions_prefix'
        );

        // Original unprefixed guards should not remain
        $this->assertStringNotContainsString(
            "function_exists('spyc_load')",
            $content,
            'Original unprefixed function_exists guard should not remain'
        );
    }

    #[Test]
    public function it_updates_function_call_references_with_derived_prefix(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            'testproject_spyc_load_file(',
            $content,
            'Call to spyc_load_file() should be updated to the derived prefixed name'
        );
    }

    #[Test]
    public function it_updates_static_method_calls_on_derived_prefixed_class(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            'TestProject_Spyc::',
            $content,
            'Static method calls on Spyc class should use the derived classmap_prefix'
        );

        $this->assertStringNotContainsString(
            'return Spyc::',
            $content,
            'Original unprefixed static calls should not remain'
        );
    }
}
