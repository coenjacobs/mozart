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
 * Handles: function_exists(), class_exists(), interface_exists(), trait_exists(), constant()
 */
trait ExistenceCheckTrait
{
    /**
     * Parse an existence-check function call and extract its parts.
     *
     * For constant() calls, the ::CONST_NAME suffix is separated from the class/namespace value.
     *
     * @return array{argNode: String_, value: string, suffix: string}|null
     */
    protected function parseExistenceCheck(FuncCall $node): ?array
    {
        if (!$node->name instanceof Name) {
            return null;
        }

        $existenceChecks = ['function_exists', 'class_exists', 'interface_exists', 'trait_exists', 'constant'];
        $functionName = $node->name->toString();
        if (!in_array($functionName, $existenceChecks, true)) {
            return null;
        }

        if (count($node->args) === 0 || !$node->args[0] instanceof Arg) {
            return null;
        }

        $firstArgValue = $node->args[0]->value;
        if (!$firstArgValue instanceof String_) {
            return $this->parseConstantConcat($functionName, $firstArgValue);
        }

        $value = $firstArgValue->value;
        $suffix = '';

        // For constant() calls, strip the ::CONST_NAME suffix before matching
        if ($functionName === 'constant' && str_contains($value, '::')) {
            $separatorPos = (int) strpos($value, '::');
            $suffix = substr($value, $separatorPos);
            $value = substr($value, 0, $separatorPos);
        }

        return [
            'argNode' => $firstArgValue,
            'value'   => $value,
            'suffix'  => $suffix,
        ];
    }

    /**
     * Handle concatenation in constant() calls: constant('Namespace\Class::' . $var)
     *
     * @return array{argNode: String_, value: string, suffix: string}|null
     */
    private function parseConstantConcat(string $functionName, Expr $argValue): ?array
    {
        if ($functionName !== 'constant') {
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
