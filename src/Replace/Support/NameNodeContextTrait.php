<?php

namespace CoenJacobs\Mozart\Replace\Support;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

/**
 * Trait providing context detection for Name nodes in AST traversal.
 */
trait NameNodeContextTrait
{
    /**
     * Check if node is part of a namespace or use statement.
     *
     * These are handled separately from general class name references.
     */
    protected function isPartOfNamespaceOrUseStatement(Name $node): bool
    {
        $parent = $node->getAttribute('parent');

        return $parent instanceof Namespace_
            || $parent instanceof UseUse
            || $parent instanceof Use_;
    }
}
