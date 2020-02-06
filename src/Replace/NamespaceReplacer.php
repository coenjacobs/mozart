<?php

namespace CoenJacobs\Mozart\Replace;

class NamespaceReplacer extends BaseReplacer
{
    /** @var string */
    public $dep_namespace = '';

    public function replace($contents)
    {
        $searchNamespace = $this->autoloader->getSearchNamespace();
        return preg_replace_callback(
            '/([^\\?])(' . addslashes($searchNamespace) . '[\\\|;])/U',
            function ($matches) {
                // If it matches any of the namespaces to skip, then do nothing
                foreach ($this->namespacesToSkip as $namespaceToSkip) {
                    if (strlen($matches[2]) >= strlen($namespaceToSkip) && substr($matches[2], 0, strlen($namespaceToSkip)) == $namespaceToSkip) {
                        return $matches[1] . $matches[2];
                    }
                }
                return $matches[1] . $this->dep_namespace . $matches[2];
            },
            $contents
        );
    }
}
