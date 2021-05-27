<?php
/**
 * @see https://github.com/BrianHenryIE/strauss/issues/19
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
class StraussIssue19Test extends \BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase
{

    /**
     * Need to make the class finder in change enumerator stricter.
     *
     * @author BrianHenryIE
     */
    public function testObjectIsNotPrefixed()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss-issue-19",
  "require": {
    "iio/libmergepdf": "^4.0"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "Strauss\\Issue19\\",
      "classmap_prefix": "Strauss_Issue19_"
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

        $php_string = file_get_contents($this->testsWorkingDir . 'strauss/tecnickcom/tcpdf/include/tcpdf_static.php');

        $this->assertStringNotContainsString('* Creates a copy of a class Strauss_Issue19_object', $php_string);
        
        $this->assertStringContainsString('* Creates a copy of a class object', $php_string);
    }
}
