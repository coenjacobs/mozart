<?php
/**
 * @see https://www.php-fig.org/psr/psr-4/
 */

namespace CoenJacobs\Mozart\Composer\Autoload;

class Psr4 extends NamespaceAutoloader
{

    public function getSearchNamespace(): string
    {
        return trim($this->namespace, '\\');
    }

    public function getNamespacePath(): string
    {
        return str_replace('\\', DIRECTORY_SEPARATOR, $this->namespace);
    }
}
