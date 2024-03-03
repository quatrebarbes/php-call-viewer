<?php

namespace App\PhpVisitor;

use PhpParser\Node;

class VisitorNewCall extends Visitor {

    protected string $method = '';
    protected array $visited = [];

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->method = $node->name->name;
        }

        if ($node instanceof Node\Expr\New_) {

            $namespace = explode('\\', $node->class->name);
            $className = array_pop($namespace);

            $this->visited[] = [
                self::FROM => [
                    self::METHOD => $this->method,
                ],
                self::TO => [
                    self::NAMESPACE => implode('\\', $namespace),
                    self::CLASSE => $className,
                    self::METHOD => '__construct',
                ],
            ];
        }
    }
}
