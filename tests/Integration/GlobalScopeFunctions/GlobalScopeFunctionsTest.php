<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\GlobalScopeFunctions;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for global-scope function prefixing.
 *
 * Uses mustangostang/spyc as a real-world package that defines global-scope
 * functions (spyc_load, spyc_load_file, spyc_dump) and a global-scope class
 * (Spyc) via a files autoloader. This is the exact scenario from issue #263.
 *
 * @see https://github.com/coenjacobs/mozart/issues/263
 * @see https://github.com/coenjacobs/mozart/issues/264
 */
class GlobalScopeFunctionsTest extends IntegrationTestCase
{
    private string $spycFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->spycFile = $this->testsWorkingDir . '/classes/mustangostang/spyc/Spyc.php';
    }

    /**
     * Run Mozart once and cache the result for assertions across tests.
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
    public function it_prefixes_class_declaration(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            'class MozartTest_Spyc',
            $content,
            'The Spyc class declaration should be prefixed'
        );
    }

    #[Test]
    public function it_prefixes_function_declarations(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            'function mozarttest_spyc_load(',
            $content,
            'spyc_load function declaration should be prefixed'
        );

        $this->assertStringContainsString(
            'function mozarttest_spyc_load_file(',
            $content,
            'spyc_load_file function declaration should be prefixed'
        );

        $this->assertStringContainsString(
            'function mozarttest_spyc_dump(',
            $content,
            'spyc_dump function declaration should be prefixed'
        );
    }

    #[Test]
    public function it_updates_function_exists_guards(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            "function_exists('mozarttest_spyc_load')",
            $content,
            'function_exists guard for spyc_load should use prefixed name'
        );

        $this->assertStringContainsString(
            "function_exists('mozarttest_spyc_load_file')",
            $content,
            'function_exists guard for spyc_load_file should use prefixed name'
        );

        $this->assertStringContainsString(
            "function_exists('mozarttest_spyc_dump')",
            $content,
            'function_exists guard for spyc_dump should use prefixed name'
        );

        // Original unprefixed guards should not remain
        $this->assertStringNotContainsString(
            "function_exists('spyc_load')",
            $content,
            'Original unprefixed function_exists guard should not remain'
        );
    }

    #[Test]
    public function it_updates_class_exists_guard(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        $this->assertStringContainsString(
            "class_exists('MozartTest_Spyc')",
            $content,
            'class_exists guard should use prefixed class name'
        );

        $this->assertStringNotContainsString(
            "class_exists('Spyc')",
            $content,
            'Original unprefixed class_exists guard should not remain'
        );
    }

    #[Test]
    public function it_updates_function_call_references(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        // Spyc.php calls spyc_load_file() at the end of the file for CLI usage
        $this->assertStringContainsString(
            'mozarttest_spyc_load_file(',
            $content,
            'Call to spyc_load_file() should be updated to prefixed name'
        );
    }

    #[Test]
    public function it_updates_static_method_calls_on_renamed_class(): void
    {
        $this->runMozartOnSpyc();
        $content = file_get_contents($this->spycFile);

        // The global functions call Spyc::YAMLLoad(), Spyc::YAMLLoadString(), Spyc::YAMLDump()
        $this->assertStringContainsString(
            'MozartTest_Spyc::',
            $content,
            'Static method calls on Spyc class should use prefixed name'
        );

        // No unprefixed Spyc:: references should remain (except in comments/strings)
        // Check specifically in function bodies where the static calls live
        $this->assertStringNotContainsString(
            'return Spyc::',
            $content,
            'Original unprefixed static calls should not remain'
        );
    }
}
