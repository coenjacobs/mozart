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
 * Class MozartIssue106Test
 * @coversNothing
 */
class MozartIssue106Test extends IntegrationTestCase
{

    /**
     * Issue #106, multiple classmap prefixing.
     *
     * @see https://github.com/coenjacobs/mozart/issues/106
     */
    public function test_only_prefix_classmap_classes_once()
    {

        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-106",
	"require": {
		"symfony/polyfill-intl-idn":  "1.22.0",
        "symfony/polyfill-intl-normalizer": "1.22.0"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_"
		}
	}
}
EOD;

        file_put_contents($this->testsWorkingDir . 'composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $php_string = file_get_contents($this->testsWorkingDir .'strauss/symfony/polyfill-intl-normalizer/Resources/stubs/Normalizer.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('class BrianHenryIE_Strauss_BrianHenryIE_Strauss_Normalizer extends', $php_string, 'Double prefixing problem still present.');

        // Confirm solution is correct.
        $this->assertStringContainsString('class BrianHenryIE_Strauss_Normalizer extends', $php_string, 'Class name not properly prefixed.');
    }
}
