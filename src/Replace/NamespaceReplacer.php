<?php

namespace CoenJacobs\Mozart\Replace;

use Exception;

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
     */
    public function replace(string $contents, string $file = null): string
    {
        $searchNamespace = preg_quote($this->autoloader->getSearchNamespace(), '/');
        $dependencyNamespace = preg_quote($this->dep_namespace, '/');

        $replaced = preg_replace_callback(
            "
            /                                # Start the pattern
              ([^a-zA-Z0-9_\x7f-\xff])       # Match the non-class character before the namespace
                (                            # Start the namespace matcher
                  (?<!$dependencyNamespace)  # Does NOT start with the prefix
                  (?<![a-zA-Z0-9_]\\\\)      # Not a class-allowed character followed by a slash
                  (?<!class\s)				 # Not a class declaration.
                  $searchNamespace           # The namespace we're looking for
                  [\\\|;\s]                  # Backslash, pipe, semicolon, or space
                )                            # End the namespace matcher
            /Ux",
            function ($matches) {
                return $matches[1] . $this->dep_namespace . $matches[2];
            },
            $contents
        );

        if (empty($replaced)) {
            throw new Exception('Failed to replace contents of the file.');
        }

        return $replaced;
    }
}
