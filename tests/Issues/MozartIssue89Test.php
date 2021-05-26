<?php
/**
 * @see https://github.com/coenjacobs/mozart/issues/89
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MozartIssue89Test
 * @package BrianHenryIE\Strauss\Tests\Issues
 * @coversNothing
 */
class MozartIssue89Test extends IntegrationTestCase
{

    /**
     * If a file is specified more than once in an autoloader, e.g. is explicitly listed and is also in a folder listed,
     * a "File already exists at path" error occurs.
     *
     * To fix this, we enumerate the files to be copied using a dictionary indexed with the source file path, then loop
     * and copy, thus only copying each one once.
     *
     * Original error:
     * "League\Flysystem\FileExistsException : File already exists at path: lib/classes/tecnickcom/tcpdf/tcpdf.php"
     *
     * Test is using a known problematic autoloader:
     * "iio/libmergepdf": {
     *   "classmap": [
     *     "config",
     *     "include",
     *     "tcpdf.php",
     *     "tcpdf_parser.php",
     *     "tcpdf_import.php",
     *     "tcpdf_barcodes_1d.php",
     *     "tcpdf_barcodes_2d.php",
     *     "include/tcpdf_colors.php",
     *     "include/tcpdf_filters.php",
     *     "include/tcpdf_font_data.php",
     *     "include/tcpdf_fonts.php",
     *     "include/tcpdf_images.php",
     *     "include/tcpdf_static.php",
     *     "include/barcodes/datamatrix.php",
     *     "include/barcodes/pdf417.php",
     *     "include/barcodes/qrcode.php"
     *    ]
     *  }
     *
     * @see https://github.com/coenjacobs/mozart/issues/89
     *
     * @test
     */
    public function it_moves_each_file_once_per_namespace()
    {

        if (version_compare(phpversion(), '8.0.0', '>=')) {
            $this->markTestSkipped("Package specified for test is not PHP 8 compatible");
        }

        $composerJsonString = <<<'EOD'
{
	"name": "brianhenryie/mozart-issue-89",
	"require": {
		"iio/libmergepdf": "4.0"
	},
	"extra": {
		"strauss": {
			"namespace_prefix": "BrianHenryIE\\Strauss\\",
			"classmap_prefix": "BrianHenryIE_Strauss_"
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

        // $this->expectException(League\Flysystem\FileExistsException::class);

        $exception = null;

        try {
            $result = $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);
        } catch (\League\Flysystem\FileExistsException $e) {
            $exception  = $e;
        }

        // On the failing test, an exception was thrown and this line was not reached.
        $this->assertEquals(0, $result);

        $this->assertNull($exception);
    }
}
