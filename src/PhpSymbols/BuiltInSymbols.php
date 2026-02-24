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
        $this->symbols = require $dataFile;
    }

    /**
     * Check if a class name is a PHP built-in class.
     */
    public function isBuiltInClass(string $name): bool
    {
        return isset($this->symbols['classes'][$name]);
    }

    /**
     * Check if an interface name is a PHP built-in interface.
     */
    public function isBuiltInInterface(string $name): bool
    {
        return isset($this->symbols['interfaces'][$name]);
    }

    /**
     * Check if a trait name is a PHP built-in trait.
     */
    public function isBuiltInTrait(string $name): bool
    {
        return isset($this->symbols['traits'][$name]);
    }

    /**
     * Check if an enum name is a PHP built-in enum.
     */
    public function isBuiltInEnum(string $name): bool
    {
        return isset($this->symbols['enums'][$name]);
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
        return isset($this->symbols['functions'][$name]);
    }

    /**
     * Check if a constant name is a PHP built-in constant.
     */
    public function isBuiltInConstant(string $name): bool
    {
        return isset($this->symbols['constants'][$name]);
    }
}
