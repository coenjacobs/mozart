<?php

declare(strict_types=1);

use CoenJacobs\Mozart\Config\Composer;
use CoenJacobs\Mozart\Config\Mozart;
use PHPUnit\Framework\TestCase;

class ConfigMapperTest extends TestCase
{
    /**
     * @test
     */
    #[Test]
    public function it_creates_a_valid_config_object_based_on_composer_file()
    {
        $config = Composer::loadFromFile(__DIR__ . '/config-mapper-test.json');
        $this->assertInstanceOf(Composer::class, $config);
        $this->assertInstanceOf(Mozart::class, $config->getExtra()->getMozart());
        $this->assertCount(4, $config->autoload->getAutoloaders());
    }
}
