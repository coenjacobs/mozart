<?php
declare(strict_types=1);


use CoenJacobs\Mozart\Console\Commands\Compose;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers CoenJacobs\Mozart\Replace\NamespaceReplacer::
 *
 * Class NamespaceReplacerIntegrationTest
 */
class NamespaceReplacerIntegrationTest extends TestCase
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
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }

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
     * After PR #84, running Mozart on Mpdf began prefixing the class name inside the namespaced file.
     *
     * The problem coming from the filename matching the namespace name?
     *
     * dev-master#5d8041fdefc94ff57edcbe83ab468a9988c4fc11
     *
     * @see https://github.com/coenjacobs/mozart/pull/84/files
     *
     * Should be: "class Mpdf implements" because its namespace has already been prefixed.
     */
    public function test_it_does_not_make_classname_replacement_inside_namespaced_file()
    {

        $composer = $this->composer;

        $composer->require["mpdf/mpdf"] = "8.0.10";

        file_put_contents($this->testsWorkingDir . '/composer.json', json_encode($composer));

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $mpdf_php = file_get_contents($this->testsWorkingDir .'/dep_directory/Mpdf/Mpdf.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('class Mozart\Mpdf implements', $mpdf_php);

        // Confirm solution is correct.
        $this->assertStringContainsString('class Mpdf implements', $mpdf_php);
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
