<?php
declare(strict_types=1);


use CoenJacobs\Mozart\Console\Commands\Compose;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers CoenJacobs\Mozart\Replace\ClassmapReplacer::
 *
 * Class NamespaceReplacerIntegrationTest
 */
class ClassmapReplacerIntegrationTest extends TestCase
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

        $this->testsWorkingDir = __DIR__ . '/temptestdir/';
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }

        $mozart_config = new class() {
            public $dep_namespace = "Mozart";
            public $classmap_prefix = "Mozart_";
            public $dep_directory = "/dep_directory/";
            public $classmap_directory = "/classmap_directory/";
            public $override_autoload;
        };

        $composer = new class() {
            public $repositories = array();
            public $require = array();
            public $minimum_stability = "dev";
            public $extra;
        };

        $composer->extra = new stdClass();

        $composer->extra->mozart = $mozart_config;

        $this->composer = $composer;
    }

    /**
     * Issue #93 shows a classname being updated inside a class whose namespace has also been updated
     * by Mozart.
     *
     * This is caused by the same files being loaded by both a PSR-4 autolaoder and classmap autoloader.
     * @see https://github.com/katzgrau/KLogger/blob/de2d3ab6777a393a9879e0496ebb8e0644066e3f/composer.json#L24-L29
     */
    public function test_it_does_not_make_classname_replacement_inside_namespaced_file()
    {

        $this->markTestSkipped('composer not respecting pinned commit');


        $composer = $this->composer;

        $composer->repositories[] = new class() {
            public $url = "https://github.com/BrianHenryIE/bh-wp-logger";
            public $type = "git";
        };

        $composer->require["brianhenryie/wp-logger"] = "dev-master#dd2bb0665e01e11b282178e76a2334198d3860c5";

        $composer_json_string = json_encode($composer);
        $composer_json_string = str_replace('minimum_stability', 'minimum-stability', $composer_json_string);

        file_put_contents($this->testsWorkingDir . 'composer.json', $composer_json_string);

        chdir($this->testsWorkingDir);

        exec('composer update');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

	    $php_string = file_get_contents($this->testsWorkingDir .'dep_directory/BrianHenryIE/WP_Logger/class-logger.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('class Mozart_Logger extends', $php_string);

        // Confirm solution is correct.
        $this->assertStringContainsString('class Logger extends', $php_string);
    }



	/**
	 * Issue #86 – "class as" appeared in a comment and later the keyword as was prefixed!
	 *
	 * Solved by https://github.com/ziodave
	 */
	public function test_do_not_parse_comments_to_classnames()
	{

		$composer = $this->composer;

		$composer->require["pear/pear-core-minimal"] = "v1.10.10";

		$composer->extra->mozart->override_autoload = new class() {
			public $pear_pear_code_minimal;
			public $pear_console_getopt;
			public function __construct()
			{
				$this->pear_pear_code_minimal = new class() {
					public $classmap = array(
						"src/"
					);
				};
				$this->pear_console_getopt = new stdClass();
			}
		};


		$composer_json_string = json_encode($composer);
		$composer_json_string = str_replace("pear_pear_core_minimal", "pear/pear-core-minimal", $composer_json_string);
		$composer_json_string = str_replace("pear_console_getopt", "pear/console_getopt", $composer_json_string);

		file_put_contents($this->testsWorkingDir . '/composer.json', $composer_json_string);

		chdir($this->testsWorkingDir);

		exec('composer install');

		$inputInterfaceMock = $this->createMock(InputInterface::class);
		$outputInterfaceMock = $this->createMock(OutputInterface::class);

		$mozartCompose = new Compose();

		$result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

		$php_string = file_get_contents($this->testsWorkingDir .'/classmap_directory/pear/pear_exception/PEAR/Exception.php');

		// Confirm problem is gone.
		$this->assertStringNotContainsString('foreach (self::$_observers Mozart_as $func) {', $php_string);

		// Confirm solution is correct.
		$this->assertStringContainsString('foreach (self::$_observers as $func) {', $php_string);
	}

	/**
	 * Like issue #86, when prefixing WP_Dependency_Installer, words in comments were
	 *
	 * @see https://github.com/afragen/wp-dependency-installer/
	 */
	public function test_do_not_parse_comments_to_classnames_wp_dependency_installer()
	{

		$composer = $this->composer;

		$composer->require["afragen/wp-dependency-installer"] = "3.1";

		$composer_json_string = json_encode($composer);

		file_put_contents($this->testsWorkingDir . '/composer.json', $composer_json_string);

		chdir($this->testsWorkingDir);

		exec('composer install');

		$inputInterfaceMock = $this->createMock(InputInterface::class);
		$outputInterfaceMock = $this->createMock(OutputInterface::class);

		$mozartCompose = new Compose();

		$result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

		$php_string = file_get_contents($this->testsWorkingDir .'/classmap_directory/afragen/wp-dependency-installer/wp-dependency-installer.php');

		// Confirm problem is gone.
		$this->assertStringNotContainsString('Path Mozart_to plugin or theme', $php_string, 'Text in comment still prefixed.');

		// Confirm solution is correct.
		$this->assertStringContainsString('Mozart_WP_Dependency_Installer', $php_string, 'Class name not properly prefixed.');
	}



	/**
	 * Issue #106, multiple classmap prefixing.
	 *
	 * @see https://github.com/coenjacobs/mozart/issues/106
	 */
	public function test_only_prefix_classmap_classes_once()
	{

		$composer = $this->composer;

		$composer->require["symfony/polyfill-intl-idn"] = "1.22.0";
		$composer->require["symfony/polyfill-intl-normalizer"] = "1.22.0";

		$composer_json_string = json_encode($composer);

		file_put_contents($this->testsWorkingDir . '/composer.json', $composer_json_string);

		chdir($this->testsWorkingDir);

		exec('composer install');

		$inputInterfaceMock = $this->createMock(InputInterface::class);
		$outputInterfaceMock = $this->createMock(OutputInterface::class);

		$mozartCompose = new Compose();

		$result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

		$php_string = file_get_contents($this->testsWorkingDir .'/classmap_directory/symfony/polyfill-intl-normalizer/Resources/stubs/Normalizer.php');

		// Confirm problem is gone.
		$this->assertStringNotContainsString('class Mozart_Mozart_Normalizer extends', $php_string, 'Double prefixing problem still present.');

		// Confirm solution is correct.
		$this->assertStringContainsString('class Mozart_Normalizer extends', $php_string, 'Class name not properly prefixed.');
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
