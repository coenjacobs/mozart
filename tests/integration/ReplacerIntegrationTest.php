<?php

namespace BrianHenryIE\Strauss\Tests\Integration;

use BrianHenryIE\Strauss\ChangeEnumerator;
use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
use BrianHenryIE\Strauss\Copier;
use BrianHenryIE\Strauss\FileEnumerator;
use BrianHenryIE\Strauss\Replacer;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 * Class ReplacerIntegrationTest
 * @package BrianHenryIE\Strauss\Tests\Integration
 * @coversNothing
 */
class ReplacerIntegrationTest extends IntegrationTestCase
{

    public function testReplaceNamespace()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss",
  "require": {
    "google/apiclient": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "delete_vendor_directories": false
    }
  }
}
EOD;

        file_put_contents($this->testsWorkingDir . '/composer.json', $composerJsonString);

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

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss",
  "require": {
    "setasign/fpdf": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "delete_vendor_directories": false
    }
  }
}
EOD;

        file_put_contents($this->testsWorkingDir . '/composer.json', $composerJsonString);

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
