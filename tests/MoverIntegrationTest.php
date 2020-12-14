<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Console\Commands\Compose;
use League\Flysystem\FileExistsException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers CoenJacobs\Mozart\Mover::
 *

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
    public function test_aws_sdk_succeeds()
    {

        $composer = $this->composer;

        $composer->require["aws/aws-sdk-php"] = "2.8.31";

        file_put_contents($this->testsWorkingDir . '/composer.json', json_encode($composer));

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        try {
            $php_string = file_get_contents($this->testsWorkingDir . '/dep_directory/Aws/Ses/Common/Aws.php');
        } catch (FileExistsException $e) {
            $this->fail();
        }

//        $this->assertStringContainsString('class Mpdf implements', $php_string);
    }


    /**
     * Issue 90. Needs "iio/libmergepdf".
     *
     * Error: "File already exists at path: classmap_directory/tecnickcom/tcpdf/tcpdf.php".
     */
    public function test_libpdfmerge_succeeds()
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
	    // classmap_directory/tecnickcom/tcpdf/tcpdf.php
        $php_string = file_get_contents($this->testsWorkingDir .'/dep_directory/iio/libmergepdf/tcpdi/tcpdi.php');

//        // Confirm solution is correct.
//        $this->assertStringContainsString('class Mpdf implements', $php_string);
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
		if(!file_exists($dir)){
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
