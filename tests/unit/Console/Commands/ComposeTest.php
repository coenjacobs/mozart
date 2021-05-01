<?php
declare(strict_types=1);

namespace BrianHenryIE\Strauss\Tests\Unit\Console\Commands;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComposeTest extends TestCase
{

    /**
     * When composer.json is absent, instead of failing with:
     * "failed to open stream: No such file or directory"
     * a better message should be written to the OutputInterface.
     *
     * @test
     */
    public function it_fails_gracefully_when_composer_json_absent(): void
    {
        chdir(sys_get_temp_dir());

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
             ->method('write');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When json_decode fails, instead of
     * "Trying to get property 'extra' of non-object"
     * a better message should be written to the OutputInterface.
     *
     * @test
     */
    public function it_handles_malformed_json_with_grace(): void
    {

        $badComposerJson = '{ "name": "coenjacobs/mozart", }';

        $tmpfname = tempnam(sys_get_temp_dir(), 'Strauss-' . __CLASS__ . '-' . __FUNCTION__);
        file_put_contents($tmpfname, $badComposerJson);
        chdir(dirname($tmpfname));

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When composer.json->extra is absent, instead of
     * "Undefined property: stdClass::$extra"
     * a better message should be written to the OutputInterface.
     *
     * When package name is not set, `\Composer\Composer::getPackage()->getName()` returns '__root__'.
     *
     */
    public function test_it_handles_absent_extra_config_with_grace(): void
    {

        $badComposerJson = '{ }';

        $tmpfname = tempnam(sys_get_temp_dir(), 'Strauss-' . __CLASS__ . '-' . __FUNCTION__);
        file_put_contents($tmpfname, $badComposerJson);
        chdir(dirname($tmpfname));

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }


    /**
     * When composer.json->extra is not an object, instead of
     * "Trying to get property 'mozart' of non-object"
     * a better message should be written to the OutputInterface.
     *
     * @test
     */
    public function it_handles_malformed_extra_config_with_grace(): void
    {

        $badComposerJson = '{ "name": "coenjacobs/mozart", "extra": [] }';

        $tmpfname = tempnam(sys_get_temp_dir(), 'Strauss-' . __CLASS__ . '-' . __FUNCTION__);
        file_put_contents($tmpfname, $badComposerJson);
        chdir(dirname($tmpfname));

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When composer.json->extra->mozart is absent, instead of
     * "Undefined property: stdClass::$mozart"
     * a better message should be written to the OutputInterface.
     *
     * @test
     */
    public function it_handles_absent_mozart_config_with_grace(): void
    {

        $badComposerJson = '{ "name": "coenjacobs/mozart", "extra": { "moozart": {} } }';

        $tmpfname = tempnam(sys_get_temp_dir(), 'Strauss-' . __CLASS__ . '-' . __FUNCTION__);
        file_put_contents($tmpfname, $badComposerJson);
        chdir(dirname($tmpfname));

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When composer.json->extra->mozart is malformed, instead of
     * "Undefined property: stdClass::$mozart"
     * a better message should be written to the OutputInterface.
     *
     * is_object() added.
     *
     * @test
     */
    public function it_handles_malformed_mozart_config__with_grace(): void
    {

        $badComposerJson = '{ "name": "coenjacobs/mozart", "extra": { "mozart": []  }';

        $tmpfname = tempnam(sys_get_temp_dir(), 'Strauss-' . __CLASS__ . '-' . __FUNCTION__);
        file_put_contents($tmpfname, $badComposerJson);
        chdir(dirname($tmpfname));

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }
}
