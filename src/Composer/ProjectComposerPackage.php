<?php
/**
 * Extends ComposerPackage to return the typed Strauss config.
 */

namespace BrianHenryIE\Strauss\Composer;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;

class ProjectComposerPackage extends ComposerPackage
{
    /**
     * @return StraussConfig
     * @throws \Exception
     */
    public function getStraussConfig(): StraussConfig
    {

        return new StraussConfig($this->composer);
    }
}
