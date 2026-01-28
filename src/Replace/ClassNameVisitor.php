<?php

namespace CoenJacobs\Mozart\Replace;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that rewrites class references to use prefixed namespaces.
 *
 * This visitor traverses the AST and:
 * - Prefixes namespace declarations
 * - Prefixes use statements and tracks aliases
 * - Prefixes class references in type hints, extends, implements, instanceof, new, etc.
 * - Does NOT touch variable names, property names, or string literals
 */
class ClassNameVisitor extends NodeVisitorAbstract
{
    /**
     * The namespace prefix to add.
     */
    protected string $prefix;

    /**
     * Namespaces that should be prefixed.
     *
     * @var array<string>
     */
    protected array $targetNamespaces;

    /**
     * Aliases imported via use statements (maps alias => original FQN).
     *
     * @var array<string,string>
     */
    protected array $aliases = [];

    /**
     * The current namespace being processed.
     */
    protected ?string $currentNamespace = null;

    /**
     * @param string        $prefix           The prefix to add (e.g., "MyPlugin\Dependencies")
     * @param array<string> $targetNamespaces Namespaces to prefix (e.g., ["Invoker", "Psr\Container"])
     */
    public function __construct(string $prefix, array $targetNamespaces)
    {
        $this->prefix = trim($prefix, '\\');
        $this->targetNamespaces = array_map(
            fn(string $namespace) => trim($namespace, '\\'),
            $targetNamespaces
        );
    }

    /**
     * Reset state before traversing a new AST.
     *
     * @param array<Node> $nodes The AST nodes to traverse
     */
    public function beforeTraverse(array $nodes): ?array
    {
        unset($nodes);
        $this->aliases = [];
        $this->currentNamespace = null;
        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        // Track the current namespace
        if ($node instanceof Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : null;
        }

        return null;
    }

    /**
     * Process a node after its children have been visited.
     *
     * Handles namespace declarations, use statements, and class name references.
     */
    public function leaveNode(Node $node): ?Node
    {
        // Handle namespace declarations - prefix them
        if ($node instanceof Namespace_ && $node->name !== null) {
            if ($this->shouldPrefixNamespace($node->name->toString())) {
                $newName = $this->prefix . '\\' . $node->name->toString();
                $node->name = new Name($newName);
                return $node;
            }
        }

        // Handle use statements - prefix them and track aliases
        if ($node instanceof Use_) {
            return $this->processUseStatement($node);
        }

        // Handle all Name nodes (class references in various contexts)
        if ($node instanceof Name) {
            return $this->processName($node);
        }

        return null;
    }

    /**
     * Process a use statement - prefix if needed and track aliases.
     */
    protected function processUseStatement(Use_ $node): ?Use_
    {
        $modified = false;

        foreach ($node->uses as $use) {
            $originalName = $use->name->toString();

            // Track the alias (the local name that will be used in code)
            $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
            $this->aliases[$alias] = $originalName;

            // Prefix the use statement if needed
            if ($this->shouldPrefixNamespace($originalName)) {
                $newName = $this->prefix . '\\' . $originalName;
                $use->name = new Name($newName);
                $modified = true;
            }
        }

        return $modified ? $node : null;
    }

    /**
     * Process a Name node (class reference).
     */
    protected function processName(Name $node): ?Name
    {
        if ($this->shouldSkipName($node)) {
            return null;
        }

        $resolvedName = $this->resolveClassName($node);
        if ($resolvedName === null || !$this->shouldPrefixNamespace($resolvedName)) {
            return null;
        }

        return $this->createPrefixedName($node, $resolvedName);
    }

    /**
     * Check if a Name node should be skipped (not processed).
     */
    protected function shouldSkipName(Name $node): bool
    {
        $parent = $node->getAttribute('parent');

        // Skip if this is a namespace declaration (handled separately)
        if ($parent instanceof Namespace_) {
            return true;
        }

        // Skip if this is part of a use statement (handled separately)
        if ($parent instanceof UseUse || $parent instanceof Use_) {
            return true;
        }

        $nameStr = $node->toString();

        // If this is a simple name that matches a tracked alias, skip it
        // (it will resolve through the prefixed use statement)
        if (!str_contains($nameStr, '\\') && isset($this->aliases[$nameStr])) {
            return true;
        }

        return false;
    }

    /**
     * Create a prefixed name from the original node and resolved name.
     */
    protected function createPrefixedName(Name $node, string $resolvedName): Name
    {
        $prefixedName = $this->prefix . '\\' . $resolvedName;

        if ($node->isFullyQualified()) {
            return new Name\FullyQualified($prefixedName);
        }

        return new Name($prefixedName);
    }

    /**
     * Resolve a class name to its fully qualified form.
     */
    protected function resolveClassName(Name $node): ?string
    {
        $nameStr = $node->toString();

        // If fully qualified, strip the leading backslash for comparison
        if ($node->isFullyQualified()) {
            return $nameStr;
        }

        // If unqualified (no backslash), check aliases first
        if (!str_contains($nameStr, '\\')) {
            if (isset($this->aliases[$nameStr])) {
                return $this->aliases[$nameStr];
            }
            // If in a namespace context and not an alias, could be a relative reference
            // but we don't want to prefix things that aren't in target namespaces
            return $nameStr;
        }

        // Qualified but not fully qualified (has backslash but no leading backslash)
        // This is relative to current namespace or a use alias
        $parts = explode('\\', $nameStr);
        $firstPart = $parts[0];

        // Check if first part is an alias
        if (isset($this->aliases[$firstPart])) {
            // Replace the alias with the original and resolve
            $parts[0] = $this->aliases[$firstPart];
            return implode('\\', $parts);
        }

        return $nameStr;
    }

    /**
     * Check if a namespace should be prefixed.
     */
    protected function shouldPrefixNamespace(string $namespace): bool
    {
        $namespace = trim($namespace, '\\');

        // Check if it already has the prefix
        if (str_starts_with($namespace, $this->prefix . '\\')) {
            return false;
        }

        // Check if it's exactly the prefix
        if ($namespace === $this->prefix) {
            return false;
        }

        foreach ($this->targetNamespaces as $target) {
            // Exact match
            if ($namespace === $target) {
                return true;
            }

            // Starts with target namespace (sub-namespace)
            if (str_starts_with($namespace, $target . '\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the tracked aliases.
     *
     * @return array<string,string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }
}
