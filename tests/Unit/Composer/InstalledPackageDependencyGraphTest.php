<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Composer;

use CoenJacobs\Mozart\Composer\InstalledPackageDependencyGraph;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class InstalledPackageDependencyGraphTest extends TestCase
{
    private string $workingDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDir = sys_get_temp_dir() . '/mozart_installed_graph_' . uniqid();
        mkdir($this->workingDir . '/vendor/composer', 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (!is_dir($this->workingDir)) {
            return;
        }

        $iterator = new RecursiveDirectoryIterator($this->workingDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($this->workingDir);
    }

    #[Test]
    public function it_builds_a_dependency_graph_from_composer_two_metadata(): void
    {
        $this->writeInstalledJson([
            'packages' => [
                [
                    'name' => 'vendor/tool',
                    'require' => [
                        'vendor/shared' => '^1.0',
                        'php' => '^8.1',
                    ],
                ],
                [
                    'name' => 'vendor/shared',
                    'require' => [
                        'ext-json' => '*',
                    ],
                ],
            ],
        ]);

        $graph = $this->createGraph()->getDependencyGraph();

        $this->assertSame(
            [
                'vendor/tool' => ['vendor/shared'],
                'vendor/shared' => [],
            ],
            $graph
        );
    }

    #[Test]
    public function it_builds_a_dependency_graph_from_legacy_metadata(): void
    {
        $this->writeInstalledJson([
            [
                'name' => 'vendor/tool',
                'require' => [
                    'vendor/shared' => '^1.0',
                ],
            ],
            [
                'name' => 'vendor/shared',
            ],
        ]);

        $graph = $this->createGraph()->getDependencyGraph();

        $this->assertSame(
            [
                'vendor/tool' => ['vendor/shared'],
                'vendor/shared' => [],
            ],
            $graph
        );
    }

    #[Test]
    public function it_returns_no_shared_packages_when_installed_json_is_missing(): void
    {
        $sharedPackages = $this->createGraph()->getSharedProcessedPackages(['vendor/shared']);

        $this->assertSame([], $sharedPackages);
    }

    #[Test]
    public function it_detects_directly_shared_processed_packages(): void
    {
        $this->writeInstalledJson([
            'packages' => [
                [
                    'name' => 'vendor/tool',
                    'require' => [
                        'vendor/shared' => '^1.0',
                    ],
                ],
                [
                    'name' => 'vendor/processed',
                    'require' => [
                        'vendor/shared' => '^1.0',
                    ],
                ],
                [
                    'name' => 'vendor/shared',
                ],
            ],
        ]);

        $sharedPackages = $this->createGraph()->getSharedProcessedPackages([
            'vendor/processed',
            'vendor/shared',
        ]);

        $this->assertSame(['vendor/shared'], $sharedPackages);
    }

    #[Test]
    public function it_detects_transitively_shared_processed_packages(): void
    {
        $this->writeInstalledJson([
            'packages' => [
                [
                    'name' => 'vendor/tool',
                    'require' => [
                        'vendor/processed-a' => '^1.0',
                    ],
                ],
                [
                    'name' => 'vendor/processed-a',
                    'require' => [
                        'vendor/processed-b' => '^1.0',
                    ],
                ],
                [
                    'name' => 'vendor/processed-b',
                    'require' => [
                        'vendor/shared' => '^1.0',
                    ],
                ],
                [
                    'name' => 'vendor/shared',
                ],
            ],
        ]);

        $sharedPackages = $this->createGraph()->getSharedProcessedPackages([
            'vendor/processed-a',
            'vendor/processed-b',
            'vendor/shared',
        ]);

        $this->assertSame(
            [
                'vendor/processed-a',
                'vendor/processed-b',
                'vendor/shared',
            ],
            $sharedPackages
        );
    }

    /**
     * @param array<mixed> $payload
     */
    private function writeInstalledJson(array $payload): void
    {
        file_put_contents(
            $this->workingDir . '/vendor/composer/installed.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function createGraph(): InstalledPackageDependencyGraph
    {
        return new InstalledPackageDependencyGraph($this->workingDir);
    }
}
