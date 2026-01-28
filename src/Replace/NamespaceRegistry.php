<?php

namespace CoenJacobs\Mozart\Replace;

/**
 * Registry for tracking namespace mappings during replacement.
 *
 * Maps original namespaces to their prefixed versions, allowing the AST-based
 * replacement to look up which namespaces need to be transformed.
 */
class NamespaceRegistry
{
    /**
     * Maps original namespaces to prefixed namespaces.
     *
     * @var array<string,string>
     */
    protected array $namespaces = [];

    /**
     * Register a namespace mapping.
     *
     * @param string $original The original namespace (e.g., "Invoker")
     * @param string $prefixed The prefixed namespace (e.g., "MyPlugin\Dependencies\Invoker")
     */
    public function register(string $original, string $prefixed): void
    {
        // Normalize by removing leading/trailing backslashes
        $original = trim($original, '\\');
        $prefixed = trim($prefixed, '\\');

        $this->namespaces[$original] = $prefixed;
    }

    /**
     * Check if a namespace has been registered.
     *
     * @param string $namespace The namespace to check
     */
    public function hasNamespace(string $namespace): bool
    {
        $namespace = trim($namespace, '\\');
        return isset($this->namespaces[$namespace]);
    }

    /**
     * Get the prefixed version of a namespace.
     *
     * @param string $namespace The original namespace
     * @return string|null The prefixed namespace, or null if not registered
     */
    public function getPrefixedNamespace(string $namespace): ?string
    {
        $namespace = trim($namespace, '\\');
        return $this->namespaces[$namespace] ?? null;
    }

    /**
     * Get all registered namespaces.
     *
     * @return array<string,string> Map of original => prefixed namespaces
     */
    public function getAll(): array
    {
        return $this->namespaces;
    }

    /**
     * Check if the registry is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->namespaces);
    }

    /**
     * Clear all registered namespaces.
     */
    public function clear(): void
    {
        $this->namespaces = [];
    }
}
