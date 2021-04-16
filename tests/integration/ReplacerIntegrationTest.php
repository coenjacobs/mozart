<?php

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
use BrianHenryIE\Strauss\Util\IntegrationTestCase;

class ReplacerIntegrationTest extends IntegrationTestCase
{

    public function testReplaceNamespace()
    {
        copy(__DIR__ . '/replacer-integration-test-1.json', $this->testsWorkingDir . 'composer.json');

        chdir($this->testsWorkingDir);
        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir);
        $config = $projectComposerPackage->getStraussConfig();

        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return new ComposerPackage($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'strauss' . DIRECTORY_SEPARATOR;
        $absoluteTargetDir = $workingDir . $relativeTargetDir;

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $relativeTargetDir);
        $fileEnumerator->compileFileList();
        $fileList = $fileEnumerator->getFileList();
        $phpFileList = $fileEnumerator->getPhpFileList();

        $copier = new Copier($fileList, $workingDir, $relativeTargetDir);
        $copier->prepareTarget();
        $copier->copy();

        $changeEnumerator = new ChangeEnumerator();
        $changeEnumerator->findInFiles($absoluteTargetDir, $phpFileList);
        $namespaces = $changeEnumerator->getDiscoveredNamespaces();
        $classes = $changeEnumerator->getDiscoveredClasses();

        $replacer = new Replacer($config, $workingDir);

        $replacer->replaceInFiles($namespaces, $classes, $phpFileList);

        $updatedFile = file_get_contents($absoluteTargetDir . 'google/apiclient/src/CLient.php');

        $this->assertStringContainsString('use BrianHenryIE\Strauss\Google\AccessToken\Revoke;', $updatedFile);
    }


    public function testReplaceClass()
    {
        copy(__DIR__ . '/replacer-integration-test-2.json', $this->testsWorkingDir . 'composer.json');

        chdir($this->testsWorkingDir);
        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir);
        $config = $projectComposerPackage->getStraussConfig();

        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return new ComposerPackage($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'strauss' . DIRECTORY_SEPARATOR;
        $absoluteTargetDir = $workingDir . $relativeTargetDir;

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $relativeTargetDir);
        $fileEnumerator->compileFileList();
        $fileList = $fileEnumerator->getFileList();
        $phpFileList = $fileEnumerator->getPhpFileList();

        $copier = new Copier($fileList, $workingDir, $relativeTargetDir);
        $copier->prepareTarget();
        $copier->copy();

        $changeEnumerator = new ChangeEnumerator();
        $changeEnumerator->findInFiles($absoluteTargetDir, $phpFileList);
        $namespaces = $changeEnumerator->getDiscoveredNamespaces();
        $classes = $changeEnumerator->getDiscoveredClasses();

        $replacer = new Replacer($config, $workingDir);

        $replacer->replaceInFiles($namespaces, $classes, $phpFileList);

        $updatedFile = file_get_contents($absoluteTargetDir . 'setasign/fpdf/fpdf.php');

        $this->assertStringContainsString('class BrianHenryIE_Strauss_FPDF', $updatedFile);
    }
}
