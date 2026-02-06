<?php

namespace CoenJacobs\Mozart\Replace\Namespace;

use CoenJacobs\Mozart\Replace\Support\ExistenceCheckTrait;
use CoenJacobs\Mozart\Replace\Support\NameNodeContextTrait;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that rewrites class references to use prefixed namespaces.
 *
 * This visitor traverses the AST and:
 * - Prefixes namespace declarations
 * - Prefixes use statements and tracks aliases
 * - Prefixes class references in type hints, extends, implements, instanceof, new, etc.
 * - Handles string literals in existence checks (function_exists, class_exists, etc.)
 * - Does NOT touch variable names, property names, or other string literals
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PrefixVisitor extends NodeVisitorAbstract
{
    use ExistenceCheckTrait;
    use NameNodeContextTrait;

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
     * @param array<Node> $nodes The AST nodes (unused, required by interface)
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeTraverse(array $nodes): ?array
    {
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

        // Handle string literals in existence checks (function_exists, class_exists, etc.)
        if ($node instanceof FuncCall) {
            return $this->processExistenceCheck($node);
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
        if ($this->isPartOfNamespaceOrUseStatement($node)) {
            return true;
        }

        return $this->isUnqualifiedNameInPrefixedNamespace($node);
    }

    /**
     * Check if node is an unqualified name that will resolve via namespace.
     */
    protected function isUnqualifiedNameInPrefixedNamespace(Name $node): bool
    {
        $nameStr = $node->toString();

        // Only check simple names (no backslash)
        if (str_contains($nameStr, '\\') || $node->isFullyQualified()) {
            return false;
        }

        // If this name matches a tracked alias, skip it
        // (it will resolve through the prefixed use statement)
        if (isset($this->aliases[$nameStr])) {
            return true;
        }

        // If we're inside a namespace that's being prefixed,
        // unqualified names will resolve correctly via the prefixed namespace
        return $this->isCurrentNamespaceBeingPrefixed();
    }

    /**
     * Check if the current namespace is in the list of namespaces being prefixed.
     */
    protected function isCurrentNamespaceBeingPrefixed(): bool
    {
        if ($this->currentNamespace === null) {
            return false;
        }

        return $this->shouldPrefixNamespace($this->currentNamespace);
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

    /**
     * Process function calls that check for existence of classes/functions/etc.
     *
     * Uses ExistenceCheckTrait::parseExistenceCheck() for parsing, then applies
     * namespace prefix if the value matches a target namespace.
     */
    protected function processExistenceCheck(FuncCall $node): ?FuncCall
    {
        $allParsed = $this->parseAllExistenceChecks($node);
        $modified = false;

        foreach ($allParsed as $parsed) {
            if ($this->shouldPrefixNamespace($parsed['value'])) {
                $parsed['argNode']->value = $this->prefix . '\\' . $parsed['value'] . $parsed['suffix'];
                $modified = true;
            }
        }

        return $modified ? $node : null;
    }
}
