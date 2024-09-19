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
}
