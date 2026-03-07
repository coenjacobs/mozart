<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit;

use CoenJacobs\Mozart\Config\ConfigLoader;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Mover;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\PackageFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;

class MoverTest extends TestCase
{
    /**
     * A temporary directory for creating and deleting files for these tests.
     */
    protected string $testsWorkingDir;

    /**
     * composer->extra->mozart settings
     */
    protected Mozart $config;

    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir';
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }

        $pimpleAutoload = json_decode("{ \"psr-0\" : { \"Pimple\" : [ \"src/\" ]  } }");
        $htmlpurifierAutoload = json_decode("{ \"classmap\" : { \"Pimple\" => [ \"library/\" ]  } }");

        $configArgs = array(
            'dep_directory' => "/dep_directory/",
            'classmap_directory' => "/classmap_directory/",
            'packages' => array(
                "pimple/pimple",
                "ezyang/htmlpurifier",
            ),
            'override_autoload' => array(
                'pimple/pimple' => $pimpleAutoload,
                'ezyang/htmlpurifier' => $htmlpurifierAutoload,
            ),
        );

        $loader = new ConfigLoader();
        $this->config = $loader->fromString(json_encode($configArgs), Mozart::class);
        $this->config->setWorkingDir($this->testsWorkingDir);
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` are absent,
     * create them.
     */
    #[Test]
    public function it_creates_absent_dirs(): void
    {
        $mover = new Mover($this->config);

        $packages = array();

        $mover->deleteTargetDirs($packages);

        $this->assertTrue(file_exists($this->testsWorkingDir . DIRECTORY_SEPARATOR
                                      . $this->config->getDepDirectory()));
        $this->assertTrue(file_exists($this->testsWorkingDir . DIRECTORY_SEPARATOR
                                      . $this->config->getClassmapDirectory()));
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` already exists
     * with contents, it is not an issue.
     */
    #[Test]
    public function it_is_unpertrubed_by_existing_dirs(): void
    {
        $mover = new Mover($this->config);

        $depPath = $this->config->getWorkingDir() . $this->config->getDepDirectory();
        $classmapPath = $this->config->getWorkingDir() . $this->config->getClassmapDirectory();

        if (!file_exists($depPath)) {
            mkdir($depPath);
        }
        if (!file_exists($classmapPath)) {
            mkdir($classmapPath);
        }

        $this->assertDirectoryExists($depPath);
        $this->assertDirectoryExists($classmapPath);

        $packages = array();

        ob_start();

        $mover->deleteTargetDirs($packages);

        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` contains a
     * subdir we are going to need when moving, delete the subdir.
     */
    #[Test]
    public function it_deletes_subdirs_for_packages_about_to_be_moved(): void
    {
        mkdir($this->testsWorkingDir . DIRECTORY_SEPARATOR . $this->config->getDepDirectory());
        mkdir($this->testsWorkingDir . DIRECTORY_SEPARATOR . $this->config->getClassmapDirectory());

        mkdir($this->testsWorkingDir . DIRECTORY_SEPARATOR . $this->config->getDepDirectory() . 'Pimple');
        mkdir($this->testsWorkingDir . DIRECTORY_SEPARATOR . $this->config->getClassmapDirectory() . 'ezyang');

        $packages = array();
        foreach ($this->config->getPackages() as $packageString) {
            $testDummyComposerDir = $this->testsWorkingDir . DIRECTORY_SEPARATOR . 'vendor'
                                    . DIRECTORY_SEPARATOR . $packageString;
            @mkdir($testDummyComposerDir, 0777, true);
            $testDummyComposerPath = $testDummyComposerDir . DIRECTORY_SEPARATOR . 'composer.json';
            $testDummyComposerContents = json_encode(new stdClass());

            file_put_contents($testDummyComposerPath, $testDummyComposerContents);

            $overrideAutoload = $this->config->getOverrideAutoload();
            if (!empty($overrideAutoload)) {
                $overrideAutoload = $overrideAutoload->getByKey($packageString);
            }
            $factory = new PackageFactory();
            $finder = new PackageFinder();
            $parsedPackage = $factory->createPackage($testDummyComposerPath, $overrideAutoload);
            $parsedPackage->loadDependencies($finder);
            $packages[] = $parsedPackage;
        }

        $mover = new Mover($this->config);
        $mover->deleteTargetDirs($packages);

        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . $this->config->getDepDirectory() . 'Pimple');
        $this->assertDirectoryDoesNotExist($this->testsWorkingDir . $this->config->getDepDirectory() . 'ezyang');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $dir = $this->testsWorkingDir;

        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator(
            $it,
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        chdir(__DIR__);
    }
}
