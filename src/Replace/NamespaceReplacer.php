<?php

namespace CoenJacobs\Mozart\Replace;

class NamespaceReplacer extends BaseReplacer
{
    /** @var string */
    public $dep_namespace = '';

    public function replace($contents, $file)
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
                  $searchNamespace           # The namespace we're looking for
                  [\\\|;]                    # Backslash, semicolon, or pipe
                )                            # End the namespace matcher
            /Ux",
            function ($matches) {
                return $matches[1] . $this->dep_namespace . $matches[2];
            },
            $contents
        );
    }
}
