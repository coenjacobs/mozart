<?php
/**
 * When users migrate from Mozart, the settings are only preserved when the extra "mozart" key
 * is still used. Let's change it so they're understood not matter what.
 *
 * @see https://github.com/BrianHenryIE/strauss/issues/11
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Console\Commands\Compose;
use Composer\Factory;
use Composer\IO\NullIO;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package BrianHenryIE\Strauss\Tests\Issues
 * @coversNothing
 */
class StraussIssue11Test extends \BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase
{

    /**
     * @author BrianHenryIE
     */
    public function test_migrate_mozart_config()
    {

        $composerExtraStraussJson = <<<'EOD'
{
	"name": "brianhenryie/strauss-issue-8",
	"extra": {
		"mozart": {
			"dep_namespace": "MZoo\\MBO_Sandbox\\Dependencies\\",
			"dep_directory": "/src/Mozart/",
			"packages": [
				"htmlburger/carbon-fields",
				"ericmann/wp-session-manager",
				"ericmann/sessionz"
			],
			"delete_vendor_files": false,
			"override_autoload": {
				"htmlburger/carbon-fields": {
					"psr-4": {
						"Carbon_Fields\\": "core/"
					},
					"files": [
						"config.php",
						"templates",
						"assets",
						"build"
					]
				}
			}

		}
	}
}
EOD;

        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $straussConfig = new StraussConfig($composer);

        $this->assertEquals('src/Mozart/', $straussConfig->getTargetDirectory());

        $this->assertEquals("MZoo\\MBO_Sandbox\\Dependencies", $straussConfig->getNamespacePrefix());
    }

}
