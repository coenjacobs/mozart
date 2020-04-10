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
	        '/([^a-zA-Z0-9_\x7f-\xff])((?<!' . addslashes( $this->dep_namespace ) . ' )' . addslashes($searchNamespace) . '[\\\|;])/U',
            function ($matches) {
                return $matches[1] . $this->dep_namespace . $matches[2];
            },
            $contents
        );
    }
}
