<?php

namespace CoenJacobs\Mozart\Replace\Support;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * Trait providing existence-check function call parsing for AST visitors.
 *
 * Handles functions that accept fully-qualified class, function, or constant names as string
 * arguments: function_exists(), class_exists(), interface_exists(), trait_exists(), enum_exists(),
 * constant(), defined(), method_exists(), property_exists(), is_a(), is_subclass_of(),
 * is_callable(), class_alias()
 */
trait ExistenceCheckTrait
{
    /**
     * Parse an existence-check function call and extract its parts.
     *
     * For functions that accept Class::member strings (constant, defined, is_callable),
     * the ::member suffix is separated from the class/namespace value.
     *
     * Returns only the first matching argument (backward compatibility).
     *
     * @return array{argNode: String_, value: string, suffix: string}|null
     */
    protected function parseExistenceCheck(FuncCall $node): ?array
    {
        $results = $this->parseAllExistenceChecks($node);
        return $results[0] ?? null;
    }

    /**
     * Parse all matching arguments from an existence-check function call.
     *
     * For functions like class_alias that have multiple string arguments to process,
     * this returns one result per matching argument.
     *
     * @return array<int, array{argNode: String_, value: string, suffix: string}>
     */
    protected function parseAllExistenceChecks(FuncCall $node): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();
        $argIndices = $this->getExistenceCheckArgIndices($functionName);
        if ($argIndices === null) {
            return [];
        }

        $results = [];
        foreach ($argIndices as $argIndex) {
            if (count($node->args) <= $argIndex || !$node->args[$argIndex] instanceof Arg) {
                continue;
            }

            $argValue = $node->args[$argIndex]->value;
            if (!$argValue instanceof String_) {
                $parsed = $this->parseDoubleColonConcat($functionName, $argValue);
                if ($parsed !== null) {
                    $results[] = $parsed;
                }
                continue;
            }

            $results[] = $this->extractValueAndSuffix($functionName, $argValue);
        }

        return $results;
    }

    /**
     * Get the argument indices for a known existence-check function, or null if not recognized.
     *
     * @return int[]|null
     */
    private function getExistenceCheckArgIndices(string $functionName): ?array
    {
        $map = [
            'function_exists'  => [0],
            'class_exists'     => [0],
            'interface_exists' => [0],
            'trait_exists'     => [0],
            'enum_exists'      => [0],
            'constant'         => [0],
            'defined'          => [0],
            'method_exists'    => [0],
            'property_exists'  => [0],
            'is_callable'      => [0],
            'is_a'             => [1],
            'is_subclass_of'   => [1],
            'class_alias'      => [0, 1],
        ];

        return $map[$functionName] ?? null;
    }

    /**
     * Check whether a function accepts double-colon (::) syntax in its string argument.
     */
    private function supportsDoubleColon(string $functionName): bool
    {
        return in_array($functionName, ['constant', 'defined', 'is_callable'], true);
    }

    /**
     * Extract value and suffix from a String_ argument node.
     *
     * @return array{argNode: String_, value: string, suffix: string}
     */
    private function extractValueAndSuffix(string $functionName, String_ $argNode): array
    {
        $value = $argNode->value;
        $suffix = '';

        if ($this->supportsDoubleColon($functionName) && str_contains($value, '::')) {
            $separatorPos = (int) strpos($value, '::');
            $suffix = substr($value, $separatorPos);
            $value = substr($value, 0, $separatorPos);
        }

        return [
            'argNode' => $argNode,
            'value'   => $value,
            'suffix'  => $suffix,
        ];
    }

    /**
     * Handle concatenation in double-colon functions: constant('Namespace\Class::' . $var)
     *
     * @return array{argNode: String_, value: string, suffix: string}|null
     */
    private function parseDoubleColonConcat(string $functionName, Expr $argValue): ?array
    {
        if (!$this->supportsDoubleColon($functionName)) {
            return null;
        }

        if (!$argValue instanceof Concat || !$argValue->left instanceof String_) {
            return null;
        }

        if (!str_ends_with($argValue->left->value, '::')) {
            return null;
        }

        return [
            'argNode' => $argValue->left,
            'value'   => substr($argValue->left->value, 0, -2),
            'suffix'  => '::',
        ];
    }
}
