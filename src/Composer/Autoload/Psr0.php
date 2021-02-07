<?php
/**
 * @see https://www.php-fig.org/psr/psr-0/
 */

namespace CoenJacobs\Mozart\Composer\Autoload;

class Psr0 extends NamespaceAutoloader
{

    public function getNamespacePath(): string
    {
        return '';
    }
}
