<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Commands;

use CoenJacobs\Mozart\Commands\Config;
use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_config_test_' . uniqid();
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

    #[Test]
    public function it_resolves_config_with_psr4_autoload(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'autoload' => [
                'psr-4' => [
                    'TestProject\\' => 'src/',
                ],
            ],
            'extra' => [
                'mozart' => new \stdClass(),
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $config = $result['config'];
        $sources = $result['sources'];

        $this->assertSame('TestProject\\Dependencies', $config->depNamespace);
        $this->assertSame('vendor-prefixed/', $config->depDirectory);
        $this->assertSame('vendor-prefixed/', $config->classmapDir);
        $this->assertSame('TestProject_', $config->classmapPrefix);

        $this->assertStringContainsString('inferred from PSR-4', $sources['dep_namespace']);
        $this->assertSame('(default)', $sources['dep_directory']);
        $this->assertStringContainsString('default', $sources['classmap_directory']);
        $this->assertSame('(derived from dep_namespace)', $sources['classmap_prefix']);
        $this->assertSame('(derived from classmap_prefix)', $sources['constant_prefix']);
        $this->assertSame('(derived from classmap_prefix)', $sources['functions_prefix']);
    }

    #[Test]
    public function it_marks_explicit_values_correctly(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'mozart' => [
                    'dep_namespace' => 'Custom\\Ns',
                    'dep_directory' => 'custom/',
                    'classmap_directory' => 'custom-classes/',
                    'classmap_prefix' => 'Custom_',
                ],
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $sources = $result['sources'];

        $this->assertSame('(explicit)', $sources['dep_namespace']);
        $this->assertSame('(explicit)', $sources['dep_directory']);
        $this->assertSame('(explicit)', $sources['classmap_directory']);
        $this->assertSame('(explicit)', $sources['classmap_prefix']);
    }

    #[Test]
    public function it_resolves_zero_config_from_package_name(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'require' => [],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $config = $result['config'];
        $this->assertSame('Test\\Project\\Dependencies', $config->depNamespace);
        $this->assertSame('vendor-prefixed/', $config->depDirectory);

        $sources = $result['sources'];
        $this->assertStringContainsString('inferred from package name', $sources['dep_namespace']);
        $this->assertSame('(default)', $sources['dep_directory']);
    }

    #[Test]
    public function it_marks_explicit_true_booleans_as_explicit(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'mozart' => [
                    'generate_autoloader' => true,
                    'delete_vendor_directories' => true,
                ],
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $sources = $result['sources'];

        $this->assertSame('(explicit)', $sources['generate_autoloader']);
        $this->assertSame('(explicit)', $sources['delete_vendor_directories']);
    }

    #[Test]
    public function it_marks_explicit_false_booleans_as_explicit(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'mozart' => [
                    'generate_autoloader' => false,
                    'delete_vendor_directories' => false,
                ],
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $sources = $result['sources'];

        $this->assertSame('(explicit)', $sources['generate_autoloader']);
        $this->assertSame('(explicit)', $sources['delete_vendor_directories']);
    }

    #[Test]
    public function it_marks_omitted_booleans_as_default(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'mozart' => new \stdClass(),
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $sources = $result['sources'];

        $this->assertSame('(default)', $sources['generate_autoloader']);
        $this->assertSame('(default)', $sources['delete_vendor_directories']);
    }

    #[Test]
    public function it_marks_booleans_as_default_when_no_extra_mozart_block(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'require' => [],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $sources = $result['sources'];

        $this->assertSame('(default)', $sources['generate_autoloader']);
        $this->assertSame('(default)', $sources['delete_vendor_directories']);
    }

    #[Test]
    public function it_marks_derived_constant_prefix(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'autoload' => [
                'psr-4' => [
                    'TestProject\\' => 'src/',
                ],
            ],
            'extra' => [
                'mozart' => new \stdClass(),
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $this->assertSame('TESTPROJECT_', $result['config']->constantPrefix);
        $this->assertSame('(derived from classmap_prefix)', $result['sources']['constant_prefix']);
    }

    #[Test]
    public function it_marks_derived_functions_prefix(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'autoload' => [
                'psr-4' => [
                    'TestProject\\' => 'src/',
                ],
            ],
            'extra' => [
                'mozart' => new \stdClass(),
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $this->assertSame('testproject_', $result['config']->functionsPrefix);
        $this->assertSame('(derived from classmap_prefix)', $result['sources']['functions_prefix']);
    }

    #[Test]
    public function it_marks_explicit_constant_prefix(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'mozart' => [
                    'dep_namespace' => 'Custom\\Ns',
                    'constant_prefix' => 'MY_CUSTOM_',
                ],
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $this->assertSame('MY_CUSTOM_', $result['config']->constantPrefix);
        $this->assertSame('(explicit)', $result['sources']['constant_prefix']);
    }

    #[Test]
    public function it_marks_explicit_functions_prefix(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'mozart' => [
                    'dep_namespace' => 'Custom\\Ns',
                    'functions_prefix' => 'my_custom_',
                ],
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $this->assertSame('my_custom_', $result['config']->functionsPrefix);
        $this->assertSame('(explicit)', $result['sources']['functions_prefix']);
    }

    #[Test]
    public function it_infers_namespace_source_from_package_name(): void
    {
        $composerJson = [
            'name' => 'my-vendor/my-plugin',
            'extra' => [
                'mozart' => new \stdClass(),
            ],
        ];

        file_put_contents(
            $this->testDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($composerJson)
        );

        $command = new Config($this->testDir);
        $result = $command->execute();

        $sources = $result['sources'];
        $this->assertStringContainsString('inferred from package name', $sources['dep_namespace']);
    }
}
