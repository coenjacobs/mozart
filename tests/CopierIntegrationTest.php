<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\ComposerPackage;
use CoenJacobs\Mozart\Composer\ProjectComposerPackage;
use CoenJacobs\Mozart\Util\IntegrationTestCase;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class CopierTest
 * @package CoenJacobs\Mozart
 * @coversNothing
 */
class CopierIntegrationTest extends IntegrationTestCase
{

    public function testsPrepareTarget()
    {
        copy(__DIR__ . '/copier-integration-test-1.json', $this->testsWorkingDir . 'composer.json');

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
        $filepaths = $fileEnumerator->getFileList();

        $copier = new Copier($filepaths, $workingDir, $relativeTargetDir);

        $file = 'ContainerAwareTrait.php';
        $relativePath = 'league/container/src/';
        $targetPath = $this->testsWorkingDir . $relativeTargetDir . $relativePath;
        $targetFile = $targetPath . $file;

        mkdir(rtrim($targetPath, DIRECTORY_SEPARATOR), 0777, true);

        file_put_contents($targetFile, 'dummy file');

        assert(file_exists($targetFile));

        $copier->prepareTarget();

        $this->assertFileDoesNotExist($targetFile);
    }

    public function testsCopy()
    {
        copy(__DIR__ . '/copier-integration-test-1.json', $this->testsWorkingDir . 'composer.json');

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
        $filepaths = $fileEnumerator->getFileList();

        $copier = new Copier($filepaths, $workingDir, $relativeTargetDir);

        $file = 'Client.php';
        $relativePath = 'google/apiclient/src/';
        $targetPath = $this->testsWorkingDir . $relativeTargetDir . $relativePath;
        $targetFile = $targetPath . $file;

        $copier->prepareTarget();

        $copier->copy();

        $this->assertFileExists($targetFile);
    }
}
