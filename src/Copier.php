<?php
/**
 * Prepares the destination by deleting any files about to be copied.
 * Copies the files.
 *
 * TODO: Exclude files list.
 *
 * @author CoenJacobs
 * @author BrianHenryIE
 *
 * @license MIT
 */

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class Copier
{
    /**
     * The only path variable with a leading slash.
     * All directories in project end with a slash.
     *
     * @var string
     */
    protected string $workingDir;

    protected string $targetDir;

    /** @var string */
    protected string $vendorDir;

    protected array $filepaths;

    /** @var Filesystem */
    protected Filesystem $filesystem;

    /**
     * Copier constructor.
     * @param array<string, ComposerPackage> $filepaths
     * @param string $workingDir
     * @param string $relativeTargetDir
     * @param string $vendorDir
     */
    public function __construct(array $filepaths, string $workingDir, string $relativeTargetDir, string $vendorDir = false)
    {
        $this->filepaths = array_keys($filepaths);

        $this->workingDir = $workingDir;

        $this->targetDir = $relativeTargetDir;

        if ( false == $vendorDir )
        {
            $vendorDir = 'vendor' . DIRECTORY_SEPARATOR;
        }
        $this->vendorDir = $vendorDir;

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    /**
     * If the target dir does not exist, create it.
     * If it already exists, delete any files we're about to copy.
     *
     * @return void
     */
    public function prepareTarget(): void
    {
        if (! $this->filesystem->has($this->targetDir)) {
            $this->filesystem->createDir($this->targetDir);
        } else {
            foreach ($this->filepaths as $vendorRelativeFilepath) {
                $projectRelativeFilepath = $this->targetDir . $vendorRelativeFilepath;

                if ($this->filesystem->has($projectRelativeFilepath)) {
                    $this->filesystem->delete($projectRelativeFilepath);
                }
            }
        }
    }


    /**
     *
     */
    public function copy(): void
    {

        foreach ($this->filepaths as $relativeFilepath) {
            $sourceFileRelativePath = $this->vendorDir . $relativeFilepath;

            $targetFileRelativePath = $this->targetDir . $relativeFilepath;

            $this->filesystem->copy($sourceFileRelativePath, $targetFileRelativePath);
        }
    }
}
