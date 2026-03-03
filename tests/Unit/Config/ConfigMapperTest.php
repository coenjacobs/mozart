<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Config;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\OverrideAutoload;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\PackageFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigMapperTest extends TestCase
{
    /** @test */
    public function it_creates_a_valid_config_object_based_on_composer_file(): void
    {
        $finder = new PackageFinder();
        $factory = new PackageFactory();
        $package = $factory->createPackage(__DIR__ . '/config-mapper-test.json');
        $package->loadDependencies($finder);
        $this->assertInstanceOf(Package::class, $package);
        $this->assertInstanceOf(Mozart::class, $package->getExtra()->getMozart());
		$this->assertInstanceOf(OverrideAutoload::class, $package->getExtra()->getMozart()->getOverrideAutoload());
        $this->assertCount(4, $package->autoload->getAutoloaders());
    }

    /** @test */
    public function it_creates_a_valid_config_object_based_on_composer_file_without_override(): void
    {
        $finder = new PackageFinder();
        $factory = new PackageFactory();
        $package = $factory->createPackage(__DIR__ . '/config-mapper-no-override-test.json');
        $package->loadDependencies($finder);
        $this->assertInstanceOf(Package::class, $package);
        $this->assertInstanceOf(Mozart::class, $package->getExtra()->getMozart());
        $this->assertInstanceOf(OverrideAutoload::class, $package->getExtra()->getMozart()->getOverrideAutoload());
        $this->assertCount(4, $package->autoload->getAutoloaders());
    }

    #[Test]
    public function it_maps_generate_autoloader_from_json_config(): void
    {
        $config = new Mozart();
        $result = $config->loadFromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
            'generate_autoloader' => true,
        ]));

        $this->assertTrue($result->getGenerateAutoloader());
    }

    #[Test]
    public function it_defaults_generate_autoloader_to_false(): void
    {
        $config = new Mozart();
        $result = $config->loadFromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
        ]));

        $this->assertFalse($result->getGenerateAutoloader());
    }
}
