<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit;

use CoenJacobs\Mozart\Config\Mozart;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\FilesHandler;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilesHandlerTest extends TestCase
{
    private string $testDir;
    private Mozart $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mozart_test_' . uniqid();
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
    public function it_can_read_existing_file(): void
    {
        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'test.txt';
        $content = 'Test content';
        file_put_contents($filePath, $content);

        $handler = new FilesHandler($this->config);
        $result = $handler->readFile('test.txt');

        $this->assertEquals($content, $result);
    }

    #[Test]
    public function it_throws_exception_when_reading_nonexistent_file(): void
    {
        $handler = new FilesHandler($this->config);

        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessage('Failed to read file');

        $handler->readFile('nonexistent.txt');
    }

    #[Test]
    public function it_can_write_file(): void
    {
        $filePath = 'test_write.txt';
        $content = 'Written content';

        $handler = new FilesHandler($this->config);
        $handler->writeFile($filePath, $content);

        $this->assertFileExists($this->testDir . DIRECTORY_SEPARATOR . $filePath);
        $this->assertEquals($content, file_get_contents($this->testDir . DIRECTORY_SEPARATOR . $filePath));
    }

    #[Test]
    public function it_can_create_directory(): void
    {
        $dirPath = 'test_directory';

        $handler = new FilesHandler($this->config);
        $handler->createDirectory($dirPath);

        $this->assertDirectoryExists($this->testDir . DIRECTORY_SEPARATOR . $dirPath);
    }

    #[Test]
    public function it_can_delete_directory(): void
    {
        $dirPath = 'test_directory';
        mkdir($this->testDir . DIRECTORY_SEPARATOR . $dirPath, 0777, true);

        $handler = new FilesHandler($this->config);
        $handler->deleteDirectory($dirPath);

        $this->assertDirectoryDoesNotExist($this->testDir . DIRECTORY_SEPARATOR . $dirPath);
    }

    #[Test]
    public function it_can_check_if_directory_is_empty(): void
    {
        $emptyDir = 'empty_dir';
        $nonEmptyDir = 'non_empty_dir';
        mkdir($this->testDir . DIRECTORY_SEPARATOR . $emptyDir, 0777, true);
        mkdir($this->testDir . DIRECTORY_SEPARATOR . $nonEmptyDir, 0777, true);
        file_put_contents($this->testDir . DIRECTORY_SEPARATOR . $nonEmptyDir . DIRECTORY_SEPARATOR . 'file.txt', 'content');

        $handler = new FilesHandler($this->config);

        $this->assertTrue($handler->isDirectoryEmpty($emptyDir));
        $this->assertFalse($handler->isDirectoryEmpty($nonEmptyDir));
    }

    #[Test]
    public function it_can_get_files_from_path(): void
    {
        $subDir = $this->testDir . DIRECTORY_SEPARATOR . 'subdir';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'file1.txt', 'content1');
        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'file2.txt', 'content2');

        $handler = new FilesHandler($this->config);
        $files = $handler->getFilesFromPath($subDir);

        $fileCount = 0;
        foreach ($files as $file) {
            $fileCount++;
        }

        $this->assertEquals(2, $fileCount);
    }

    #[Test]
    public function it_excludes_vendor_directory_from_get_files_from_path(): void
    {
        $subDir = $this->testDir . DIRECTORY_SEPARATOR . 'subdir';
        $vendorDir = $subDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'some-package';
        mkdir($subDir, 0777, true);
        mkdir($vendorDir, 0777, true);
        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'file1.php', 'content1');
        file_put_contents($vendorDir . DIRECTORY_SEPARATOR . 'file2.php', 'content2');

        $handler = new FilesHandler($this->config);
        $files = $handler->getFilesFromPath($subDir);

        $filePaths = [];
        foreach ($files as $file) {
            $filePaths[] = $file->getFilename();
        }

        $this->assertContains('file1.php', $filePaths);
        $this->assertNotContains('file2.php', $filePaths);
    }

    #[Test]
    public function it_excludes_vendor_directory_from_get_file(): void
    {
        $subDir = $this->testDir . DIRECTORY_SEPARATOR . 'subdir';
        $vendorDir = $subDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'some-package';
        mkdir($subDir, 0777, true);
        mkdir($vendorDir, 0777, true);
        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'target.php', 'content');
        file_put_contents($vendorDir . DIRECTORY_SEPARATOR . 'target.php', 'duplicate');

        $handler = new FilesHandler($this->config);
        $files = $handler->getFile($subDir, 'target.php');

        $fileCount = 0;
        foreach ($files as $file) {
            $fileCount++;
            $this->assertStringNotContainsString('vendor', $file->getPathname());
        }

        $this->assertEquals(1, $fileCount);
    }

    #[Test]
    public function it_can_get_specific_file_by_name(): void
    {
        $subDir = $this->testDir . DIRECTORY_SEPARATOR . 'subdir';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'target.txt', 'target');
        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'other.txt', 'other');

        $handler = new FilesHandler($this->config);
        $files = $handler->getFile($subDir, 'target.txt');

        $fileCount = 0;
        foreach ($files as $file) {
            $fileCount++;
            $this->assertStringEndsWith('target.txt', $file->getPathname());
        }

        $this->assertEquals(1, $fileCount);
    }

    #[Test]
    public function it_can_copy_file(): void
    {
        $sourceFile = 'source.txt';
        $destFile = 'dest.txt';
        $content = 'Source content';
        file_put_contents($this->testDir . DIRECTORY_SEPARATOR . $sourceFile, $content);

        $handler = new FilesHandler($this->config);
        $handler->copyFile($sourceFile, $destFile);

        $this->assertFileExists($this->testDir . DIRECTORY_SEPARATOR . $destFile);
        $this->assertEquals($content, file_get_contents($this->testDir . DIRECTORY_SEPARATOR . $destFile));
    }

    #[Test]
    public function it_returns_config(): void
    {
        $handler = new FilesHandler($this->config);

        $this->assertSame($this->config, $handler->getConfig());
    }

    #[Test]
    public function it_creates_directories_with_public_permissions(): void
    {
        $handler = new FilesHandler($this->config);
        $handler->createDirectory('output_dir');

        $dirPath = $this->testDir . DIRECTORY_SEPARATOR . 'output_dir';
        $permissions = fileperms($dirPath) & 0777;

        $this->assertSame(0755, $permissions, sprintf(
            'Expected directory permissions 0755, got %04o',
            $permissions
        ));
    }

    #[Test]
    public function it_writes_files_with_public_permissions(): void
    {
        $handler = new FilesHandler($this->config);
        $handler->writeFile('output.php', '<?php echo "hello";');

        $filePath = $this->testDir . DIRECTORY_SEPARATOR . 'output.php';
        $permissions = fileperms($filePath) & 0777;

        $this->assertSame(0644, $permissions, sprintf(
            'Expected file permissions 0644, got %04o',
            $permissions
        ));
    }

    #[Test]
    public function it_creates_nested_directories_with_public_permissions(): void
    {
        $handler = new FilesHandler($this->config);
        $handler->createDirectory('vendor_prefixed/Package/SubDir');

        $basePath = $this->testDir . DIRECTORY_SEPARATOR;

        $dirs = [
            'vendor_prefixed',
            'vendor_prefixed/Package',
            'vendor_prefixed/Package/SubDir',
        ];

        foreach ($dirs as $dir) {
            $fullPath = $basePath . $dir;
            $permissions = fileperms($fullPath) & 0777;
            $this->assertSame(0755, $permissions, sprintf(
                'Expected 0755 for %s, got %04o',
                $dir,
                $permissions
            ));
        }
    }

    #[Test]
    public function it_throws_exception_when_writing_fails(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('write')
            ->willThrowException(UnableToWriteFile::atLocation('test.txt', 'Permission denied'));

        $handler = new FilesHandler($this->config, $filesystem);

        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessage('Failed to write file: test.txt');

        $handler->writeFile('test.txt', 'content');
    }

    #[Test]
    public function it_throws_exception_when_copy_fails(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('copy')
            ->willThrowException(UnableToCopyFile::fromLocationTo('source.txt', 'dest.txt'));

        $handler = new FilesHandler($this->config, $filesystem);

        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessage('Failed to copy file: source.txt to dest.txt');

        $handler->copyFile('source.txt', 'dest.txt');
    }

    #[Test]
    public function it_throws_exception_when_creating_directory_fails(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('createDirectory')
            ->willThrowException(UnableToCreateDirectory::atLocation('test_dir', 'Permission denied'));

        $handler = new FilesHandler($this->config, $filesystem);

        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessage('Failed to create directory: test_dir');

        $handler->createDirectory('test_dir');
    }

    #[Test]
    public function it_throws_exception_when_deleting_directory_fails(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('deleteDirectory')
            ->willThrowException(UnableToDeleteDirectory::atLocation('test_dir', 'Directory not empty'));

        $handler = new FilesHandler($this->config, $filesystem);

        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessage('Failed to delete directory: test_dir');

        $handler->deleteDirectory('test_dir');
    }
}

