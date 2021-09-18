<?php
/**
 *
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Console\Commands\Compose;
use BrianHenryIE\Strauss\Prefixer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package BrianHenryIE\Strauss\Tests\Issues
 * @coversNothing
 */
class StraussIssue33Test extends \BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase
{

    /**
     */
    public function test_backtrack_limit_exhausted()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss-backtrack-limit-exhausted",
  "minimum-stability": "dev",
  "require": {
    "afragen/wp-dependency-installer": "^3.1",
    "mpdf/mpdf": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss_Backtrack_Limit_Exhausted\\",
      "target_directory": "/strauss/",
      "classmap_prefix": "BH_Strauss_Backtrack_Limit_Exhausted_"
    }
  }
}

EOD;

        file_put_contents($this->testsWorkingDir . 'composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $strauss = new Compose();

        $result = $strauss->run($inputInterfaceMock, $outputInterfaceMock);

        $this->assertNotEquals(1, $result);
    }



    /**
     *
     */
    public function test_unit_backtrack_limit_exhausted()
    {

        $contents = file_get_contents(__DIR__.'/data/Mpdf.php');

        $originalClassname = 'WP_Dependency_Installer';

        $classnamePrefix = 'BH_Strauss_Backtrack_Limit_Exhausted_';

        $config = $this->createMock(StraussConfig::class);

        $exception = null;

        $prefixer = new Prefixer($config, $this->testsWorkingDir);

        try {
            $prefixer->replaceClassname($contents, $originalClassname, $classnamePrefix);
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->assertNull($exception);
    }
}
