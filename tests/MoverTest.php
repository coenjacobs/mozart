<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Composer\ComposerPackageConfig;
use CoenJacobs\Mozart\Composer\MozartConfig;
use CoenJacobs\Mozart\Console\Commands\Compose;
use CoenJacobs\Mozart\Mover;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    protected function setUp(): void
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

        $composerFilepath = $this->testsWorkingDir . '/composer.json';
        $composerJson = json_encode( $composer ) ;
        file_put_contents( $composerFilepath, $composerJson );

        $this->config = MozartConfig::loadFromFile($composerFilepath);
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` are absent, create them.
     *
     * @test
     */
    public function it_creates_absent_dirs(): void
    {
        $mover = new Mover($this->testsWorkingDir, $this->config);

        // Make sure the directories don't exist.
        assert( ! file_exists($this->testsWorkingDir . $this->config->getDepDirectory() ), "{$this->testsWorkingDir}{$this->config->getDepDirectory()} already exists" );
        assert( ! file_exists($this->testsWorkingDir . $this->config->getClassmapDirectory()));

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
     * @test
     */
    public function it_is_unpertrubed_by_existing_dirs(): void
    {
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
     * @test
     */
    public function it_deletes_subdirs_for_packages_about_to_be_moved(): void
    {
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

    /**
     * If a file is specified more than once in an autoloader, e.g. is explicitly listed and is also in a folder listed,
     * a "File already exists at path" error occurs.
     *
     * To fix this, we enumerate the files to be copied using a dictionary indexed with the source file path, then loop
     * and copy, thus only copying each one once.
     *
     * Original error:
     * "League\Flysystem\FileExistsException : File already exists at path: lib/classes/tecnickcom/tcpdf/tcpdf.php"
     *
     * Test is using a known problematic autoloader:
     * "iio/libmergepdf": {
     *   "classmap": [
     *     "config",
     *     "include",
     *     "tcpdf.php",
     *     "tcpdf_parser.php",
     *     "tcpdf_import.php",
     *     "tcpdf_barcodes_1d.php",
     *     "tcpdf_barcodes_2d.php",
     *     "include/tcpdf_colors.php",
     *     "include/tcpdf_filters.php",
     *     "include/tcpdf_font_data.php",
     *     "include/tcpdf_fonts.php",
     *     "include/tcpdf_images.php",
     *     "include/tcpdf_static.php",
     *     "include/barcodes/datamatrix.php",
     *     "include/barcodes/pdf417.php",
     *     "include/barcodes/qrcode.php"
     *    ]
     *  }
     *
     * @see https://github.com/coenjacobs/mozart/issues/89
     *
     * @test
     */
    public function it_moves_each_file_once_per_namespace()
    {
        $this->markTestSkipped( 'iio/libmergepdf causing PHP Unit to hang');
        // The composer.json with the Mozart requirement and `mozart compose` removed.
        copy(__DIR__ . '/issue89-composer.json', $this->testsWorkingDir . 'composer.json');

        chdir($this->testsWorkingDir);

        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        // $this->expectException(League\Flysystem\FileExistsException::class);

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        // On the failing test, an exception was thrown and this line was not reached.
        $this->assertEquals(0, $result);
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
        chdir(__DIR__);
    }
}
