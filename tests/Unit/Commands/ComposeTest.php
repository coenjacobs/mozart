<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Commands;

use CoenJacobs\Mozart\Commands\Compose;
use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use Exception;
use PHPUnit\Framework\TestCase;

class ComposeTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_compose_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** @test */
    public function it_throws_exception_when_composer_json_missing(): void
    {
        $compose = new Compose($this->testDir);

        // PackageFactory will throw an exception when file doesn't exist
        // file_get_contents returns false and triggers the exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not read config from provided file');

        // Suppress the file_get_contents warning
        @$compose->execute();
    }

    /** @test */
    public function it_throws_exception_when_mozart_config_missing(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
        ];
        file_put_contents($this->testDir . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerJson));

        $compose = new Compose($this->testDir);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Mozart config not readable in composer.json at extra->mozart');

        $compose->execute();
    }

    /** @test */
    public function it_throws_exception_when_extra_missing(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
        ];
        file_put_contents($this->testDir . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerJson));

        $compose = new Compose($this->testDir);

        $this->expectException(ConfigurationException::class);

        $compose->execute();
    }

    /** @test */
    public function it_throws_exception_when_mozart_config_empty(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
            'extra' => [
                'mozart' => null,
            ],
        ];
        file_put_contents($this->testDir . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerJson));

        $compose = new Compose($this->testDir);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Mozart config not readable in composer.json at extra->mozart');

        $compose->execute();
    }

    /** @test */
    public function it_accepts_working_directory_in_constructor(): void
    {
        $compose = new Compose($this->testDir);

        // Just verify it doesn't throw on construction
        $this->assertInstanceOf(Compose::class, $compose);
    }
}

