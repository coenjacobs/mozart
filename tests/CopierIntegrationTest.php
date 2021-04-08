<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\ComposerPackage;
use CoenJacobs\Mozart\Composer\ProjectComposerPackage;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class CopierTest
 * @package CoenJacobs\Mozart
 * @coversDefaultClass \CoenJacobs\Mozart\Copier
 */
class CopierIntegrationTest extends TestCase
{

    protected $testsWorkingDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir/';

        if (file_exists($this->testsWorkingDir)) {
            // TODO: Tests won't run on Windows with this.
            exec(sprintf("rm -rf %s", escapeshellarg($this->testsWorkingDir)));
        }

        @mkdir($this->testsWorkingDir, );
    }

    public function testsPrepareTarget()
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
        $relativeTargetDir = 'nannerl' . DIRECTORY_SEPARATOR;

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $relativeTargetDir);
        $fileEnumerator->compileFileList();
        $filepaths = $fileEnumerator->getFileList();

        $copier = new Copier($filepaths, $workingDir, $relativeTargetDir);

        $file = 'ContainerAwareTrait.php';
        $relativePath = 'league/container/src/';
        $targetPath = $this->testsWorkingDir . $relativeTargetDir . $relativePath;
        $targetFile = $targetPath . $file;

        mkdir( rtrim( $targetPath, DIRECTORY_SEPARATOR ), 0777, true );

        file_put_contents( $targetFile, 'dummy file');

        assert( file_exists( $targetFile ) );

        $copier->prepareTarget();

        $this->assertFileDoesNotExist( $targetFile );
    }

    public function testsCopy()
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
        $relativeTargetDir = 'nannerl' . DIRECTORY_SEPARATOR;

        $fileEnumerator = new FileEnumerator($dependencies, $workingDir, $relativeTargetDir);
        $fileEnumerator->compileFileList();
        $filepaths = $fileEnumerator->getFileList();

        $copier = new Copier($filepaths, $workingDir, $relativeTargetDir);

        $file = 'ContainerAwareTrait.php';
        $relativePath = 'league/container/src/';
        $targetPath = $this->testsWorkingDir . $relativeTargetDir . $relativePath;
        $targetFile = $targetPath . $file;

        $copier->prepareTarget();

        $copier->copy();

        $this->assertFileExists( $targetFile );
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

        $this->deleteDir($dir);
    }

    protected function deleteDir($dir)
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
