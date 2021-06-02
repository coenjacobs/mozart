<?php
/**
 * nesbot/carbon empty searchNamespace
 * @see https://github.com/coenjacobs/mozart/issues/109
 *
 * Comments were being prefixed.
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MozartIssue109Test
 * @package BrianHenryIE\Strauss\Tests\Issues
 * @coversNothing
 */
class MozartIssue109Test extends IntegrationTestCase
{

    public function testTheOutputDoesNotPrefixComments()
    {

        $composerJsonString = <<<'EOD'
{
  "minimum-stability": "dev",
  "require": {
    "nesbot/carbon":"1.39.0"
  },
  "require-dev": {
    "coenjacobs/mozart": "dev-master"
  },
  "extra": {
    "mozart": {
      "dep_namespace": "Mozart\\",
      "dep_directory": "/strauss/",
      "delete_vendor_files": false,
      "exclude_packages": [
        "kylekatarnls/update-helper",
        "symfony/polyfill-intl-idn",
        "symfony/translation",
        "symfony/polyfill-mbstring",
        "symfony/translation-contracts",
        "composer-plugin-api"
      ]
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

        $phpString = file_get_contents($this->testsWorkingDir .'strauss/nesbot/carbon/src/Carbon/Carbon.php');

        $this->assertStringNotContainsString('*Mozart\\ This file is part of the Carbon package.Mozart\\', $phpString);
    }
}
