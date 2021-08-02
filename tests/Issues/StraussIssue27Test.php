<?php
/**
 * Problem with too many replacements due to common class, domain, namespace names, "Normalizer".
 *
 * @see https://github.com/BrianHenryIE/strauss/issues/27
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package BrianHenryIE\Strauss\Tests\Issues
 * @coversNothing
 */
class StraussIssue27Test extends \BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase
{

    /**
     */
    public function test_virtual_package()
    {

        $composerJsonString = <<<'EOD'
{
  "require": {
    "symfony/polyfill-intl-normalizer": "1.23"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "Normalizer_Test\\",
      "classmap_prefix": "Normalizer_Test_"
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

        $php_string = file_get_contents($this->testsWorkingDir . 'strauss/symfony/polyfill-intl-normalizer/Normalizer.php');

        $this->assertStringNotContainsString('namespace Normalizer_Test\Symfony\Polyfill\Intl\Normalizer_Test_Normalizer;', $php_string);
        $this->assertStringContainsString('namespace Normalizer_Test\Symfony\Polyfill\Intl\Normalizer;', $php_string);

        $this->assertStringNotContainsString('class Normalizer_Test_Normalizer', $php_string);
        $this->assertStringContainsString('class Normalizer', $php_string);


        $php_string = file_get_contents($this->testsWorkingDir . 'strauss/symfony/polyfill-intl-normalizer/Resources/stubs/Normalizer.php');

        $this->assertStringNotContainsString('class Normalizer_Test_Normalizer extends Normalizer_Test\Symfony\Polyfill\Intl\Normalizer_Test_Normalizer\Normalizer', $php_string);
        $this->assertStringContainsString('class Normalizer_Test_Normalizer extends Normalizer_Test\Symfony\Polyfill\Intl\Normalizer\Normalizer', $php_string);
    }
}
