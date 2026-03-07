<?php

namespace CoenJacobs\Mozart\Replace\GlobalScope;

use CoenJacobs\Mozart\PhpSymbols\BuiltInSymbolsInterface;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that renames global-scope declarations with a prefix.
 *
 * Handles class/interface/trait/enum declarations, constant declarations,
 * and function declarations. Only renames in the global namespace (not
 * inside a namespace block).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DeclarationVisitor extends NodeVisitorAbstract
{
    protected string $prefix;

    protected string $constantPrefix;

    protected string $functionsPrefix;

    protected BuiltInSymbolsInterface $builtInSymbols;

    /** @var array<string,string> Map of original => prefixed names */
    protected array $replacedClasses = [];

    /** @var array<string,string> Map of original => prefixed constant names */
    protected array $replacedConstants = [];

    /** @var array<string,string> Map of original => prefixed function names */
    protected array $replacedFunctions = [];

    protected bool $inNamespace = false;

    /**
     * @param string $prefix Prefix for class/interface/trait/enum declarations
     * @param BuiltInSymbolsInterface $builtInSymbols Built-in symbol database
     * @param string $constantPrefix Prefix for constant declarations
     * @param string $functionsPrefix Prefix for function declarations
     */
    public function __construct(
        string $prefix,
        BuiltInSymbolsInterface $builtInSymbols,
        string $constantPrefix = '',
        string $functionsPrefix = ''
    ) {
        $this->prefix = $prefix;
        $this->builtInSymbols = $builtInSymbols;
        $this->constantPrefix = $constantPrefix;
        $this->functionsPrefix = $functionsPrefix;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->replacedClasses = [];
        $this->replacedConstants = [];
        $this->replacedFunctions = [];
        $this->inNamespace = false;
        return null;
    }

    /**
     * Track whether we're inside a namespace block.
     */
    public function enterNode(Node $node): ?int
    {
        // Track if we're inside a namespace block
        if ($node instanceof Namespace_ && $node->name !== null) {
            $this->inNamespace = true;
            // Skip children of named namespaces
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
        return null;
    }

    /**
     * Process node after visiting its children.
     * Renames declarations in the global namespace.
     */
    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            $this->inNamespace = false;
            return null;
        }

        if ($this->inNamespace) {
            return null;
        }

        return $this->processClassDeclaration($node)
            ?? $this->processConstantDeclaration($node)
            ?? $this->processFunctionDeclaration($node);
    }

    /**
     * Rename class/interface/trait/enum declarations.
     */
    protected function processClassDeclaration(Node $node): ?Node
    {
        if (
            !($node instanceof Class_)
            && !($node instanceof Interface_)
            && !($node instanceof Trait_)
            && !($node instanceof Enum_)
        ) {
            return null;
        }

        if ($node->name === null) {
            return null;
        }

        $originalName = $node->name->toString();

        if ($this->builtInSymbols->isBuiltInType($originalName)) {
            return null;
        }

        $newName = $this->prefix . $originalName;
        $this->replacedClasses[$originalName] = $newName;
        $node->name = new Node\Identifier($newName);
        return $node;
    }

    /**
     * Rename constant declarations (const statements and define() calls).
     */
    protected function processConstantDeclaration(Node $node): ?Node
    {
        if (empty($this->constantPrefix)) {
            return null;
        }

        if ($node instanceof Const_) {
            return $this->processConstStatement($node);
        }

        if ($node instanceof FuncCall) {
            return $this->processDefineCall($node);
        }

        return null;
    }

    /**
     * Rename function declarations.
     */
    protected function processFunctionDeclaration(Node $node): ?Node
    {
        if (empty($this->functionsPrefix)) {
            return null;
        }

        if (!$node instanceof Function_) {
            return null;
        }

        $originalName = $node->name->toString();

        if ($this->builtInSymbols->isBuiltInFunction($originalName)) {
            return null;
        }

        $newName = $this->functionsPrefix . $originalName;
        $this->replacedFunctions[$originalName] = $newName;
        $node->name = new Node\Identifier($newName);
        return $node;
    }

    /**
     * Process a const statement (const FOO = 1, BAR = 2;).
     */
    protected function processConstStatement(Const_ $node): ?Const_
    {
        $modified = false;

        foreach ($node->consts as $const) {
            $originalName = $const->name->toString();

            if ($this->builtInSymbols->isBuiltInConstant($originalName)) {
                continue;
            }

            $newName = $this->constantPrefix . $originalName;
            $this->replacedConstants[$originalName] = $newName;
            $const->name = new Node\Identifier($newName);
            $modified = true;
        }

        return $modified ? $node : null;
    }

    /**
     * Process a define('FOO', value) call.
     */
    protected function processDefineCall(FuncCall $node): ?FuncCall
    {
        if (!$node->name instanceof Name || strtolower($node->name->toString()) !== 'define') {
            return null;
        }

        if (count($node->args) < 2 || !$node->args[0] instanceof Arg) {
            return null;
        }

        $firstArg = $node->args[0]->value;

        if (!$firstArg instanceof String_) {
            return null;
        }

        $originalName = $firstArg->value;

        // Skip namespaced constants — they already have collision avoidance
        if (str_contains($originalName, '\\')) {
            return null;
        }

        if ($this->builtInSymbols->isBuiltInConstant($originalName)) {
            return null;
        }

        $newName = $this->constantPrefix . $originalName;
        $this->replacedConstants[$originalName] = $newName;
        $firstArg->value = $newName;
        return $node;
    }

    /** @return array<string,string> */
    public function getReplacedClasses(): array
    {
        return $this->replacedClasses;
    }

    /** @return array<string,string> */
    public function getReplacedConstants(): array
    {
        return $this->replacedConstants;
    }

    /** @return array<string,string> */
    public function getReplacedFunctions(): array
    {
        return $this->replacedFunctions;
    }
}
