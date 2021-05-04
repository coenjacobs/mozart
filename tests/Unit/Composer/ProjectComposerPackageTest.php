<?php

namespace BrianHenryIE\Strauss\Tests\Unit\Composer;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
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

        $config = $composer->getStraussConfig();

        $this->assertInstanceOf(StraussConfig::class, $config);
    }
}
