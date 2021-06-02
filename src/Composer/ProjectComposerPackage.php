<?php
/**
 * Extends ComposerPackage to return the typed Strauss config.
 */

namespace BrianHenryIE\Strauss\Composer;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;

class ProjectComposerPackage extends ComposerPackage
{
    protected string $author;

    protected string $vendorDirectory;

    public function __construct(string $absolutePath, array $overrideAutoload = null)
    {
        parent::__construct($absolutePath, $overrideAutoload);

        $authors = $this->composer->getPackage()->getAuthors();
        if (empty($authors) || !isset($authors[0]['name'])) {
            $this->author = explode("/", $this->name, 2)[0];
        } else {
            $this->author = $authors[0]['name'];
        }

        $vendorDirectory = $this->composer->getConfig()->get('vendor-dir');
        if (is_string($vendorDirectory)) {
            $vendorDirectory = str_replace($absolutePath, '', (string) $vendorDirectory);
            $this->vendorDirectory = $vendorDirectory;
        } else {
            $this->vendorDirectory = 'vendor' . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * @return StraussConfig
     * @throws \Exception
     */
    public function getStraussConfig(): StraussConfig
    {
        $config = new StraussConfig($this->composer);
        $config->setVendorDirectory($this->getVendorDirectory());
        return $config;
    }


    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getVendorDirectory(): string
    {
        return rtrim($this->vendorDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
