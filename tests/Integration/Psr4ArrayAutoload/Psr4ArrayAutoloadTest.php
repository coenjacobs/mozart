<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\Psr4ArrayAutoload;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for PSR-4 autoload defined as an array.
 *
 * The caseyamcl/toc package defines its PSR-4 autoload as an array:
 *   "TOC\\": ["src/", "tests/"]
 *
 * When installed via --prefer-dist, the "tests/" directory is typically
 * excluded via .gitattributes, so Mozart must handle missing directories
 * gracefully.
 *
 * @see https://github.com/coenjacobs/mozart/issues/159
 */
class Psr4ArrayAutoloadTest extends IntegrationTestCase
{
    /**
     * Verifies that Mozart successfully processes a package with PSR-4
     * autoload defined as an array, even when some directories are missing.
     */
    #[Test]
    public function it_processes_package_with_psr4_array_autoload_successfully(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // The TOC namespace directory is created in the dependencies
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/TOC');
    }
}
