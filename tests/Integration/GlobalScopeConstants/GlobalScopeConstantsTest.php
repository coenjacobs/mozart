<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\GlobalScopeConstants;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for global-scope constant prefixing.
 *
 * Uses kint-php/kint as a real-world package that defines global-scope
 * constants (KINT_DIR, KINT_WIN, KINT_PHP80, etc.) via define() calls
 * in a files-autoloaded init.php. The file also uses a defined() guard
 * at the top, exercising pass-2 reference updating.
 *
 * The override_autoload strips Kint's PSR-4 entry so only init.php is
 * processed through the files autoloader path (global-scope code).
 *
 * @see https://github.com/coenjacobs/mozart/issues/342
 */
class GlobalScopeConstantsTest extends IntegrationTestCase
{
    private string $initFile;

    /**
     * Set up the expected path for the init file.
     */
    public function setUp(): void
    {
        parent::setUp();
        // Global-scope files go to classmap_directory/vendor/package/filename
        $this->initFile = $this->testsWorkingDir . '/classes/kint-php/kint/init.php';
    }

    /**
     * Run Mozart once for assertions.
     */
    private function runMozartOnKint(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result, 'Mozart should exit with code 0');
    }

    #[Test]
    public function it_moves_init_file_to_classmap_directory(): void
    {
        $this->runMozartOnKint();

        $this->assertFileExists($this->initFile);
    }

    #[Test]
    public function it_prefixes_define_call_constants(): void
    {
        $this->runMozartOnKint();
        $content = file_get_contents($this->initFile);

        $this->assertStringContainsString(
            "define('MOZARTTEST_KINT_DIR'",
            $content,
            'KINT_DIR define() call should be prefixed'
        );

        $this->assertStringContainsString(
            "define('MOZARTTEST_KINT_WIN'",
            $content,
            'KINT_WIN define() call should be prefixed'
        );

        $this->assertStringContainsString(
            "define('MOZARTTEST_KINT_PHP80'",
            $content,
            'KINT_PHP80 define() call should be prefixed'
        );
    }

    #[Test]
    public function it_removes_original_unprefixed_define_calls(): void
    {
        $this->runMozartOnKint();
        $content = file_get_contents($this->initFile);

        $this->assertStringNotContainsString(
            "define('KINT_DIR'",
            $content,
            'Original unprefixed KINT_DIR define() should not remain'
        );

        $this->assertStringNotContainsString(
            "define('KINT_WIN'",
            $content,
            'Original unprefixed KINT_WIN define() should not remain'
        );

        $this->assertStringNotContainsString(
            "define('KINT_PHP80'",
            $content,
            'Original unprefixed KINT_PHP80 define() should not remain'
        );
    }

    #[Test]
    public function it_updates_defined_guard_to_prefixed_name(): void
    {
        $this->runMozartOnKint();
        $content = file_get_contents($this->initFile);

        // The defined('KINT_DIR') guard at the top of init.php should be updated
        $this->assertStringContainsString(
            "defined('MOZARTTEST_KINT_DIR')",
            $content,
            'defined() guard for KINT_DIR should use prefixed name'
        );

        $this->assertStringNotContainsString(
            "defined('KINT_DIR')",
            $content,
            'Original unprefixed defined() guard should not remain'
        );
    }

    #[Test]
    public function it_does_not_prefix_constants_not_defined_in_package(): void
    {
        $this->runMozartOnKint();
        $content = file_get_contents($this->initFile);

        // KINT_SKIP_FACADE and KINT_SKIP_HELPERS are referenced via defined()
        // but NOT defined in init.php — they are externally set by the user.
        // Pass-2 should leave them unchanged since they are not in the
        // replaced constants map from pass-1.
        $this->assertStringContainsString(
            "defined('KINT_SKIP_FACADE')",
            $content,
            'KINT_SKIP_FACADE should not be prefixed (not defined in package)'
        );

        $this->assertStringContainsString(
            "defined('KINT_SKIP_HELPERS')",
            $content,
            'KINT_SKIP_HELPERS should not be prefixed (not defined in package)'
        );
    }
}
