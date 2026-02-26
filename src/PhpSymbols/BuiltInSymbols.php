<?php

namespace CoenJacobs\Mozart\PhpSymbols;

class BuiltInSymbols implements BuiltInSymbolsInterface
{
    /** @var array{classes: array<string, bool>, interfaces: array<string, bool>, traits: array<string, bool>, enums: array<string, bool>, functions: array<string, bool>, constants: array<string, bool>} */
    private array $symbols;

    /**
     * @param string|null $dataFile Path to the PHP symbols data file.
     *                              Defaults to the bundled data file.
     */
    public function __construct(?string $dataFile = null)
    {
        $dataFile = $dataFile ?? __DIR__ . '/data/php-symbols.php';
        $symbols = require $dataFile;

        // Lowercase keys for case-insensitive lookups (PHP symbols are case-insensitive
        // except constants). Constants stay as-is.
        $symbols['classes'] = array_change_key_case($symbols['classes'], CASE_LOWER);
        $symbols['interfaces'] = array_change_key_case($symbols['interfaces'], CASE_LOWER);
        $symbols['traits'] = array_change_key_case($symbols['traits'], CASE_LOWER);
        $symbols['enums'] = array_change_key_case($symbols['enums'], CASE_LOWER);
        $symbols['functions'] = array_change_key_case($symbols['functions'], CASE_LOWER);

        $this->symbols = $symbols;
    }

    /**
     * Check if a class name is a PHP built-in class.
     */
    public function isBuiltInClass(string $name): bool
    {
        return isset($this->symbols['classes'][strtolower($name)]);
    }

    /**
     * Check if an interface name is a PHP built-in interface.
     */
    public function isBuiltInInterface(string $name): bool
    {
        return isset($this->symbols['interfaces'][strtolower($name)]);
    }

    /**
     * Check if a trait name is a PHP built-in trait.
     */
    public function isBuiltInTrait(string $name): bool
    {
        return isset($this->symbols['traits'][strtolower($name)]);
    }

    /**
     * Check if an enum name is a PHP built-in enum.
     */
    public function isBuiltInEnum(string $name): bool
    {
        return isset($this->symbols['enums'][strtolower($name)]);
    }

    /**
     * Check if a name is any PHP built-in type (class, interface, trait, or enum).
     */
    public function isBuiltInType(string $name): bool
    {
        return $this->isBuiltInClass($name)
            || $this->isBuiltInInterface($name)
            || $this->isBuiltInTrait($name)
            || $this->isBuiltInEnum($name);
    }

    /**
     * Check if a function name is a PHP built-in function.
     */
    public function isBuiltInFunction(string $name): bool
    {
        return isset($this->symbols['functions'][strtolower($name)]);
    }

    /**
     * Check if a constant name is a PHP built-in constant.
     */
    public function isBuiltInConstant(string $name): bool
    {
        return isset($this->symbols['constants'][$name]);
    }
}
