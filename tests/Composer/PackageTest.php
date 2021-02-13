<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Composer\Package;
use PHPUnit\Framework\TestCase;

/**
 * Class PackageTest
 * @covers \CoenJacobs\Mozart\Composer\Package
 */
class PackageTest extends TestCase
{

    protected $testsWorkingDir;

    /**
     * Package currently reads a file in its constructor so we need to write the files first.
     *
     * Make a directory
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir';
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }
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

    /**
     * @covers \CoenJacobs\Mozart\Composer\Package::__construct
     */
    public function test_instantiation()
    {

        $composer  = <<<'EOD'
{
    "name": "mustache/mustache",
    "autoload": {
			"psr-0": { "Mustache": "src/" }
    }
}
EOD;

        $filename = $this->testsWorkingDir . '/composer.json';

        file_put_contents($filename, $composer);

        $package = new Package($this->testsWorkingDir);

        $this->assertEquals($this->testsWorkingDir, $package->path);

        $this->assertNotNull($package->config);
    }


    /**
     * @covers \CoenJacobs\Mozart\Composer\Package::__construct
     */
    public function test_instantiation_override_autoload()
    {

        $composer  = <<<'EOD'
{
    "name": "google/apiclient",
    "autoload": {
        "psr-0": {
            "Google_": "src/"
        }
    }
}
EOD;
        $overrideAutoload = new stdClass();
        $overrideAutoload->classmap = [  "src/Google/Service/"  ] ;

        $filename = $this->testsWorkingDir . '/composer.json';

        file_put_contents($filename, $composer);

        $package = new Package($this->testsWorkingDir, $overrideAutoload);
        $package->findAutoloaders();

        $this->assertCount(1, $package->autoloaders);
        $this->assertInstanceOf(\CoenJacobs\Mozart\Composer\Autoload\Classmap::class, $package->autoloaders[0]);
    }


    /**
     * @covers \CoenJacobs\Mozart\Composer\Package::findAutoloaders
     */
    public function test_find_autoloaders_finds_psr_0()
    {

        $composer  = <<<'EOD'
{
    "name": "mustache/mustache",
    "autoload": {
			"psr-0": { "Mustache": "src/" }
    }
}
EOD;

        $filename = $this->testsWorkingDir . '/composer.json';

        file_put_contents($filename, $composer);

        $package = new Package($this->testsWorkingDir);
        $package->findAutoloaders();

        $this->assertCount(1, $package->autoloaders);

        $this->assertInstanceOf(\CoenJacobs\Mozart\Composer\Autoload\Psr0::class, $package->autoloaders[0]);
    }

    /**
     * @covers \CoenJacobs\Mozart\Composer\Package::findAutoloaders
     */
    public function test_find_autoloaders_finds_psr_4()
    {

        $composer  = <<<'EOD'
{
    "name": "psr/container",
    "autoload": {
        "psr-4": {
            "Psr\\Container\\": "src/"
        }
    }
}
EOD;

        $filename = $this->testsWorkingDir . '/composer.json';

        file_put_contents($filename, $composer);

        $package = new Package($this->testsWorkingDir);
        $package->findAutoloaders();

        $this->assertCount(1, $package->autoloaders);

        $this->assertInstanceOf(\CoenJacobs\Mozart\Composer\Autoload\Psr4::class, $package->autoloaders[0]);
    }

    /**
     * @covers \CoenJacobs\Mozart\Composer\Package::findAutoloaders
     */
    public function test_find_autoloaders_finds_classmap()
    {

        $composer  = <<<'EOD'
{
    "name": "iio/libmergepdf",
    "autoload": {
        "classmap": [
            "tcpdi/"
        ]
    }
}
EOD;

        $filename = $this->testsWorkingDir . '/composer.json';

        file_put_contents($filename, $composer);

        $package = new Package($this->testsWorkingDir);
        $package->findAutoloaders();

        $this->assertCount(1, $package->autoloaders);

        $this->assertInstanceOf(\CoenJacobs\Mozart\Composer\Autoload\Classmap::class, $package->autoloaders[0]);
    }

    /**
     * @covers \CoenJacobs\Mozart\Composer\Package::findAutoloaders
     */
    public function test_find_multiple_different_autoloaders()
    {

        $composer  = <<<'EOD'
{
    "name": "google/apiclient",
    "autoload": {
        "psr-0": {
            "Google_": "src/"
        },
        "classmap": [
            "src/Google/Service/"
        ]
    }
}
EOD;

        $filename = $this->testsWorkingDir . '/composer.json';

        file_put_contents($filename, $composer);

        $package = new Package($this->testsWorkingDir);
        $package->findAutoloaders();

        $this->assertCount(2, $package->autoloaders);
    }

    public function test_find_multiple_same_autoloaders()  {

	    $composer  = <<<'EOD'
{
    "name": "rubix/tensor",
    "autoload": {
        "psr-4": {
            "Tensor\\": "src/",
            "JAMA\\": "lib/JAMA"
        }
    }
}
EOD;

	    $filename = $this->testsWorkingDir . '/composer.json';

	    file_put_contents($filename, $composer);

	    $package = new Package($this->testsWorkingDir);
	    $package->findAutoloaders();

	    $this->assertCount(2, $package->autoloaders);
    }
}
