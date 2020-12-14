<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Console\Commands\Compose;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
class MoverIntegrationTest extends TestCase
{

    /**
     * A temporary directory for creating and deleting files for these tests.
     *
     * @var string
     */
    protected $testsWorkingDir;

    /**
     * @var stdClass
     */
    protected $composer;

    /**
     * Set up a common settings object.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir';
        if (file_exists($this->testsWorkingDir)) {
            $this->delete_dir($this->testsWorkingDir);
        }
        mkdir($this->testsWorkingDir);

        $mozart_config = new class() {
            public $dep_namespace = "Mozart";
            public $classmap_prefix = "Mozart_";
            public $dep_directory = "/dep_directory/";
            public $classmap_directory = "/classmap_directory/";

        };

        $composer = new class() {
            public $require = array();
            public $extra;
        };

        $composer->extra = new class() {
            public $mozart;
        };

        $composer->extra->mozart = $mozart_config;

        $this->composer = $composer;
    }


    /**
     * Issue 43. Needs "aws/aws-sdk-php".
     *
     * League\Flysystem\FileExistsException : File already exists at path:
     * dep_directory/vendor/guzzle/guzzle/src/Guzzle/Cache/Zf1CacheAdapter.php
     */
    public function testAwsSdkSucceeds()
    {

        $composer = $this->composer;

        $composer->require["aws/aws-sdk-php"] = "2.8.31";

        $composer->extra->mozart->override_autoload = new class() {
            public $guzzle_guzzle;

            public function __construct()
            {
                $this->guzzle_guzzle = new class() {
                    public $psr_4 = array(
                        "Guzzle"=>"src/"
                    );
                };
            }
        };

        $composer_json_string = json_encode($composer);

        $composer_json_string = str_replace("psr_4", "psr-4", $composer_json_string);
        $composer_json_string = str_replace("guzzle_guzzle", "guzzle/guzzle", $composer_json_string);


        file_put_contents($this->testsWorkingDir . '/composer.json', $composer_json_string);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $this->assertEquals(0, $result);

        $this->assertFileExists($this->testsWorkingDir . '/dep_directory/Aws/Common/Aws.php');
    }


    /**
     * Issue 90. Needs "iio/libmergepdf".
     *
     * Error: "File already exists at path: classmap_directory/tecnickcom/tcpdf/tcpdf.php".
     */
    public function testLibpdfmergeSucceeds()
    {

        $composer = $this->composer;

        $composer->require["iio/libmergepdf"] = "4.0.3";

        file_put_contents($this->testsWorkingDir . '/composer.json', json_encode($composer));

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $this->assertEquals(0, $result);

        // This test would only fail on Windows?
        $this->assertDirectoryNotExists($this->testsWorkingDir .'classmap_directory/iio/libmergepdf/vendor/iio/libmergepdf/tcpdi');

        $this->assertFileExists($this->testsWorkingDir .'/classmap_directory/iio/libmergepdf/tcpdi/tcpdi.php');
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

        $this->delete_dir($dir);
    }

    protected function delete_dir($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

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
