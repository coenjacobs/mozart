<?php

namespace BrianHenryIE\Strauss\Tests\Integration;

use BrianHenryIE\Strauss\ChangeEnumerator;
use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
use BrianHenryIE\Strauss\Copier;
use BrianHenryIE\Strauss\FileEnumerator;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 * Class CopierTest
 * @package BrianHenryIE\Strauss
 * @coversNothing
 */
class ChangeEnumeratorIntegrationTest extends IntegrationTestCase
{

    /**
     * Given a list of files, find all the global classes and the namespaces.
     */
    public function testOne()
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

        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return new ComposerPackage($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'strauss' . DIRECTORY_SEPARATOR;

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
