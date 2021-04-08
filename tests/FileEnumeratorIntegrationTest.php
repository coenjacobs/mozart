<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\ComposerPackage;
use CoenJacobs\Mozart\Composer\ProjectComposerPackage;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class FileEnumeratorIntegrationTest
 * @package CoenJacobs\Mozart
 * @coversDefaultClass \CoenJacobs\Mozart\FileEnumerator
 */
class FileEnumeratorIntegrationTest extends TestCase {

    protected $testsWorkingDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir/';

        if (file_exists($this->testsWorkingDir)) {
            exec(sprintf("rm -rf %s", escapeshellarg($this->testsWorkingDir)));
        }

        @mkdir($this->testsWorkingDir, );
    }

    public function testBuildFileList()
    {
        copy(__DIR__ . '/copierintegration-test-1.json', $this->testsWorkingDir . 'composer.json');

        chdir($this->testsWorkingDir);
        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir);

        $dependencies = array_map(function ($element) {
            $dir = $this->testsWorkingDir . 'vendor'. DIRECTORY_SEPARATOR . $element;
            return new ComposerPackage($dir);
        }, $projectComposerPackage->getRequiresNames());

        $workingDir = $this->testsWorkingDir;
        $relativeTargetDir = 'nannerl';

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $relativeTargetDir);

        $fileEnumerator->compileFileList();

        $list = $fileEnumerator->getFileList();

        $this->assertContains('league/container/src/ContainerAwareTrait.php', $list);
    }


    public function testClassmapAutoloader() {
        $this->markTestIncomplete();
    }


    public function testFilesAutoloader() {
        $this->markTestIncomplete();
    }

    /**
     * Delete $this->testsWorkingDir after each test.
     *
     * @see https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $dir = $this->testsWorkingDir;

        $this->delete_dir($dir);
    }

    protected function delete_dir($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator(
            $it,
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}