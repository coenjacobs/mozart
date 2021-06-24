<?php

namespace BrianHenryIE\Strauss\Tests\Integration;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
use BrianHenryIE\Strauss\FileEnumerator;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class FileEnumeratorIntegrationTest
 * @package BrianHenryIE\Strauss
 * @coversNothing
 */
class FileEnumeratorIntegrationTest extends IntegrationTestCase
{

    public function testBuildFileList()
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
      "delete_vendor_files": false
    }
  }
}
EOD;

        file_put_contents($this->testsWorkingDir . 'composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir);

        // Only one because we haven't run "flat dependency list".
        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return ComposerPackage::fromFile($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $vendorDir = 'vendor' . DIRECTORY_SEPARATOR;

        $config = $this->createStub(StraussConfig::class);
        $config->method('getVendorDirectory')->willReturn($vendorDir);

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $config);

        $fileEnumerator->compileFileList();

        $list = array_keys($fileEnumerator->getAllFilesAndDependencyList());

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
