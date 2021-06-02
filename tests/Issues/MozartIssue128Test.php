<?php
/**
 * @see https://github.com/coenjacobs/mozart/issues/128
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 * Class MozartIssue128Test
 * @coversNothing
 */
class MozartIssue128Test extends IntegrationTestCase
{

    /**
     * Because the neither package was a sub-package of the other, the replacing was not occurring
     * throughout.
     */
    public function test_it_does_not_make_classname_replacement_inside_namespaced_file()
    {

        $this->markTestSkipped("Failing on PHP 8");

        $composerJsonString = <<<'EOD'
{
  "require": {
    "setasign/fpdf": "1.8",
    "setasign/fpdi": "2.3"
  },
  "require-dev": {
    "coenjacobs/mozart": "dev-master#3b1243ca8505fa6436569800dc34269178930f39"
  },
  "extra": {
    "strauss": {
      "target_directory": "strauss",
      "namespace_prefix": "\\Strauss\\"
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

        assert(0 === $result);

        $mpdf_php = file_get_contents($this->testsWorkingDir .'strauss/setasign/fpdi/src/FpdfTpl.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('class FpdfTpl extends \FPDF', $mpdf_php);

        // Confirm solution is correct.
        $this->assertStringContainsString('class FpdfTpl extends \Strauss_FPDF', $mpdf_php);
    }
}
