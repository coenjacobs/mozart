<?php
/**
 * @see https://github.com/coenjacobs/mozart/blob/3b1243ca8505fa6436569800dc34269178930f39/tests/replacers/ClassmapReplacerIntegrationTest.php
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 *
 * Class NamespaceReplacerIntegrationTest
 * @coversNothing
 */
class MozartIssue86Test extends IntegrationTestCase
{

    /**
     * Issue #86 – "class as" appeared in a comment and later the keyword as was prefixed!
     *
     * Solved by https://github.com/ziodave
     */
    public function test_do_not_parse_comments_to_classnames()
    {

        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-86",
	"require": {
		"pear/pear-core-minimal": "v1.10.10"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_",
			"override_autoload": {
				"pear/pear-core-minimal": {
					"classmap": [
						"src/"
					]
				},
				"pear/console_getopt": {}
			}
		}
	}
}
EOD;

        file_put_contents($this->testsWorkingDir . '/composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $php_string = file_get_contents($this->testsWorkingDir .'/strauss/pear/pear_exception/PEAR/Exception.php');

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

        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-86-2",
	"require": {
		"afragen/wp-dependency-installer": "3.1"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_"
		}
	}
}
EOD;

        file_put_contents($this->testsWorkingDir . '/composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $php_string = file_get_contents($this->testsWorkingDir .'/strauss/afragen/wp-dependency-installer/wp-dependency-installer.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('Path BrianHenryIE_Strauss_to plugin or theme', $php_string, 'Text in comment still prefixed.');

        // Confirm solution is correct.
        $this->assertStringContainsString('BrianHenryIE_Strauss_WP_Dependency_Installer', $php_string, 'Class name not properly prefixed.');
    }
}
