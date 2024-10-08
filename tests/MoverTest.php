<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\Console\Commands\Compose;
use CoenJacobs\Mozart\Mover;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
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
     * @var Mozart
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

        $this->config = Mozart::loadFromString( json_encode($configArgs) );
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` are absent, create them.
     *
     * @test
     */
    #[Test]
    public function it_creates_absent_dirs(): void
    {
        $mover = new Mover($this->testsWorkingDir, $this->config);

        $packages = array();

        $mover->deleteTargetDirs($packages);

        $this->assertTrue(file_exists($this->testsWorkingDir . DIRECTORY_SEPARATOR
                                      . $this->config->getDepDirectory()));
        $this->assertTrue(file_exists($this->testsWorkingDir . DIRECTORY_SEPARATOR
                                      . $this->config->getClassmapDirectory()));
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` already exists with contents, it is not an issue.
     *
     * @test
     */
    #[Test]
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
    #[Test]
    public function it_deletes_subdirs_for_packages_about_to_be_moved(): void
    {
        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->getDepDirectory());
        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->getClassmapDirectory());

        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->getDepDirectory() . 'Pimple');
        mkdir($this->testsWorkingDir  . DIRECTORY_SEPARATOR . $this->config->getClassmapDirectory() . 'ezyang');

        $packages = array();
        foreach ($this->config->getPackages() as $packageString) {
            $testDummyComposerDir = $this->testsWorkingDir  . DIRECTORY_SEPARATOR . 'vendor'
                                    . DIRECTORY_SEPARATOR . $packageString;
            @mkdir($testDummyComposerDir, 0777, true);
            $testDummyComposerPath = $testDummyComposerDir . DIRECTORY_SEPARATOR . 'composer.json';
            $testDummyComposerContents = json_encode(new stdClass());

            file_put_contents($testDummyComposerPath, $testDummyComposerContents);

            $overrideAutoload = $this->config->getOverrideAutoload();
            if ( ! empty( $overrideAutoload ) ) {
                $overrideAutoload = $overrideAutoload->getByKey( $packageString );
            }
            $parsedPackage = PackageFactory::createPackage($testDummyComposerPath, $overrideAutoload);
            $packages[] = $parsedPackage;
        }

        $mover = new Mover($this->testsWorkingDir, $this->config);
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
    #[Test]
    public function it_moves_each_file_once_per_namespace()
    {

        // The composer.json with the Mozart requirement and `mozart compose` removed.
        copy(__DIR__ . '/issue89-composer.json', $this->testsWorkingDir . '/composer.json');

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
