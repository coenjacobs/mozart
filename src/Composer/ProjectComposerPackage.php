<?php
/**
 * Extends ComposerPackage to return the typed Nannerl config.
 */

namespace CoenJacobs\Mozart\Composer;

use CoenJacobs\Mozart\Composer\Extra\NannerlConfig;

class ProjectComposerPackage extends ComposerPackage
{
    /**
     * @return NannerlConfig
     * @throws \Exception
     */
    public function getNannerlConfig(): NannerlConfig
    {

        return new NannerlConfig($this->composer);
    }
}
