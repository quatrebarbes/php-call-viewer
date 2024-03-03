<?php

namespace App\PhpVisitor;

use PhpParser\Node;

class VisitorStaticCall extends Visitor {

    protected string $parent = '';
    protected string $method = '';
    protected array $visited = [];

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if (
            $node instanceof Node\Stmt\Class_ &&
            isset($node->extends->name)
        ) {
            $this->parent = $node->extends->name;
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->method = $node->name->name;
        }

        if (
            $node instanceof Node\Expr\ClassConstFetch ||
            $node instanceof Node\Expr\StaticCall
        ) {

            $namespaceClass = $node->class->name;
            if ($namespaceClass == 'self') {
                $namespaceClass = $this->namespace . '\\' . $this->class;
            } elseif ($namespaceClass == 'parent') {
                $namespaceClass = $this->parent;
            }

            $namespace = explode('\\', $namespaceClass);
            $className = array_pop($namespace);

            $this->visited[] = [
                self::FROM => [
                    self::METHOD => $this->method,
                ],
                self::TO => [
                    self::NAMESPACE => implode('\\', $namespace),
                    self::CLASSE => $className,
                    self::METHOD => $node->name->name,
                ],
            ];
        }
    }
}
