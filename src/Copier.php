<?php

namespace BrianHenryIE\Strauss;

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

    protected array $filepaths;

    /** @var Filesystem */
    protected Filesystem $filesystem;

    /**
     * Copier constructor.
     * @param string[] $filepaths
     * @param string $workingDir
     * @param string $relativeTargetDir
     */
    public function __construct(array $filepaths, string $workingDir, string $relativeTargetDir)
    {
        $this->filepaths = $filepaths;

        $this->workingDir = $workingDir;

        $this->targetDir = $relativeTargetDir;

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
     * @return void
     */
    public function copy()
    {

        foreach ($this->filepaths as $relativeFilepath) {
            $sourceFileRelativePath = 'vendor' . DIRECTORY_SEPARATOR . $relativeFilepath;

            $targetFileRelativePath = $this->targetDir . $relativeFilepath;

            $this->filesystem->copy($sourceFileRelativePath, $targetFileRelativePath);
        }
    }
}
