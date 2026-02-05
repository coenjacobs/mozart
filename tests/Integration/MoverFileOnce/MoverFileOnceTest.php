<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\MoverFileOnce;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class MoverFileOnceTest extends IntegrationTestCase
{
    /**
     * If a file is specified more than once in an autoloader, e.g. is
     * explicitly listed and is also in a folder listed, a "File already exists
     * at path" error occurs.
     *
     * To fix this, we list the files being moved/copied by their absolute path
     * resulting in only copying each file only once.
     *
     * Original error:
     * "League\Flysystem\FileExistsException : File already exists at path:
     * lib/classes/tecnickcom/tcpdf/tcpdf.php"
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
     */
    #[Test]
    public function it_moves_each_file_once_per_namespace(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        // On the failing test, an exception was thrown and this line was not reached.
        $this->assertEquals(0, $result);
    }
}
