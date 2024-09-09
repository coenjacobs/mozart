<?php

declare(strict_types=1);

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Config\PackageFactory;
use PHPUnit\Framework\TestCase;

class ConfigMapperTest extends TestCase
{
    /**
     * @test
     */
    #[Test]
    public function it_creates_a_valid_config_object_based_on_composer_file()
    {
        $package = PackageFactory::createPackage(__DIR__ . '/config-mapper-test.json');
        $this->assertInstanceOf(Package::class, $package);
        $this->assertInstanceOf(Mozart::class, $package->getExtra()->getMozart());
        $this->assertCount(4, $package->autoload->getAutoloaders());
    }
}
