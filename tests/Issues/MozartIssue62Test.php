<?php
/**
 * AWS not working after Mozart has been ran
 * @see https://github.com/coenjacobs/mozart/issues/62
 *
 * Possibly down to multiple autoload directories in one autoload key. Mozart was only reading the second key from
 * ```
 * "autoload": {
 *  "psr-0": {
 *      "Guzzle": "src/",
 *      "Guzzle\\Tests": "tests/"
 *   }
 * }
 * ```
 * (although arguably, it shouldn't read the second at all).
 *
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MozartIssue62Test extends IntegrationTestCase
{

    /**
     * Just confirms `use Guzzle\Common\Collection;` is prefixed.
     */
    public function testGuzzleNamespaceIsPrefixedInS3Client()
    {
        $this->markTestSkipped('Very slow to run');

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/mozart-issue-62",
  "require": {
    "aws/aws-sdk-php": "2.8.*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "Strauss\\",
      "target_directory": "/strauss/"
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

        $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $phpString = file_get_contents($this->testsWorkingDir .'strauss/aws/aws-sdk-php/src/Aws/S3/S3Client.php');

        $this->assertStringContainsString('use Strauss\\Guzzle\\Common\\Collection;', $phpString);
    }
}
