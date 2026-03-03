<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Console\Commands;

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
     * Before each test ensure the current working directory is this one. Record
     * the previous PHPUnit cwd to restore after.
     */
    public function setUp(): void
    {
        parent::setUp();

        chdir(dirname(__FILE__));
    }

    /**
     * When composer.json is absent, instead of failing with: "failed to open
     * stream: No such file or directory" a better message should be written to
     * the OutputInterface.
     *
     * @test
     */
    public function it_fails_gracefully_when_composer_json_absent(): void
    {
        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->exactly(1))
             ->method('writeln');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When json_decode fails, instead of "Trying to get property 'extra' of
     * non-object" a better message should be written to the OutputInterface.
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
                            ->method('writeln');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When composer.json->extra is absent, zero-config resolves settings
     * from the package name. The command proceeds but may fail during
     * execution (e.g. no vendor directory). The error is written to
     * OutputInterface, not thrown.
     *
     * @test
     */
    public function it_handles_absent_extra_config_with_grace(): void
    {
        $badComposerJson = '{ "name": "coenjacobs/mozart" }';

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->atLeastOnce())
                            ->method('writeln');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }


    /**
     * When composer.json->extra is not an object, instead of "Trying to get
     * property 'mozart' of non-object" a better message should be written to
     * the OutputInterface.
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
                            ->method('writeln');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When composer.json->extra->mozart is absent (typo in key name),
     * zero-config resolves settings from the package name. The command
     * proceeds but may fail during execution.
     *
     * @test
     */
    public function it_handles_absent_mozart_config_with_grace(): void
    {
        $badComposerJson = '{ "name": "coenjacobs/mozart", "extra": { "moozart": {} } }';

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->atLeastOnce())
                            ->method('writeln');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
            public function __construct($inputInterfaceMock, $outputInterfaceMock)
            {
                parent::__construct();

                $this->execute($inputInterfaceMock, $outputInterfaceMock);
            }
        };
    }

    /**
     * When composer.json->extra->mozart is an empty array, JsonMapper maps
     * it to a Mozart object with default values. Zero-config then resolves
     * settings from the package name.
     *
     * @test
     */
    public function it_handles_malformed_mozart_config__with_grace(): void
    {
        $badComposerJson = '{ "name": "coenjacobs/mozart", "extra": { "mozart": [] } }';

        file_put_contents(__DIR__ . '/composer.json', $badComposerJson);

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $outputInterfaceMock->expects($this->atLeastOnce())
                            ->method('writeln');

        new class( $inputInterfaceMock, $outputInterfaceMock ) extends Compose {
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

        // Clean up any output directories created by a partial compose run
        // (e.g. the autoloader generator may create vendor-prefixed/ before
        // an error aborts execution).
        $vendorPrefixed = __DIR__ . '/vendor-prefixed';
        if (is_dir($vendorPrefixed)) {
            $this->removeDirectory($vendorPrefixed);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        chdir(self::$cwd);
    }
}
