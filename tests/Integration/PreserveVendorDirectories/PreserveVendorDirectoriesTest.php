<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\PreserveVendorDirectories;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class PreserveVendorDirectoriesTest extends IntegrationTestCase
{
    /**
     * Verifies that setting `delete_vendor_directories` to `false` preserves
     * the original vendor directories after Mozart has moved and rewritten the
     * package files. The dependencies should exist in both the target directory
     * (rewritten) and the original vendor directory (untouched).
     */
    #[Test]
    public function it_preserves_vendor_directories_when_configured(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        // Dependencies are moved and rewritten into the target directory
        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/Pimple');

        // Original vendor directories are preserved because delete_vendor_directories is false
        $this->assertDirectoryExists($this->testsWorkingDir . '/vendor/pimple/pimple');
    }
}
