<?php
/**
 * Build a list of files from the composer autoloaders.
 */

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class FileEnumerator
{

    /**
     * The only path variable with a leading slash.
     * All directories in project end with a slash.
     *
     * @var string
     */
    protected string $workingDir;

    protected string $targetDir;

    /** @var ComposerPackage[] */
    protected array $dependencies;

    /** @var Filesystem */
    protected Filesystem $filesystem;

    /**
     * Copier constructor.
     * @param ComposerPackage[] $dependencies
     * @param string $workingDir
     * @param string $relativeTargetDir
     */
    public function __construct(array $dependencies, string $workingDir, string $relativeTargetDir)
    {
        $this->workingDir = $workingDir;

        $this->dependencies = $dependencies;

        $this->targetDir = $relativeTargetDir;

        $this->filesystem = new Filesystem(new Local($this->workingDir));
    }

    /**
     * Complete list of files to copy.
     *
     * Relative filepaths keyed with the same string to eliminate duplicates.
     *
     * Relative from vendor/
     *
     * @var array<string, string>
     */
    protected array $filesWithDependencies = [];

    /**
     * This should be in another class becuase it needs to build a list per package, all of which will be copied,
     * only some (most) of which will be prefixed.
     *
     * Read the autoload keys of the dependencies and generate a list of the files referenced.
     */
    public function compileFileList()
    {

        $finder = new Finder();

        // TODO: read 'vendor' from composer.json.
        $prefixToRemove = $this->workingDir .'vendor'. DIRECTORY_SEPARATOR;

        foreach ($this->dependencies as $dependency) {

            /**
             * Where $dependency->autoload is ~
             *
             * [ "psr-4" => [ "BrianHenryIE\Strauss" => "src" ] ]
             */
            $autoloaders = $dependency->getAutoload();

            foreach ($autoloaders as $type => $value) {
                // Might have to switch/case here.

                foreach ($value as $namespace => $path) {
                    $packagePath = $this->workingDir . 'vendor' . DIRECTORY_SEPARATOR
                        . $dependency->getName() . DIRECTORY_SEPARATOR;

                    if (is_file($packagePath . $path)) {
                        //  $finder->files()->name($file)->in($source_path);

                        $relativeFilepath = str_replace($prefixToRemove, '', $packagePath . $path);

                        $this->filesWithDependencies[ $relativeFilepath ] = $dependency->getName();

                        continue;
                    }

                    // else it is a directory.

                    $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                    $sourcePath = $packagePath. $path;

                    // Only returning directories ... bh-wp-logger... symlink issue?

                    $realSourcePath = realpath($sourcePath);


//                    $finder->files()->in($realSourcePath)
                    $finder->files()->in($sourcePath)->followLinks();

                    foreach ($finder as $foundFile) {
                        $filePath = $foundFile->getPathname();
//                        $filePath = str_replace( $realSourcePath, $sourcePath, $filePath );
                        $relativeFilepath = str_replace($prefixToRemove, '', $filePath);
                        $this->filesWithDependencies[$relativeFilepath] = $dependency->getName();
                    }
                }
            }
        }
    }

    public function getFileList(): array
    {
        return array_keys($this->filesWithDependencies);
    }

    public function getPhpFileList(): array
    {
        return array_filter($this->getFileList(), function ($element) {
            return false !== strpos($element, '.php');
        });
    }
}
