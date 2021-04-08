<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\ComposerPackage;
use CoenJacobs\Mozart\Composer\ProjectComposerPackage;
use CoenJacobs\Mozart\Util\IntegrationTestCase;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class FileEnumeratorIntegrationTest
 * @package CoenJacobs\Mozart
 * @coversNothing
 */
class FileEnumeratorIntegrationTest extends IntegrationTestCase
{

    public function testBuildFileList()
    {
        copy(__DIR__ . '/fileenumerator-integration-test-1.json', $this->testsWorkingDir . 'composer.json');

        chdir($this->testsWorkingDir);
        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir);

        // Only one because we haven't run "flat dependency list".
        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return new ComposerPackage($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'nannerl' . DIRECTORY_SEPARATOR;

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $relativeTargetDir);

        $fileEnumerator->compileFileList();

        $list = $fileEnumerator->getFileList();

        $this->assertContains('google/apiclient/src/aliases.php', $list);
    }


    public function testClassmapAutoloader()
    {
        $this->markTestIncomplete();
    }


    public function testFilesAutoloader()
    {
        $this->markTestIncomplete();
    }
}
