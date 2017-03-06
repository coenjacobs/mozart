<?php

namespace CoenJacobs\Mozart;

use CoenJacobs\Mozart\Composer\Autoload\Psr0;
use CoenJacobs\Mozart\Composer\Autoload\Psr4;
use CoenJacobs\Mozart\Composer\Package;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Mover
{
    /** @var string */
    protected $workingDir;

    /** @var string */
    protected $targetDir;

    /** @var \stdClass */
    protected $config;

    public function __construct( $workingDir, $config )
    {
        $this->workingDir = $workingDir;
        $this->targetDir = $config->dep_directory;
        $this->config = $config;
    }

    public function deleteTargetDir()
    {
        $filesystem = new Filesystem(new Local($this->workingDir));
        $filesystem->deleteDir($this->targetDir);
    }

    public function movePackage(Package $package)
    {
        $finder = new Finder();
        $filesystem = new Filesystem(new Local($this->workingDir));

        foreach( $package->autoloaders as $autoloader ) {
            foreach( $autoloader->paths as $path ) {
                $source_path = $this->workingDir . '/vendor/' . $package->config->name . '/' . $path;

                $finder->files()->in($source_path);

                $searchNamespace = $autoloader->namespace;

                if ( is_a($autoloader, Psr4::class)) {
                    $searchNamespace = trim($autoloader->namespace, '\\');
                }

                foreach ($finder as $file) {
                    if ( is_a($autoloader, Psr0::class)) {
                        $targetFile = str_replace($this->workingDir, $this->config->dep_directory, $file->getRealPath());
                    } else {
                        $namespacePath = str_replace('\\', '/', $autoloader->namespace);
                        $targetFile = str_replace($this->workingDir, $this->config->dep_directory . $namespacePath, $file->getRealPath());
                    }

                    $targetFile = str_replace('/vendor/' . $package->config->name . '/' . $path, '', $targetFile);

                    $filesystem->copy(
                        str_replace($this->workingDir, '', $file->getRealPath()),
                        $targetFile
                    );

                    if ( '.php' == substr($targetFile, '-4', 4 ) ) {
                        $contents = $filesystem->read($targetFile);

                        $contents = preg_replace_callback(
                            '/'.addslashes($searchNamespace).'([\\\|;])/U',
                            function($matches) {
                                $replace = trim($matches[0], $matches[1]);
                                return $this->config->dep_namespace . $replace . $matches[1];
                            },
                            $contents
                        );

                        $filesystem->put($targetFile, $contents);
                    }
                }
            }
        }
    }
}