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

		$this->assertEquals( "pimple/pimple", $config->getPackages()[0] );
	}


	/**
	 * When the packages key is empty, the base composer.json's require key should be used.
	 *
	 * @test
	 */
	public function default_packages(): void {

		$file = __DIR__ . '/ConfigTest/composer-2.json';

		$config = \CoenJacobs\Mozart\Composer\MozartConfig::loadFromFile( $file );

		$jsonConfig = json_decode( file_get_contents($file ));

		assert( ! isset( $jsonConfig->extra->mozart->packages ) );

		assert( isset( $jsonConfig->require ) );

		$this->assertEquals( "pimple/pimple", $config->getPackages()[0] );
	}

}