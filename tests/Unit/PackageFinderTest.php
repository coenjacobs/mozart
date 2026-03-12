<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit;

use CoenJacobs\Mozart\Config\Classmap;
use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Config\Package;
use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use CoenJacobs\Mozart\PackageFactory;
use CoenJacobs\Mozart\PackageFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class PackageFinderTest extends TestCase
{
    private string $testDir;
    private Mozart $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_packagefinder_test_' . uniqid();
        mkdir($this->testDir, 0777, true);

        $this->config = new Mozart();
        $this->config->setWorkingDir($this->testDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    #[Test]
    public function it_throws_exception_when_config_not_set(): void
    {
        $finder = new PackageFinder();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Config not set to find packages');

        $finder->getPackageBySlug('vendor/package');
    }

    #[Test]
    public function it_returns_null_for_non_package_slug(): void
    {
        $finder = new PackageFinder();
        $finder->setConfig($this->config);

        $result = $finder->getPackageBySlug('php');

        $this->assertNull($result);
    }

    #[Test]
    public function it_throws_exception_when_package_directory_not_found(): void
    {
        $finder = new PackageFinder();
        $finder->setConfig($this->config);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Couldn't load package based on provided slug");

        $finder->getPackageBySlug('nonexistent/package');
    }

    #[Test]
    public function it_filters_out_null_packages_from_slugs(): void
    {
        $finder = new PackageFinder();
        $finder->setConfig($this->config);

        // Only test with non-package slugs (like 'php') since nonexistent packages throw exceptions
        $slugs = ['php', 'ext-mbstring'];

        $result = $finder->getPackagesBySlugs($slugs);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_finds_packages_recursively(): void
    {
        // Create a mock package structure
        $vendorDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor';
        $packageDir = $vendorDir . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'package';
        mkdir($packageDir, 0777, true);

        $composerJson = [
            'name' => 'test/package',
            'require' => [],
        ];
        file_put_contents($packageDir . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerJson));

        // Initialize overrideAutoload to avoid uninitialized property error
        $this->config->setOverrideAutoload(new stdClass());

        $finder = new PackageFinder();
        $finder->setConfig($this->config);

        $packages = [$finder->getPackageBySlug('test/package')];
        $result = $finder->findPackages($packages);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function it_has_factory_property(): void
    {
        $finder = new PackageFinder();

        $this->assertInstanceOf(PackageFactory::class, $finder->factory);
    }

    private function createMockPackage(string $name): Package
    {
        $package = new Package();
        $package->name = $name;
        return $package;
    }

    #[Test]
    public function it_deduplicates_diamond_dependencies(): void
    {
        // A depends on B and C; both B and C depend on D
        $d = $this->createMockPackage('vendor/d');
        $b = $this->createMockPackage('vendor/b');
        $b->registerDependency($d);
        $c = $this->createMockPackage('vendor/c');
        $c->registerDependency($d);
        $a = $this->createMockPackage('vendor/a');
        $a->registerDependency($b);
        $a->registerDependency($c);

        $finder = new PackageFinder();
        $result = $finder->findPackages([$a]);

        $this->assertCount(4, $result);

        $names = array_map(fn(Package $p) => $p->getName(), $result);
        $this->assertContains('vendor/a', $names);
        $this->assertContains('vendor/b', $names);
        $this->assertContains('vendor/c', $names);
        $this->assertContains('vendor/d', $names);
        $this->assertCount(4, array_unique($names));
    }

    #[Test]
    public function it_handles_circular_dependencies(): void
    {
        // A depends on B, B depends on A
        $a = $this->createMockPackage('vendor/a');
        $b = $this->createMockPackage('vendor/b');
        $a->registerDependency($b);
        $b->registerDependency($a);

        $finder = new PackageFinder();
        $result = $finder->findPackages([$a]);

        $this->assertCount(2, $result);

        $names = array_map(fn(Package $p) => $p->getName(), $result);
        $this->assertContains('vendor/a', $names);
        $this->assertContains('vendor/b', $names);
    }

    #[Test]
    public function it_passes_override_autoload_to_factory(): void
    {
        $vendorDir = $this->testDir . DIRECTORY_SEPARATOR . 'vendor';
        $packageDir = $vendorDir . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'package';
        mkdir($packageDir, 0777, true);

        // Package has psr-4 autoloading in its own composer.json
        $composerJson = [
            'name' => 'test/package',
            'autoload' => [
                'psr-4' => ['Original\\Namespace\\' => 'src/'],
            ],
            'require' => [],
        ];
        file_put_contents($packageDir . DIRECTORY_SEPARATOR . 'composer.json', json_encode($composerJson));

        // Configure override_autoload to replace it with a classmap
        $override = new stdClass();
        $override->{'test/package'} = (object) [
            'classmap' => ['lib/'],
        ];
        $this->config->setOverrideAutoload($override);

        $finder = new PackageFinder();
        $finder->setConfig($this->config);

        $package = $finder->getPackageBySlug('test/package');

        $this->assertNotNull($package);

        $autoloaders = $package->getAutoloaders();
        $this->assertCount(1, $autoloaders);
        $this->assertInstanceOf(Classmap::class, $autoloaders[0]);
    }

    #[Test]
    public function it_does_not_grow_exponentially_with_deep_shared_dependencies(): void
    {
        // Build a chain where each level shares a dependency with the next:
        // p0 -> p1 -> p2 -> p3 -> p4 -> p5
        // p0 -> p2, p1 -> p3, p2 -> p4, p3 -> p5 (extra shared edges)
        $packages = [];
        for ($i = 0; $i <= 5; $i++) {
            $packages[$i] = $this->createMockPackage("vendor/p{$i}");
        }

        for ($i = 0; $i < 5; $i++) {
            $packages[$i]->registerDependency($packages[$i + 1]);
        }
        // Add extra shared edges
        for ($i = 0; $i < 4; $i++) {
            $packages[$i]->registerDependency($packages[$i + 2]);
        }

        $finder = new PackageFinder();
        $result = $finder->findPackages([$packages[0]]);

        $this->assertCount(6, $result);

        $names = array_map(fn(Package $p) => $p->getName(), $result);
        $this->assertCount(6, array_unique($names));
    }
}

