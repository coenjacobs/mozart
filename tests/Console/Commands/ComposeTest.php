<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Console\Commands\Compose;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComposeTest extends TestCase
{
    static $cwd;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$cwd = getcwd();
    }

    /**
     * Before each test ensure the current working directory is this one.
     *
     * Record the previous PHPUnit cwd to restore after.
     */
    public function setUp(): void
    {
        parent::setUp();

        chdir(dirname(__FILE__));
    }

    /**
     * When composer.json is absent, instead of failing with:
     * "failed to open stream: No such file or directory"
     * a better message should be written to the OutputInterface.
     *
     * @test
     */
    public function it_fails_gracefully_when_composer_json_absent(): void
    {

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
             ->method('write');

        $compose = new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
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

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        $compose = new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
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
     * @test
     */
    public function it_handles_absent_extra_config_with_grace(): void
    {

        $badComposerJson = '{ "name": "coenjacobs/mozart" }';

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        $compose = new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
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

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        $compose = new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
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

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        $compose = new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
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

        $badComposerJson = '{ "name": "coenjacobs/mozart", "extra": { "mozart": [] } }';

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
                            ->method('write');

        $compose = new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $composer_json = __DIR__ . '/composer.json';
        if (file_exists($composer_json)) {
            unlink($composer_json);
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        chdir(self::$cwd);
    }
}
