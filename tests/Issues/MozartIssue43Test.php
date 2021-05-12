<?php
/**
 * Root directories can not be deleted
 * @see https://github.com/coenjacobs/mozart/issues/43
 *
 * "File already exists at path: strauss/symfony/event-dispatcher/Tests/EventTest.php"
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MozartIssue43Test
 * @package BrianHenryIE\Strauss\Tests\Issues
 * @coversNothing
 */
class MozartIssue43Test extends IntegrationTestCase
{

    /**
     * Issue 43. Needs "aws/aws-sdk-php".
     *
     * League\Flysystem\FileExistsException : File already exists at path:
     * dep_directory/vendor/guzzle/guzzle/src/Guzzle/Cache/Zf1CacheAdapter.php
     */
    public function testAwsSdkSucceeds()
    {
        $this->markTestSkipped('Very slow to run');

        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-43",
	"require": {
		"aws/aws-sdk-php": "2.8.31"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_",
			"override_autoload": {
				"guzzle/guzzle": {
					"psr-4": {
						"Guzzle": "src/"
					}
				}
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

        $strauss = new Compose();

        $result = $strauss->run($inputInterfaceMock, $outputInterfaceMock);
//
//        $this->assertEquals(0, $result);

        $this->assertFileExists($this->testsWorkingDir . '/strauss/aws/aws-sdk-php/src/AWS/Common/Aws.php');
    }
}
