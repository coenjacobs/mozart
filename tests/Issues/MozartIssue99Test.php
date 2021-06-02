<?php
/**
 * A PSR-0 test.
 *
 * This worked very easily because once the files are copied, Strauss doesn't care about autoloaders, just if you
 * are a class in a global namespace or if its a namespace that should br prefixed.
 *
 * @see https://github.com/coenjacobs/mozart/issues/99
 *
 * @see https://github.com/sdrobov/autopsr4
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 * Class MozartIssue99Test
 * @coversNothing
 */
class MozartIssue99Test extends IntegrationTestCase
{

    /**
     * WooCommerce Action Scheduler ... has no autoload key. But also needs some Mozart patches to work correctly.
     */
    public function test_it_does_not_make_classname_replacement_inside_namespaced_file()
    {

        $composerJsonString = <<<'EOD'
{
  "require": {
    "mustache/mustache": "2.13.0"
  },
  "extra": {
    "strauss": {
      "target_directory": "strauss",
      "namespace_prefix": "Strauss\\",
      "classmap_prefix": "Strauss_"
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

        $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $this->markTestIncomplete("What to assert!?");
    }
}
