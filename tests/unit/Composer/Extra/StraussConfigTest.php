<?php
/**
 * Should accept Nannarl config and Mozart config.
 *
 * Should have sensible defaults.
 */

namespace BrianHenryIE\Strauss\Composer\Extra;

use Composer\Factory;
use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;

class StraussConfigTest extends TestCase
{

    /**
     * With a full (at time of writing) config, test the getters.
     */
    public function testGetters()
    {

        $path = __DIR__ . '/straussconfig-test-1.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertContains('pimple/pimple', $sut->getPackages());

        $this->assertEquals('target_directory' . DIRECTORY_SEPARATOR, $sut->getTargetDirectory());

        $this->assertEquals("BrianHenryIE\\Strauss\\", $sut->getNamespacePrefix());

        $this->assertEquals('BrianHenryIE_Strauss_', $sut->getClassmapPrefix());

        $this->assertContains('psr/container', $sut->getExcludePrefixPackages());

        $this->assertArrayHasKey('clancats/container', $sut->getOverrideAutoload());

        $this->assertFalse($sut->isDeleteVendorDirectories());
    }

    /**
     * Test how it handles an extra key.
     *
     * Turns out it just ignores it... good!
     */
    public function testExtraKey()
    {

        $path = __DIR__ . '/straussconfig-test-2.json';

        $composer = Factory::create(new NullIO(), $path);

        $exception = null;

        try {
            $sut = new StraussConfig($composer);
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->assertNull($exception);
    }

    /**
     * straussconfig-test-3.json has no target_dir key.
     *
     * If no target_dir is specified, used "strauss/"
     */
    public function testDefaultTargetDir()
    {

        $path = __DIR__ . '/straussconfig-test-3.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertEquals('strauss'. DIRECTORY_SEPARATOR, $sut->getTargetDirectory());
    }

    /**
     * When the namespace prefix isn't provided, use the PSR-4 autoload key name.
     */
    public function testDefaultNamespacePrefixFromAutoloaderPsr4()
    {

        $path = __DIR__ . '/straussconfig-test-4.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE\\Strauss\\", $sut->getNamespacePrefix());
    }

    /**
     * When the namespace prefix isn't provided, use the PSR-0 autoload key name.
     */
    public function testDefaultNamespacePrefixFromAutoloaderPsr0()
    {

        $path = __DIR__ . '/straussconfig-test-5.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE\\Strauss\\", $sut->getNamespacePrefix());
    }

    /**
     * When the namespace prefix isn't provided, and there's no PSR-0 or PSR-4 autoloader to figure it from...
     *
     * brianhenryie/strauss-config-test
     */
    public function testDefaultNamespacePrefixWithNoAutoloader()
    {

        $path = __DIR__ . '/straussconfig-test-6.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertEquals("Brianhenryie\\Strauss_Config_Test\\", $sut->getNamespacePrefix());
    }

    /**
     * When the classmap prefix isn't provided, use the PSR-4 autoload key name.
     */
    public function testDefaultClassmapPrefixFromAutoloaderPsr4()
    {

        $path = __DIR__ . '/straussconfig-test-4.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE_Strauss_", $sut->getClassmapPrefix());
    }

    /**
     * When the classmap prefix isn't provided, use the PSR-0 autoload key name.
     */
    public function testDefaultClassmapPrefixFromAutoloaderPsr0()
    {

        $path = __DIR__ . '/straussconfig-test-5.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE_Strauss_", $sut->getClassmapPrefix());
    }

    /**
     * When the classmap prefix isn't provided, and there's no PSR-0 or PSR-4 autoloader to figure it from...
     *
     * brianhenryie/strauss-config-test
     */
    public function testDefaultClassmapPrefixWithNoAutoloader()
    {

        $path = __DIR__ . '/straussconfig-test-6.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertEquals("Brianhenryie_Strauss_Config_Test", $sut->getClassmapPrefix());
    }

    /**
     * When Strauss config has packages specified, obviously use them.
     */
    public function testGetPackagesFromConfig()
    {

        $path = __DIR__ . '/straussconfig-test-1.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertContains('pimple/pimple', $sut->getPackages());
    }

    /**
     * When Strauss config has no packages specified, use composer.json's require list.
     */
    public function testGetPackagesNoConfig()
    {

        $path = __DIR__ . '/straussconfig-test-3.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertContains('league/container', $sut->getPackages());
    }

    /**
     * For backwards compatibility, if a Mozart config is present, use it.
     */
    public function testMapMozartConfig()
    {

        $path = __DIR__ . '/straussconfig-test-7.json';

        $composer = Factory::create(new NullIO(), $path);

        $sut = new StraussConfig($composer);

        $this->assertContains('pimple/pimple', $sut->getPackages());

        $this->assertEquals('dep_directory' . DIRECTORY_SEPARATOR, $sut->getTargetDirectory());

        $this->assertEquals("My_Mozart_Config\\", $sut->getNamespacePrefix());

        $this->assertEquals('My_Mozart_Config_', $sut->getClassmapPrefix());

        $this->assertContains('psr/container', $sut->getExcludePrefixPackages());

        $this->assertArrayHasKey('clancats/container', $sut->getOverrideAutoload());

        // Mozart default was true.
        $this->assertTrue($sut->isDeleteVendorDirectories());
    }
}
