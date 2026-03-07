<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Config;

use CoenJacobs\Mozart\Config\ConfigDefaultsResolver;
use CoenJacobs\Mozart\Config\ConfigLoader;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\OverrideAutoload;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\PackageFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigMapperTest extends TestCase
{
    private ConfigLoader $loader;
    private ConfigDefaultsResolver $defaults;

    public function setUp(): void
    {
        parent::setUp();
        $this->loader = new ConfigLoader();
        $this->defaults = new ConfigDefaultsResolver();
    }

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
        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
            'generate_autoloader' => true,
        ]), Mozart::class);

        $this->assertTrue($result->getGenerateAutoloader());
    }

    #[Test]
    public function it_defaults_generate_autoloader_to_true(): void
    {
        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
        ]), Mozart::class);

        $this->assertTrue($result->getGenerateAutoloader());
    }

    #[Test]
    public function it_respects_explicit_generate_autoloader_false(): void
    {
        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
            'generate_autoloader' => false,
        ]), Mozart::class);

        $this->assertFalse($result->getGenerateAutoloader());
    }

    #[Test]
    public function it_applies_all_defaults_with_psr4_root_package(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertTrue($result->isValidMozartConfig());
        $this->assertSame('vendor-prefixed/', $result->depDirectory);
        $this->assertSame('vendor-prefixed/', $result->classmapDir);
        $this->assertSame('MyPlugin\\Dependencies', $result->depNamespace);
        $this->assertSame('MyPlugin_', $result->classmapPrefix);
        $this->assertSame('MYPLUGIN_', $result->constantPrefix);
        $this->assertSame('myplugin_', $result->functionsPrefix);
    }

    #[Test]
    public function it_infers_namespace_from_package_name_when_no_psr4(): void
    {
        $package = new Package();
        $package->name = 'coen-jacobs/my-plugin';

        $extra = new \CoenJacobs\Mozart\Config\Extra();
        $extra->mozart = new Mozart();
        $package->extra = $extra;

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('CoenJacobs\\MyPlugin\\Dependencies', $result->depNamespace);
        $this->assertSame('CoenJacobs_MyPlugin_', $result->classmapPrefix);
    }

    #[Test]
    public function it_fails_validation_with_no_psr4_and_no_name(): void
    {
        $package = new Package();
        $package->name = '';

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertFalse($result->isValidMozartConfig());
        $this->assertSame('', $result->depNamespace);
        $this->assertContains('dep_namespace', $result->getMissingConfigFields());
        $this->assertContains('classmap_prefix', $result->getMissingConfigFields());
    }

    #[Test]
    public function it_preserves_explicit_values_with_partial_config(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Custom\\Namespace',
            'dep_directory' => 'custom-dir/',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertTrue($result->isValidMozartConfig());
        $this->assertSame('Custom\\Namespace', $result->depNamespace);
        $this->assertSame('custom-dir/', $result->depDirectory);
        $this->assertSame('custom-dir/', $result->classmapDir);
        $this->assertSame('Custom_Namespace_', $result->classmapPrefix);
    }

    #[Test]
    public function it_does_not_change_full_config(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Explicit\\Namespace',
            'dep_directory' => 'explicit-dir/',
            'classmap_directory' => 'explicit-classmap/',
            'classmap_prefix' => 'Explicit_Prefix_',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('Explicit\\Namespace', $result->depNamespace);
        $this->assertSame('explicit-dir/', $result->depDirectory);
        $this->assertSame('explicit-classmap/', $result->classmapDir);
        $this->assertSame('Explicit_Prefix_', $result->classmapPrefix);
    }

    #[Test]
    public function it_defaults_dep_directory_to_vendor_prefixed(): void
    {
        $package = $this->createPackageWithPsr4('Test\\');

        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'classmap_prefix' => 'Test_',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('vendor-prefixed/', $result->depDirectory);
    }

    #[Test]
    public function it_defaults_classmap_directory_to_dep_directory(): void
    {
        $package = $this->createPackageWithPsr4('Test\\');

        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => 'my-deps/',
            'classmap_prefix' => 'Test_',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('my-deps/', $result->classmapDir);
    }

    #[Test]
    public function it_derives_classmap_prefix_from_explicit_dep_namespace(): void
    {
        $package = $this->createPackageWithPsr4('Test\\');

        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'MyPlugin\\Vendor\\Deps',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('MyPlugin_Vendor_Deps_', $result->classmapPrefix);
    }

    #[Test]
    public function it_derives_classmap_prefix_from_inferred_dep_namespace(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('MyPlugin_', $result->classmapPrefix);
    }

    #[Test]
    public function it_converts_kebab_case_package_name_to_namespace(): void
    {
        $package = new Package();
        $package->name = 'my-vendor/my-awesome-plugin';

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('MyVendor\\MyAwesomePlugin\\Dependencies', $result->depNamespace);
    }

    #[Test]
    public function it_returns_missing_config_fields(): void
    {
        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Deps',
            'dep_directory' => 'deps/',
        ]), Mozart::class);

        $missing = $result->getMissingConfigFields();
        $this->assertContains('classmap_directory', $missing);
        $this->assertContains('classmap_prefix', $missing);
        $this->assertNotContains('dep_namespace', $missing);
        $this->assertNotContains('dep_directory', $missing);
    }

    #[Test]
    public function it_respects_explicit_delete_vendor_directories_false(): void
    {
        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
            'delete_vendor_directories' => false,
        ]), Mozart::class);

        $this->assertFalse($result->getDeleteVendorDirectories());
    }

    #[Test]
    public function it_defaults_delete_vendor_directories_to_true(): void
    {
        $result = $this->loader->fromString(json_encode([
            'dep_namespace' => 'Test\\Dependencies',
            'dep_directory' => '/deps/',
            'classmap_directory' => '/classes/',
            'classmap_prefix' => 'Test_',
        ]), Mozart::class);

        $this->assertTrue($result->getDeleteVendorDirectories());
    }

    #[Test]
    public function it_preserves_explicit_delete_vendor_directories_false_after_defaults(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode([
            'delete_vendor_directories' => false,
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertFalse($result->getDeleteVendorDirectories());
        $this->assertTrue($result->isValidMozartConfig());
    }

    #[Test]
    public function it_defaults_delete_vendor_directories_to_true_after_defaults(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertTrue($result->getDeleteVendorDirectories());
    }

    #[Test]
    public function it_defaults_constant_prefix_from_classmap_prefix(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('MYPLUGIN_', $result->constantPrefix);
    }

    #[Test]
    public function it_defaults_functions_prefix_from_classmap_prefix(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('myplugin_', $result->functionsPrefix);
    }

    #[Test]
    public function it_preserves_explicit_constant_prefix(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode([
            'constant_prefix' => 'MY_CUSTOM_',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('MY_CUSTOM_', $result->constantPrefix);
    }

    #[Test]
    public function it_preserves_explicit_functions_prefix(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode([
            'functions_prefix' => 'my_custom_',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('my_custom_', $result->functionsPrefix);
    }

    #[Test]
    public function it_derives_both_prefixes_from_inferred_classmap_prefix(): void
    {
        $package = new Package();
        $package->name = 'coen-jacobs/my-plugin';

        $result = $this->loader->fromString(json_encode(new \stdClass()), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('CoenJacobs_MyPlugin_', $result->classmapPrefix);
        $this->assertSame('COENJACOBS_MYPLUGIN_', $result->constantPrefix);
        $this->assertSame('coenjacobs_myplugin_', $result->functionsPrefix);
    }

    #[Test]
    public function it_derives_constant_and_functions_prefix_from_explicit_classmap_prefix(): void
    {
        $package = $this->createPackageWithPsr4('MyPlugin\\');

        $result = $this->loader->fromString(json_encode([
            'classmap_prefix' => 'CustomPrefix_',
        ]), Mozart::class);
        $this->defaults->apply($result, $package);

        $this->assertSame('CustomPrefix_', $result->classmapPrefix);
        $this->assertSame('CUSTOMPREFIX_', $result->constantPrefix);
        $this->assertSame('customprefix_', $result->functionsPrefix);
    }

    /**
     * Create a Package with a PSR-4 autoloader for testing.
     */
    private function createPackageWithPsr4(string $namespace): Package
    {
        $factory = new PackageFactory();
        $json = json_encode([
            'name' => 'test/project',
            'autoload' => [
                'psr-4' => [
                    $namespace => 'src/',
                ],
            ],
            'extra' => [
                'mozart' => new \stdClass(),
            ],
        ]);

        // Write a temp file for the factory
        $tempFile = sys_get_temp_dir() . '/mozart-test-' . md5($namespace) . '.json';
        file_put_contents($tempFile, $json);

        $package = $factory->createPackage($tempFile);
        unlink($tempFile);

        return $package;
    }
}
