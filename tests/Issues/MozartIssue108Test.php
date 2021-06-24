<?php
/**
 * This is interesting for two reasons.
 *
 * 1. The published package on packagist does not contain a composer.json, so I'm using dev-master here, which
 * for regular woocommerce/woocommerce needs build steps before it's valid, so this test should be consider
 * incomplete.
 *
 * 2. Action Scheduler are using an un-namespaced version of mtdowling/cron-expression in
 * their lib/ folder, whereas I would prefer to place it in the composer/require, but then the PHP's references
 * would be incorrect
 *
 * @see https://github.com/coenjacobs/mozart/issues/108
 */

namespace BrianHenryIE\Strauss\Tests\Issues;

use BrianHenryIE\Strauss\Console\Commands\Compose;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;

/**
 * Class MozartIssue108Test
 * @coversNothing
 */
class MozartIssue108Test extends IntegrationTestCase
{

    /**
     * WooCommerce Action Scheduler ... has no autoload key. But also needs some Mozart patches to work correctly.
     */
    public function test_it_does_not_make_classname_replacement_inside_namespaced_file()
    {

        $composerJsonString = <<<'EOD'
{
  "require": {
    "woocommerce/action-scheduler": "3.1.6",
    "deliciousbrains/wp-background-processing": "1.0.2"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "Strauss",
      "override_autoload": {
        "woocommerce/action-scheduler": {
            "classmap": [
                "classes/",
                "deprecated/",
                "lib/cron-expression/"
            ]
        }
      }
    }
  }
}
EOD;

        file_put_contents($this->testsWorkingDir . 'composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        // The file we're going to move and check.
        assert(file_exists($this->testsWorkingDir . 'vendor/deliciousbrains/wp-background-processing/classes/wp-async-request.php'));

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $mozartCompose = new Compose();

        $mozartCompose->run($inputInterfaceMock, $outputInterfaceMock);

        $php_contents = file_get_contents($this->testsWorkingDir .'strauss/deliciousbrains/wp-background-processing/classes/wp-async-request.php');
        $this->assertStringContainsString('abstract class Strauss_WP_Async_Request', $php_contents);

//        $pdf_contents = file_get_contents($this->testsWorkingDir .'strauss/mtdowling/cron-expression/src/Cron/CronExpression.php');
//        $this->assertStringContainsString('namespace Strauss\\CronExpression', $pdf_contents);

        $php_contents = file_get_contents($this->testsWorkingDir .'strauss/woocommerce/action-scheduler/lib/cron-expression/CronExpression.php');
        $this->assertStringContainsString('class Strauss_CronExpression', $php_contents);

        $php_contents = file_get_contents($this->testsWorkingDir .'strauss/woocommerce/action-scheduler/classes/schedules/ActionScheduler_CronSchedule.php');
        $this->assertStringContainsString('if ( ! is_a( $recurrence, \'Strauss_CronExpression\' ) ) {', $php_contents);
    }
}
