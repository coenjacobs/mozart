<?php
/**
 * @see https://github.com/BrianHenryIE/strauss/issues/14
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
class StraussIssue14Test extends \BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase
{

    /**
     * Looks like the exclude_from_prefix regex for psr is not specific enough.
     *
     * @author BrianHenryIE
     */
    public function test_guzzle_http_is_prefixed()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss-issue-14",
  "require":{
    "guzzlehttp/psr7": "*"
  },
  "require-dev":{
    "brianhenryie/strauss": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss\\"
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

        $php_string = file_get_contents($this->testsWorkingDir .'/strauss/guzzlehttp/psr7/src/AppendStream.php');

        // was namespace GuzzleHttp\Psr7;

        // Confirm solution is correct.
        $this->assertStringContainsString('namespace BrianHenryIE\Strauss\GuzzleHttp\Psr7;', $php_string);


    }
}
