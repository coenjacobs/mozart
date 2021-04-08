<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\ComposerPackage;
use CoenJacobs\Mozart\Composer\ProjectComposerPackage;
use CoenJacobs\Mozart\Util\IntegrationTestCase;

/**
 * Class CopierTest
 * @package CoenJacobs\Mozart
 * @coversNothing
 */
class ChangeEnumeratorIntegrationTest extends IntegrationTestCase
{

    /**
     * Given a list of files, find all the global classes and the namespaces.
     */
    public function testOne()
    {
        copy(__DIR__ . '/changeenumerator-integration-test-1.json', $this->testsWorkingDir . 'composer.json');

        chdir($this->testsWorkingDir);
        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir);

        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return new ComposerPackage($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'nannerl' . DIRECTORY_SEPARATOR;

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $relativeTargetDir);

        $fileEnumerator->compileFileList();

        $copier = new Copier($fileEnumerator->getFileList(), $workingDir, $relativeTargetDir);

        $copier->prepareTarget();

        $copier->copy();



        $changeEnumerator = new ChangeEnumerator();

        $phpFileList = $fileEnumerator->getPhpFileList();

        $changeEnumerator->findInFiles($workingDir . $relativeTargetDir, $phpFileList);


        $classes = $changeEnumerator->getDiscoveredClasses();

        $namespaces = $changeEnumerator->getDiscoveredNamespaces();

        $this->assertNotEmpty($classes);
        $this->assertNotEmpty($namespaces);

        $this->assertContains('Google_Task_Composer', $classes);
    }
}
