<?php
/**
 * @see https://github.com/coenjacobs/mozart/blob/3b1243ca8505fa6436569800dc34269178930f39/tests/replacers/ClassmapReplacerIntegrationTest.php#L67-L109
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 * Class MozartIssue93Test
 * @coversNothing
 */
class MozartIssue93Test extends IntegrationTestCase
{
    /**
     * Issue #93 shows a classname being updated inside a class whose namespace has also been updated
     * by Mozart.
     *
     * This is caused by the same files being loaded by both a PSR-4 autolaoder and classmap autoloader.
     * @see https://github.com/katzgrau/KLogger/blob/de2d3ab6777a393a9879e0496ebb8e0644066e3f/composer.json#L24-L29
     *
     * @author BrianHenryIE
     */
    public function test_it_does_not_make_classname_replacement_inside_namespaced_file()
    {

        $this->markTestSkipped('Not respecting the pinned commit.');

        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-93",
	"repositories": [{
		"url": "https://github.com/BrianHenryIE/bh-wp-logger",
		"type": "git"
	}],
	"require": {
		"brianhenryie/wp-logger": "dev-master#dd2bb0665e01e11b282178e76a2334198d3860c5"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_"
		}
	},
	"minimum-stability": "dev"
}
EOD;

        file_put_contents($this->testsWorkingDir . 'composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $php_string = file_get_contents($this->testsWorkingDir .'strauss/brianhenryie/wp-logger/src/class-logger.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('class BrianHenryIE_Strauss_Logger extends', $php_string);

        // Confirm solution is correct.
        $this->assertStringContainsString('class Logger extends', $php_string);
    }
}
