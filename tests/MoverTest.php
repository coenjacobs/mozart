<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Composer\Package;
use CoenJacobs\Mozart\Mover;
use PHPUnit\Framework\TestCase;

class MoverTest extends TestCase
{

    /**
     * A temporary directory for creating and deleting files for these tests.
     *
     * @var string
     */
    protected $testsWorkingDir;

    /**
     * composer->extra->mozart settings
     *
     * @var stdClass
     */
    protected $config;

    /**
     * Set up a common settings object.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir';
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }
        
        $config = new class() {
        };
        $config->dep_directory = "/dep_directory/";
        $config->classmap_directory = "/classmap_directory/";
        $config->packages = array(
                "pimple/pimple",
                "ezyang/htmlpurifier"
            );

        $pimpleAutoload = json_decode("{ \"psr-0\" : { \"Pimple\" : [ \"src/\" ]  } }");
        $htmlpurifierAutoload = json_decode("{ \"classmap\" : { \"Pimple\" => [ \"library/\" ]  } }");

        $config->override_autoload = array();
        $config->override_autoload["pimple/pimple"] = $pimpleAutoload;
        $config->override_autoload["ezyang/htmlpurifier"] = $htmlpurifierAutoload;

        $this->config = $config;
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` are absent, create them.
     *
     * @test
     */
    public function it_creates_absent_dirs(): void
    {
        $mover = new Mover($this->testsWorkingDir, $this->config);

        $packages = array();

        $mover->deleteTargetDirs($packages);

        $this->assertTrue(file_exists($this->testsWorkingDir . DIRECTORY_SEPARATOR
                                      . $this->config->dep_directory));
        $this->assertTrue(file_exists($this->testsWorkingDir . DIRECTORY_SEPARATOR
                                      . $this->config->classmap_directory));
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` already exists with contents, it is not an issue.
     *
     * @test
     */
    public function it_is_unpertrubed_by_existing_dirs(): void
    {
        $mover = new Mover($this->testsWorkingDir, $this->config);

        if (!file_exists($this->testsWorkingDir . $this->config->dep_directory)) {
            mkdir($this->testsWorkingDir . $this->config->dep_directory);
        }
        if (!file_exists($this->testsWorkingDir . $this->config->classmap_directory)) {
            mkdir($this->testsWorkingDir . $this->config->classmap_directory);
        }

        $this->assertDirectoryExists($this->testsWorkingDir . $this->config->dep_directory);
        $this->assertDirectoryExists($this->testsWorkingDir . $this->config->classmap_directory);
  
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
     * @test
     */
    public function it_deletes_subdirs_for_packages_about_to_be_moved(): void
    {
        $mover = new Mover($this->testsWorkingDir, $this->config);

        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->dep_directory);
        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->classmap_directory);

        // TODO: Create the subdirs that should be deleted.
        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->dep_directory . 'Pimple');
        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->classmap_directory . 'ezyang');

        $packages = array();
        foreach ($this->config->packages as $packageString) {
            $testDummyComposerDir = $this->testsWorkingDir  . DIRECTORY_SEPARATOR . 'vendor'
                                    . DIRECTORY_SEPARATOR . $packageString;
            @mkdir($testDummyComposerDir, 0777, true);
            $testDummyComposerPath = $testDummyComposerDir . DIRECTORY_SEPARATOR . 'composer.json';
            $testDummyComposerContents = json_encode(new stdClass());

            file_put_contents($testDummyComposerPath, $testDummyComposerContents);
            $parsedPackage = new Package($testDummyComposerDir, $this->config->override_autoload[$packageString]);
            $parsedPackage->findAutoloaders();
            $packages[] = $parsedPackage;
        }

        $mover->deleteTargetDirs($packages);

        $this->assertDirectoryNotExists($this->testsWorkingDir . $this->config->dep_directory . 'Pimple');
        $this->assertDirectoryNotExists($this->testsWorkingDir . $this->config->dep_directory . 'ezyang');
    }

    /**
     * Delete $this->testsWorkingDir after each test.
     *
     * @see https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
     */
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
    }
}
