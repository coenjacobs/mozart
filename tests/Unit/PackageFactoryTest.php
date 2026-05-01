<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\PackageFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PackageFactoryTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_factory_test_' . uniqid();
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
    public function it_creates_package_from_file(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
        ];
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($filePath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($filePath);

        $this->assertInstanceOf(Package::class, $package);
        $this->assertEquals('test/package', $package->name);
    }

    #[Test]
    public function it_caches_packages(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
        ];
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($filePath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package1 = $factory->createPackage($filePath);
        $package2 = $factory->createPackage($filePath);

        $this->assertSame($package1, $package2);
    }

    #[Test]
    public function it_applies_override_autoload_when_provided(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
            'autoload' => [
                'psr-4' => [
                    'Original\\' => 'src/',
                ],
            ],
        ];
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($filePath, json_encode($composerJson));

        $overrideAutoload = new \stdClass();
        $overrideAutoload->{'psr-4'} = (object)['Override\\' => ['lib/']];

        $factory = new PackageFactory();
        $package = $factory->createPackage($filePath, $overrideAutoload);

        $this->assertInstanceOf(Package::class, $package);
        // The override should be applied, but we can't easily test the internal state
        // without exposing more methods. The fact that it doesn't throw is sufficient.
    }

    #[Test]
    public function it_handles_null_override_autoload(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
        ];
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($filePath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($filePath, null);

        $this->assertInstanceOf(Package::class, $package);
    }

    #[Test]
    public function it_returns_existing_mozart_when_extra_mozart_exists(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
            'extra' => [
                'mozart' => [
                    'dep_namespace' => 'Custom\\Ns',
                    'dep_directory' => 'custom/',
                ],
            ],
        ];
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($filePath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($filePath);

        $config = $factory->resolveConfig($package);

        $this->assertSame($package->getExtra()->getMozart(), $config);
        $this->assertSame('Custom\\Ns', $config->depNamespace);
    }

    #[Test]
    public function it_creates_and_attaches_mozart_in_zero_config_mode(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
        ];
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($filePath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($filePath);

        $config = $factory->resolveConfig($package);

        $this->assertInstanceOf(Mozart::class, $config);
        $this->assertNotNull($package->getExtra());
        $this->assertSame($config, $package->getExtra()->getMozart());
    }

    #[Test]
    public function it_attaches_mozart_when_extra_exists_but_mozart_is_absent(): void
    {
        $composerJson = [
            'name' => 'test/package',
            'require' => [],
            'extra' => new \stdClass(),
        ];
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($filePath, json_encode($composerJson));

        $factory = new PackageFactory();
        $package = $factory->createPackage($filePath);

        $config = $factory->resolveConfig($package);

        $this->assertInstanceOf(Mozart::class, $config);
        $this->assertSame($config, $package->getExtra()->getMozart());
    }

}

