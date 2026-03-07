<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\FilesAutoloaderBasenameCollision;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression test for #326: files autoloader flattens same-basename global
 * files and silently overwrites one.
 *
 * Two global-scope files with the same basename (helpers.php) but in different
 * source directories (src/ and lib/) must both survive as distinct output files.
 */
class FilesAutoloaderBasenameCollisionTest extends IntegrationTestCase
{
    /**
     * Create the synthetic files that the override_autoload points to.
     * These are placed inside the package's vendor directory after composer
     * update so we have full control over the scenario.
     */
    private function createSyntheticFiles(): void
    {
        $packageDir = $this->testsWorkingDir . '/vendor/mustangostang/spyc';

        $srcDir = $packageDir . '/src';
        $libDir = $packageDir . '/lib';

        mkdir($srcDir, 0777, true);
        mkdir($libDir, 0777, true);

        file_put_contents(
            $srcDir . '/helpers.php',
            "<?php\nfunction src_helper_one() { return 'from_src'; }\n"
        );
        file_put_contents(
            $libDir . '/helpers.php',
            "<?php\nfunction lib_helper_two() { return 'from_lib'; }\n"
        );
    }

    /**
     * Mozart must complete successfully when a package has two files entries
     * with the same basename in different directories.
     */
    #[Test]
    public function it_completes_without_errors(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $this->createSyntheticFiles();

        $result = $this->runMozart();

        $this->assertEquals(0, $result);
    }

    /**
     * Both files must exist in the output with their directory structure
     * preserved and distinct content.
     */
    #[Test]
    public function it_preserves_both_same_basename_files(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $this->createSyntheticFiles();

        $result = $this->runMozart();
        $this->assertEquals(0, $result);

        $srcTarget = $this->testsWorkingDir
            . '/classes/mustangostang/spyc/src/helpers.php';
        $libTarget = $this->testsWorkingDir
            . '/classes/mustangostang/spyc/lib/helpers.php';

        $this->assertFileExists($srcTarget, 'src/helpers.php must survive in output');
        $this->assertFileExists($libTarget, 'lib/helpers.php must survive in output');

        // Verify content: each file should retain its own function
        $srcContent = file_get_contents($srcTarget);
        $libContent = file_get_contents($libTarget);

        $this->assertStringContainsString('src_helper_one', $srcContent,
            'src/helpers.php must contain its original function');
        $this->assertStringContainsString('lib_helper_two', $libContent,
            'lib/helpers.php must contain its original function');

        // The two files must NOT contain each other's functions
        $this->assertStringNotContainsString('lib_helper_two', $srcContent,
            'src/helpers.php must not contain content from lib/helpers.php');
        $this->assertStringNotContainsString('src_helper_one', $libContent,
            'lib/helpers.php must not contain content from src/helpers.php');
    }

    /**
     * Both files must have their functions prefixed with the configured
     * functions_prefix.
     */
    #[Test]
    public function it_prefixes_functions_in_both_files(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $this->createSyntheticFiles();

        $result = $this->runMozart();
        $this->assertEquals(0, $result);

        $srcTarget = $this->testsWorkingDir
            . '/classes/mustangostang/spyc/src/helpers.php';
        $libTarget = $this->testsWorkingDir
            . '/classes/mustangostang/spyc/lib/helpers.php';

        $srcContent = file_get_contents($srcTarget);
        $libContent = file_get_contents($libTarget);

        $this->assertStringContainsString('mozarttest_src_helper_one', $srcContent,
            'Function in src/helpers.php should be prefixed');
        $this->assertStringContainsString('mozarttest_lib_helper_two', $libContent,
            'Function in lib/helpers.php should be prefixed');
    }
}
