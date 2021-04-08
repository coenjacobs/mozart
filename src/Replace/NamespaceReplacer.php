<?php

namespace CoenJacobs\Mozart\Replace;

class NamespaceReplacer extends BaseReplacer
{
    /**
     * The prefix to add to existing namespaces.
     *
     * @var string "My\Mozart\Prefix".
     */
    public $dep_namespace = '';

    /**
     * @param string $contents The text to make replacements in.
     * @param null $file Only used in ClassmapReplacer (for recording which files were changed).
     *
     * @return string The updated text.
     */
    public function replace($contents, $file = null)
    {
        $searchNamespace = preg_quote($this->autoloader->getSearchNamespace(), '/');
        $dependencyNamespace = preg_quote($this->dep_namespace, '/');

        return preg_replace_callback(
            "
            /                                # Start the pattern
              ([^a-zA-Z0-9_\x7f-\xff])       # Match the non-class character before the namespace
                (                            # Start the namespace matcher
                  (?<!$dependencyNamespace)  # Does NOT start with the prefix
                  (?<![a-zA-Z0-9_]\\\\)      # Not a class-allowed character followed by a slash
                  (?<!class\s)				 # Not a class declaration
                  $searchNamespace			 # The namespace were looking for
                  (?=;|\\\|\||\sas)			 # Followed by one of
                  							 # semicolon when it is the class namespace itself
                  				             # backslash when it is part of a longer namespace or namespaced class
                  							 # pipe
                  							 # space followed by the as keyword
                )                            # End the namespace matcher
            /Ux", # U non-greedy matching by default, x ignore whitespace in regex.
            function ($matches) {
                return $matches[1] . $this->dep_namespace . $matches[2];
            },
            $contents
        );
    }
}
