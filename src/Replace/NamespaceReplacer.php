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
            '/' . addslashes($searchNamespace) . '([\\\|;])/U',
            function ($matches) {
                $replace = trim($matches[0], $matches[1]);
                return $this->dep_namespace . $replace . $matches[1];
            },
            $contents
        );
    }
}
