<?php

namespace BrianHenryIE\Strauss\Tests\Integration;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
use BrianHenryIE\Strauss\Copier;
use BrianHenryIE\Strauss\FileEnumerator;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;
use stdClass;

/**
 * Class CopierTest
 * @package BrianHenryIE\Strauss
 * @coversNothing
 */
class CopierIntegrationTest extends IntegrationTestCase
{

    public function testsPrepareTarget()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss",
  "require": {
    "league/container": "*"
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

        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return ComposerPackage::fromFile($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'strauss' . DIRECTORY_SEPARATOR;
        $vendorDir = 'vendor' . DIRECTORY_SEPARATOR;

        $config = $this->createStub(StraussConfig::class);
        $config->method('getVendorDirectory')->willReturn($vendorDir);

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $config);
        $fileEnumerator->compileFileList();
        $filepaths = $fileEnumerator->getAllFilesAndDependencyList();

        $copier = new Copier($filepaths, $workingDir, $relativeTargetDir, $vendorDir);

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

        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return ComposerPackage::fromFile($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'strauss' . DIRECTORY_SEPARATOR;
        $vendorDir = 'vendor' . DIRECTORY_SEPARATOR;

        $config = $this->createStub(StraussConfig::class);
        $config->method('getVendorDirectory')->willReturn($vendorDir);

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $config);
        $fileEnumerator->compileFileList();
        $filepaths = $fileEnumerator->getAllFilesAndDependencyList();

        $copier = new Copier($filepaths, $workingDir, $relativeTargetDir, $vendorDir);

        $file = 'Client.php';
        $relativePath = 'google/apiclient/src/';
        $targetPath = $this->testsWorkingDir . $relativeTargetDir . $relativePath;
        $targetFile = $targetPath . $file;

        $copier->prepareTarget();

        $copier->copy();

        $this->assertFileExists($targetFile);
    }




    /**
     * Set up a common settings object.
     * @see MoverTest.php
     */
    protected function createComposer(): void
    {
//        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir/';
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }

        $mozartConfig = new stdClass();
        $mozartConfig->dep_directory = "/dep_directory/";
        $mozartConfig->classmap_directory = "/classmap_directory/";
        $mozartConfig->packages = array(
            "pimple/pimple",
            "ezyang/htmlpurifier"
        );

        $pimpleAutoload = new stdClass();
        $pimpleAutoload->{'psr-0'} = new stdClass();
        $pimpleAutoload->{'psr-0'}->Pimple = "src/";

        $htmlpurifierAutoload = new stdClass();
        $htmlpurifierAutoload->classmap = new stdClass();
        $htmlpurifierAutoload->classmap->Pimple = "library/";

        $mozartConfig->override_autoload = array();
        $mozartConfig->override_autoload["pimple/pimple"] = $pimpleAutoload;
        $mozartConfig->override_autoload["ezyang/htmlpurifier"] = $htmlpurifierAutoload;

        $composer = new stdClass();
        $composer->extra = new stdClass();
        $composer->extra->mozart = $mozartConfig;

        $composerFilepath = $this->testsWorkingDir . 'composer.json';
        $composerJson = json_encode($composer) ;
        file_put_contents($composerFilepath, $composerJson);

        $this->config = StraussConfig::loadFromFile($composerFilepath);
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` are absent, create them.
     * @see MoverTest.php
     * @test
     */
    public function it_creates_absent_dirs(): void
    {
        $this->markTestIncomplete();

        $mover = new Mover($this->testsWorkingDir, $this->config);

        // Make sure the directories don't exist.
        assert(! file_exists($this->testsWorkingDir . $this->config->gett()), "{$this->testsWorkingDir}{$this->config->getDepDirectory()} already exists");
        assert(! file_exists($this->testsWorkingDir . $this->config->getClassmapDirectory()));

        $packages = array();

        $mover->deleteTargetDirs($packages);

        $this->assertTrue(file_exists($this->testsWorkingDir
            . $this->config->getDepDirectory()));
        $this->assertTrue(file_exists($this->testsWorkingDir
            . $this->config->getClassmapDirectory()));
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` already exists with contents, it is not an issue.
     *
     * @see MoverTest.php
     *
     * @test
     */
    public function it_is_unpertrubed_by_existing_dirs(): void
    {
        $this->markTestIncomplete();

        $mover = new Mover($this->testsWorkingDir, $this->config);

        if (!file_exists($this->testsWorkingDir . $this->config->getDepDirectory())) {
            mkdir($this->testsWorkingDir . $this->config->getDepDirectory());
        }
        if (!file_exists($this->testsWorkingDir . $this->config->getClassmapDirectory())) {
            mkdir($this->testsWorkingDir . $this->config->getClassmapDirectory());
        }

        $this->assertDirectoryExists($this->testsWorkingDir . $this->config->getDepDirectory());
        $this->assertDirectoryExists($this->testsWorkingDir . $this->config->getClassmapDirectory());

        $packages = array();

        ob_start();

        $mover->deleteTargetDirs($packages);

        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` contains a subdir we are going to need when moving,
     * delete the subdir. aka:  If subfolders exist for dependencies we are about to manage, delete those subfolders.
     *
     * @see MoverTest.php
     *
     * @test
     */
    public function it_deletes_subdirs_for_packages_about_to_be_moved(): void
    {
        $this->markTestIncomplete();

        $mover = new Mover($this->testsWorkingDir, $this->config);

        @mkdir($this->testsWorkingDir . $this->config->getDepDirectory());
        @mkdir($this->testsWorkingDir . $this->config->getClassmapDirectory());

        @mkdir($this->testsWorkingDir . $this->config->getDepDirectory() . 'Pimple');
        @mkdir($this->testsWorkingDir . $this->config->getClassmapDirectory() . 'ezyang');

        $packages = array();
        foreach ($this->config->getPackages() as $packageString) {
            $testDummyComposerDir = $this->testsWorkingDir  . 'vendor'
                . DIRECTORY_SEPARATOR . $packageString;
            @mkdir($testDummyComposerDir, 0777, true);
            $testDummyComposerPath = $testDummyComposerDir . DIRECTORY_SEPARATOR . 'composer.json';
            $testDummyComposerContents = json_encode(new stdClass());

            file_put_contents($testDummyComposerPath, $testDummyComposerContents);
            $parsedPackage = new ComposerPackageConfig($testDummyComposerDir, $this->config->getOverrideAutoload()[$packageString]);
            $parsedPackage->findAutoloaders();
            $packages[] = $parsedPackage;
        }

        $mover->deleteTargetDirs($packages);

        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . $this->config->getDepDirectory() . 'Pimple');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . $this->config->getDepDirectory() . 'ezyang');
    }
}
