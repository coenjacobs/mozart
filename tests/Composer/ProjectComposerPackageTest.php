<?php

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Extra\NannerlConfig;
use PHPUnit\Framework\TestCase;

class ProjectComposerPackageTest extends TestCase
{


    /**
     * A simple test to check the getters all work.
     */
    public function testParseJson()
    {

        $testFile = __DIR__ . '/projectcomposerpackage-test-1.json';

        $composer = new ProjectComposerPackage($testFile);

        $config = $composer->getNannerlConfig();

        $this->assertInstanceOf(NannerlConfig::class, $config);
    }
}
