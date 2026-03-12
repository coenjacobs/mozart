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
 * (from the constant map), function call references (from the function map),
 * and string literals in existence checks.
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
     * Lowercase-keyed version of classMap for case-insensitive lookups.
     *
     * @var array<string,string>
     */
    protected array $classMapLower;

    /**
     * Map of original constant names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $constantMap;

    /**
     * Map of original function names to their prefixed versions.
     *
     * @var array<string,string>
     */
    protected array $functionMap;

    /**
     * Lowercase-keyed version of functionMap for case-insensitive lookups.
     *
     * @var array<string,string>
     */
    protected array $functionMapLower;

    /**
     * @param array<string,string> $classMap Map of original => prefixed class names
     * @param array<string,string> $constantMap Map of original => prefixed constant names
     * @param array<string,string> $functionMap Map of original => prefixed function names
     */
    public function __construct(array $classMap, array $constantMap = [], array $functionMap = [])
    {
        $this->classMap = $classMap;
        $this->classMapLower = array_change_key_case($classMap, CASE_LOWER);
        $this->constantMap = $constantMap;
        $this->functionMap = $functionMap;
        $this->functionMapLower = array_change_key_case($functionMap, CASE_LOWER);
    }

    /**
     * Process a node after its children have been visited.
     */
    public function leaveNode(Node $node): ?Node
    {
        // Handle function calls: both existence checks and function map replacements
        if ($node instanceof FuncCall) {
            return $this->processFuncCall($node);
        }

        // Handle constant references (e.g. MY_CONST, \MY_CONST)
        if ($node instanceof ConstFetch) {
            return $this->processConstFetch($node);
        }

        if ($node instanceof Name) {
            return $this->processNameNode($node);
        }

        return null;
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
     * Process function call nodes.
     *
     * Handles both existence checks (string argument replacement) and
     * direct function call name replacement from the function map.
     */
    protected function processFuncCall(FuncCall $node): ?FuncCall
    {
        $modified = false;

        // Check for existence check string arguments first
        $existenceResult = $this->processExistenceCheck($node);
        if ($existenceResult !== null) {
            $modified = true;
        }

        // Check if the function name itself is in our function map
        if ($node->name instanceof Name) {
            $nameStr = $node->name->toString();

            $nameLower = strtolower($nameStr);
            if (!str_contains($nameStr, '\\') && isset($this->functionMapLower[$nameLower])) {
                $prefixedName = $this->functionMapLower[$nameLower];

                $node->name = $node->name->isFullyQualified()
                    ? new Name\FullyQualified($prefixedName)
                    : new Name($prefixedName);

                $modified = true;
            }
        }

        return $modified ? $node : null;
    }

    /**
     * Process function calls that check for existence of classes/constants/functions.
     *
     * Uses ExistenceCheckTrait::parseExistenceCheck() for parsing, then applies
     * replacement if the value matches a mapped class, constant, or function name.
     */
    protected function processExistenceCheck(FuncCall $node): ?FuncCall
    {
        if (!$node->name instanceof Name) {
            return null;
        }

        $allParsed = $this->parseAllExistenceChecks($node);
        $modified = false;
        $functionName = strtolower($node->name->toString());

        foreach ($allParsed as $parsed) {
            $replacement = $this->getExistenceCheckReplacement(
                $functionName,
                $parsed['value'],
                $parsed['suffix']
            );

            if ($replacement !== null) {
                $parsed['argNode']->value = $replacement . $parsed['suffix'];
                $modified = true;
            }
        }

        return $modified ? $node : null;
    }

    /**
     * Process Name nodes that should use the class map.
     */
    protected function processNameNode(Name $node): ?Name
    {
        // Skip names that are part of namespace or use statements
        if ($this->isPartOfNamespaceOrUseStatement($node)) {
            return null;
        }

        if ($this->shouldDeferNameReplacementToParent($node)) {
            return null;
        }

        $nameStr = $node->toString();

        // Only process simple names (no namespace separator)
        // Namespaced references are handled by PrefixVisitor
        if (str_contains($nameStr, '\\')) {
            return null;
        }

        $prefixedName = $this->classMapLower[strtolower($nameStr)] ?? null;
        if ($prefixedName === null) {
            return null;
        }

        if ($node->isFullyQualified()) {
            return new Name\FullyQualified($prefixedName);
        }

        return new Name($prefixedName);
    }

    /**
     * Skip Name nodes whose parent node applies a more specific symbol map.
     */
    protected function shouldDeferNameReplacementToParent(Name $node): bool
    {
        $parent = $node->getAttribute('parent');

        if ($parent instanceof FuncCall && $node === $parent->name) {
            return true;
        }

        return $parent instanceof ConstFetch && $node === $parent->name;
    }

    /**
     * Resolve the correct replacement map for an existence-check argument.
     */
    protected function getExistenceCheckReplacement(string $functionName, string $value, string $suffix): ?string
    {
        if ($this->shouldUseClassMapForExistenceCheck($functionName, $suffix)) {
            return $this->classMapLower[strtolower($value)] ?? null;
        }

        if ($this->shouldUseConstantMapForExistenceCheck($functionName)) {
            return $this->constantMap[$value] ?? null;
        }

        if ($this->shouldUseFunctionMapForExistenceCheck($functionName, $suffix)) {
            return $this->functionMapLower[strtolower($value)] ?? null;
        }

        return null;
    }

    /**
     * Determine whether an existence-check argument should use the class map.
     */
    protected function shouldUseClassMapForExistenceCheck(string $functionName, string $suffix): bool
    {
        if ($suffix !== '') {
            return in_array($functionName, ['defined', 'constant', 'is_callable'], true);
        }

        return in_array(
            $functionName,
            [
                'class_exists',
                'interface_exists',
                'trait_exists',
                'enum_exists',
                'method_exists',
                'property_exists',
                'is_a',
                'is_subclass_of',
                'class_alias',
            ],
            true
        );
    }

    /**
     * Determine whether an existence-check argument should use the constant map.
     */
    protected function shouldUseConstantMapForExistenceCheck(string $functionName): bool
    {
        return in_array($functionName, ['define', 'defined', 'constant'], true);
    }

    /**
     * Determine whether an existence-check argument should use the function map.
     */
    protected function shouldUseFunctionMapForExistenceCheck(string $functionName, string $suffix): bool
    {
        if ($suffix !== '') {
            return false;
        }

        return in_array($functionName, ['function_exists', 'is_callable'], true);
    }
}
