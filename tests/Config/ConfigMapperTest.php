<?php

declare(strict_types=1);

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\PackageFinder;
use PHPUnit\Framework\TestCase;

class ConfigMapperTest extends TestCase
{
    /**
     * @test
     */
    #[Test]
    public function it_creates_a_valid_config_object_based_on_composer_file()
    {
        $finder = new PackageFinder();
        $factory = new PackageFactory();
        $package = $factory->createPackage(__DIR__ . '/config-mapper-test.json');
        $package->loadDependencies($finder);
        $this->assertInstanceOf(Package::class, $package);
        $this->assertInstanceOf(Mozart::class, $package->getExtra()->getMozart());
        $this->assertCount(4, $package->autoload->getAutoloaders());
    }

    /**
     * @test
     */
    public function it_respects_explicit_delete_vendor_directories_false(): void
    {
        $config = new Mozart();
        $result = $config->loadFromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
            'delete_vendor_directories' => false,
        ]));

        $this->assertFalse($result->getDeleteVendorDirectories());
    }

    /**
     * @test
     */
    public function it_defaults_delete_vendor_directories_to_true(): void
    {
        $config = new Mozart();
        $result = $config->loadFromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
        ]));

        $this->assertTrue($result->getDeleteVendorDirectories());
    }
}
