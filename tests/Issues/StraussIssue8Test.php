<?php
/**
 * @see https://github.com/BrianHenryIE/strauss/issues/8
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package BrianHenryIE\Strauss\Tests\Issues
 * @coversNothing
 */
class StraussIssue8Test extends \BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase
{

    /**
     * @author BrianHenryIE
     */
    public function test_delete_vendor_files()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss-issue-8",
  "require": {
    "htmlburger/carbon-fields": "*"
  },
  "extra": {
    "strauss":{
      "delete_vendor_files": true
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

        $this->assertEquals(0, $result);

        $this->assertFileDoesNotExist($this->testsWorkingDir. 'vendor/htmlburger/carbon-fields/core/Carbon_Fields.php');
    }
}
