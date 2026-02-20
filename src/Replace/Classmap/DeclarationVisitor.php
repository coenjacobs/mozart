<?php

namespace CoenJacobs\Mozart\Replace\Classmap;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that renames class/interface/trait/enum declarations with a prefix.
 *
 * Only renames declarations in the global namespace (not inside a namespace block).
 */
class DeclarationVisitor extends NodeVisitorAbstract
{
    protected string $prefix;

    /** @var array<string,string> Map of original => prefixed names */
    protected array $replacedClasses = [];

    protected bool $inNamespace = false;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->replacedClasses = [];
        $this->inNamespace = false;
        return null;
    }

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
     * Renames class/interface/trait/enum declarations in the global namespace.
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

        if (
            $node instanceof Class_
            || $node instanceof Interface_
            || $node instanceof Trait_
            || $node instanceof Enum_
        ) {
            if ($node->name === null) {
                return null;
            }

            $originalName = $node->name->toString();
            $newName = $this->prefix . $originalName;
            $this->replacedClasses[$originalName] = $newName;
            $node->name = new Node\Identifier($newName);
            return $node;
        }

        return null;
    }

    /** @return array<string,string> */
    public function getReplacedClasses(): array
    {
        return $this->replacedClasses;
    }
}
