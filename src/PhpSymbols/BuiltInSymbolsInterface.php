<?php

namespace CoenJacobs\Mozart\PhpSymbols;

interface BuiltInSymbolsInterface
{
    /**
     * Check if a class name is a PHP built-in class.
     */
    public function isBuiltInClass(string $name): bool;

    /**
     * Check if an interface name is a PHP built-in interface.
     */
    public function isBuiltInInterface(string $name): bool;

    /**
     * Check if a trait name is a PHP built-in trait.
     */
    public function isBuiltInTrait(string $name): bool;

    /**
     * Check if an enum name is a PHP built-in enum.
     */
    public function isBuiltInEnum(string $name): bool;

    /**
     * Check if a name is any PHP built-in type (class, interface, trait, or enum).
     */
    public function isBuiltInType(string $name): bool;

    /**
     * Check if a function name is a PHP built-in function.
     */
    public function isBuiltInFunction(string $name): bool;

    /**
     * Check if a constant name is a PHP built-in constant.
     */
    public function isBuiltInConstant(string $name): bool;
}
