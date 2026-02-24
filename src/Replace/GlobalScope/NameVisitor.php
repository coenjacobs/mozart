<?php

namespace CoenJacobs\Mozart\Replace\GlobalScope;

use CoenJacobs\Mozart\Replace\Support\ExistenceCheckTrait;
use CoenJacobs\Mozart\Replace\Support\NameNodeContextTrait;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that replaces simple (non-namespaced) global-scope names.
 *
 * Handles class name references (from the class map), constant references
 * (from the constant map), and string literals in existence checks.
 *
 * Unlike PrefixVisitor which handles namespaced code, this visitor:
 * - Only replaces simple names (no namespace separator)
 * - Uses direct mappings of original => prefixed names
 * - Handles string literals in existence checks (class_exists, defined, etc.)
 */
class NameVisitor extends NodeVisitorAbstract
{
    use ExistenceCheckTrait;
    use NameNodeContextTrait;

    /**
     * Map of original class names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $classMap;

    /**
     * Map of original constant names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $constantMap;

    /**
     * @param array<string,string> $classMap Map of original => prefixed class names
     * @param array<string,string> $constantMap Map of original => prefixed constant names
     */
    public function __construct(array $classMap, array $constantMap = [])
    {
        $this->classMap = $classMap;
        $this->constantMap = $constantMap;
    }

    /**
     * Process a node after its children have been visited.
     */
    public function leaveNode(Node $node): ?Node
    {
        // Handle string literals in existence checks (class_exists, defined, etc.)
        if ($node instanceof FuncCall) {
            return $this->processExistenceCheck($node);
        }

        // Handle constant references (e.g. MY_CONST, \MY_CONST)
        if ($node instanceof ConstFetch) {
            return $this->processConstFetch($node);
        }

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
        // Namespaced references are handled by PrefixVisitor
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

    /**
     * Process constant fetch nodes (e.g. MY_CONST, \MY_CONST).
     */
    protected function processConstFetch(ConstFetch $node): ?ConstFetch
    {
        if (empty($this->constantMap)) {
            return null;
        }

        $nameStr = $node->name->toString();

        // Only process simple names (no namespace separator after stripping leading \)
        if (str_contains($nameStr, '\\')) {
            return null;
        }

        if (!isset($this->constantMap[$nameStr])) {
            return null;
        }

        $prefixedName = $this->constantMap[$nameStr];

        if ($node->name->isFullyQualified()) {
            $node->name = new Name\FullyQualified($prefixedName);
            return $node;
        }

        $node->name = new Name($prefixedName);
        return $node;
    }

    /**
     * Process function calls that check for existence of classes/constants/etc.
     *
     * Uses ExistenceCheckTrait::parseExistenceCheck() for parsing, then applies
     * replacement if the value matches a mapped class or constant name.
     */
    protected function processExistenceCheck(FuncCall $node): ?FuncCall
    {
        $allParsed = $this->parseAllExistenceChecks($node);
        $modified = false;

        foreach ($allParsed as $parsed) {
            $value = $parsed['value'];

            if (isset($this->classMap[$value])) {
                $parsed['argNode']->value = $this->classMap[$value] . $parsed['suffix'];
                $modified = true;
            } elseif (isset($this->constantMap[$value])) {
                $parsed['argNode']->value = $this->constantMap[$value] . $parsed['suffix'];
                $modified = true;
            }
        }

        return $modified ? $node : null;
    }
}
