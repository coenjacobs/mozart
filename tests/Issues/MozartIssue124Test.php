<?php
/**
 *
 * @see https://github.com/coenjacobs/mozart/blob/3b1243ca8505fa6436569800dc34269178930f39/tests/replacers/NamespaceReplacerIntegrationTest.php
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 * Class MozartIssue124Test
 * @coversNothing
 */
class MozartIssue124Test extends IntegrationTestCase
{

    /**
     * After PR #84, running Mozart on Mpdf began prefixing the class name inside the namespaced file.
     *
     * The problem coming from the filename matching the namespace name?
     *
     * dev-master#5d8041fdefc94ff57edcbe83ab468a9988c4fc11
     *
     * @see https://github.com/coenjacobs/mozart/pull/84/files
     *
     * Should be: "class Mpdf implements" because its namespace has already been prefixed.
     */
    public function test_it_does_not_make_classname_replacement_inside_namespaced_file()
    {

        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-124",
	"require": {
		"mpdf/mpdf": "8.0.10"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_"
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

        $mpdf_php = file_get_contents($this->testsWorkingDir .'strauss/mpdf/mpdf/src/Mpdf.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('class BrianHenryIE\Strauss\Mpdf implements', $mpdf_php);

        // Confirm solution is correct.
        $this->assertStringContainsString('class Mpdf implements', $mpdf_php);
    }


    /**
     * Another Mpdf problem, presumably down to the classname matching the namespace.
     *
     * Typed function properties whose class type (name) match the namespace being replaced are
     * unintentionally prefixed, causing PHP to crash.
     *
     * Occurring at dev-master#3b1243ca8505fa6436569800dc34269178930f39
     *
     * @see https://github.com/coenjacobs/mozart/issues/124
     */
    public function test_it_does_not_prefix_function_argument_types_whose_classname_matches_the_namespace()
    {


        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-124",
	"require": {
		"mpdf/mpdf": "8.0.10"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_"
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

        $mpdf_php = file_get_contents($this->testsWorkingDir .'strauss/mpdf/mpdf/src/Conversion/DecToOther.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('public function __construct(BrianHenryIE\Strauss\Mpdf $mpdf)', $mpdf_php);

        // Confirm solution is correct.
        $this->assertStringContainsString('public function __construct(Mpdf $mpdf)', $mpdf_php);
    }
    // src/strauss/mpdf/mpdf/src/Barcode/BarcodeException.php



    /**
     * Another Mpdf problem, presumably down to the classname matching the namespace.
     *
     *  @see mpdf/mpdf/src/Barcode/BarcodeException.php
     */
    public function testItDoesPrefixNamespacedExtends()
    {


        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-124",
	"require": {
		"mpdf/mpdf": "8.0.10"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_"
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

        $mpdf_php = file_get_contents($this->testsWorkingDir .'strauss/mpdf/mpdf/src/Barcode/BarcodeException.php');

        // Confirm problem is gone.
        $this->assertStringNotContainsString('class BarcodeException extends \Mpdf\MpdfException', $mpdf_php);

        // Confirm solution is correct.
        $this->assertStringContainsString('class BarcodeException extends \BrianHenryIE\Strauss\Mpdf\MpdfException', $mpdf_php);
    }
}
