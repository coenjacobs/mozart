<?php

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Cleanup
{

    /** @var Filesystem */
    protected $filesystem;

    protected StraussConfig $config;

    public function __construct(StraussConfig $config, string $workingDir)
    {
        $this->config = $config;
        $this->filesystem = new Filesystem(new Local($workingDir));
    }

    /**
     * Maybe delete the source files that were copied.
     * then delete empty directories.
     *
     * @param array $sourceFiles
     */
    public function cleanup(array $sourceFiles)
    {

        // TODO Don't do this if vendor is the target dir (i.e. in-situ updating).

        if ($this->config->isDeleteVendorDirectories()) {
            foreach ($sourceFiles as $sourceFile) {
                $relativeFilepath = 'vendor' . DIRECTORY_SEPARATOR . $sourceFile;

                $this->filesystem->delete($relativeFilepath);
            }

            // Get the root folders of the moved files.
            $rootSourceDirectories = [];
            foreach ($sourceFiles as $sourceFile) {
                $arr = explode("/", $sourceFile, 2);
                $dir = $arr[0];
                $rootSourceDirectories[ $dir ] = $dir;
            }
            $rootSourceDirectories = array_keys($rootSourceDirectories);


            $finder = new Finder();

            foreach ($rootSourceDirectories as $rootSourceDirectory) {
                if (!is_dir($rootSourceDirectory) || is_link($rootSourceDirectory)) {
                    continue;
                }

                $finder->directories()->path($rootSourceDirectory);

                foreach ($finder as $directory) {
                    $metadata = $this->filesystem->getMetadata($directory);

                    if ($this->dirIsEmpty($directory)) {
                        $this->filesystem->deleteDir($directory);
                    }
                }
            }
        }
    }

    // TODO: Use Symphony or Flysystem functions.
    protected function dirIsEmpty(string $dir): bool
    {
        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        return iterator_count($di) === 0;
    }
}
