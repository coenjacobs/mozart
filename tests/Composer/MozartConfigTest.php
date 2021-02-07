<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Composer\MozartConfig;
use PHPUnit\Framework\TestCase;

class MozartConfigTest extends TestCase {

	/** @test */
	public function happy_path(): void {

		$file = __DIR__ . '/ConfigTest/composer-1.json';

		$config = MozartConfig::loadFromFile( $file );

		$this->assertEquals( 'classmap_directory/', $config->getClassmapDirectory());

		$this->assertEquals( 'dep_directory/', $config->getDepDirectory() );

		$this->assertEquals( 'My_Mozart_Plugin_', $config->getClassmapPrefix() );

		$this->assertEquals( "pimple/pimple", $config->getPackages()[0] );

		$this->assertEquals( "pimple/pimple", array_keys( $config->getOverrideAutoload() )[0] );
	}


	/**
	 * When the packages key is empty, the base composer.json's require key should be used.
	 *
	 * @test
	 */
	public function default_packages(): void {

		$file = __DIR__ . '/ConfigTest/composer-2.json';

		$config = MozartConfig::loadFromFile( $file );

		$jsonConfig = json_decode( file_get_contents($file ));

		assert( ! isset( $jsonConfig->extra->mozart->packages ) );

		assert( isset( $jsonConfig->require ) );

		$this->assertEquals( "pimple/pimple", $config->getPackages()[0] );
	}


	/**
	 * Should throw an exception when the `extra` key is missing.
	 *
	 * @test
	 */
	public function exception_when_extra_key_is_missing() {

		$this->expectException(Exception::class);

		$file = __DIR__ . '/ConfigTest/composer-3.json';

		MozartConfig::loadFromFile( $file );

	}

	/**
	 * Should throw an exception when the `extra->mozart` key is missing.
	 *
	 * @test
	 */
	public function exception_when_extra_mozart_key_is_missing() {

		$this->expectException(Exception::class);

		$file = __DIR__ . '/ConfigTest/composer-4.json';

		MozartConfig::loadFromFile( $file );

	}


	/**
	 * Should throw an exception when composer.json does not exist
	 *
	 * @test
	 */
	public function exception_when_composerjson_is_missing() {

		$this->expectException(Exception::class);

		$file = __DIR__ . '/ConfigTest/composer-missing.json';

		MozartConfig::loadFromFile( $file );

	}


	/**
	 * Should throw an exception when composer.json is not correct json
	 *
	 * @test
	 */
	public function exception_when_composerjson_is_malformed() {

		$this->expectException(Exception::class);

		$file = __DIR__ . '/ConfigTest/composer-5.json';

		MozartConfig::loadFromFile( $file );

	}

}