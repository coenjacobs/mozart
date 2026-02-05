<?php

namespace CoenJacobs\Mozart\Replace;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that replaces simple (non-namespaced) class names with prefixed versions.
 *
 * This is used to update references to classmap classes (global namespace classes)
 * that have been prefixed during the Mozart replacement process.
 *
 * Unlike ClassNameVisitor which handles namespaced code, this visitor:
 * - Only replaces simple class names (no namespace separator)
 * - Uses a direct mapping of original => prefixed names
 * - Does NOT modify string literals or comments (AST doesn't include them as Name nodes)
 */
class ClassmapNameVisitor extends NodeVisitorAbstract
{
    use NameNodeContextTrait;

    /**
     * Map of original class names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $classMap;

    /**
     * @param array<string,string> $classMap Map of original => prefixed class names
     */
    public function __construct(array $classMap)
    {
        $this->classMap = $classMap;
    }

    /**
     * Process a node after its children have been visited.
     */
    public function leaveNode(Node $node): ?Node
    {
        // Only process Name nodes
        if (!$node instanceof Name) {
            return null;
        }

        // Skip names that are part of namespace or use statements
        if ($this->isPartOfNamespaceOrUseStatement($node)) {
            return null;
        }

        $nameStr = $node->toString();

        // Only process simple names (no namespace separator)
        // Namespaced references are handled by ClassNameVisitor
        if (str_contains($nameStr, '\\')) {
            return null;
        }

        // Check if this name is in our classmap
        if (!isset($this->classMap[$nameStr])) {
            return null;
        }

        // Replace with the prefixed version
        $prefixedName = $this->classMap[$nameStr];

        if ($node->isFullyQualified()) {
            return new Name\FullyQualified($prefixedName);
        }

        return new Name($prefixedName);
    }
}
