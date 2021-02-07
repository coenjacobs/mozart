<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase {

	/** @test */
	public function happy_path(): void {

		$file = __DIR__ . '/ConfigTest/composer-1.json';

		$config = \CoenJacobs\Mozart\Composer\MozartConfig::loadFromFile( $file );

		$this->assertEquals( '/classmap_directory/', $config->getClassmapDirectory());

		$this->assertEquals( '/dep_directory/', $config->getDepDirectory() );

		$this->assertEquals( 'My_Mozart_Plugin_', $config->getClassmapPrefix() );

	}

}