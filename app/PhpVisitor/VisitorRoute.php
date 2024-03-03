<?php

namespace App\PhpVisitor;

use PhpParser\Node;
use PhpParser\PrettyPrinter;

class VisitorRoute extends Visitor {

    protected array $visited = [];
    protected string $prefix = '';

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if (
            $node instanceof Node\Expr\MethodCall || // Route::get(...)
            $node instanceof Node\Expr\StaticCall // $route->get(...)
        ) {

            if (
                isset($node->name->name) &&
                $node->name->name == 'group' &&
                isset($node->args[0]->value->items)
            ) {
                foreach ($node->args[0]->value->items as $item) {
                    if ($item->key->value == 'prefix') {
                        $this->prefix = $item->value->value;
                    }
                }
            }

            if (
                isset($node->name->name) &&
                in_array($node->name->name, ['get', 'post', 'put', 'patch', 'delete']) &&
                isset($node->args[1]->value->value)
            ) {

                $classMethod = $node->args[1]->value->value;
                $namespace = array_filter($this->methods, function(array $method) use ($classMethod)
                {
                    return str_contains($method[self::NAMESPACE_CLASS_METHOD], $classMethod);
                });
                $namespace = array_shift($namespace);
                $namespace = $namespace[self::NAMESPACE] ?? null;
                $classMethod = explode('@', $classMethod);
                $className = $classMethod[0];
                $method = $classMethod[1];

                $route = $node->args[0]->value->value;
                if ($route[0] == '/') {
                    $route = substr($route, 1);
                }
                $route = $this->prefix . '/' . $route;
                
                $this->visited[] = [
                    self::FROM => [
                        self::NAMESPACE => 'REST',
                        self::CLASSE => $route,
                        self::METHOD => $node->name->name,
                    ],
                    self::TO => [
                        self::NAMESPACE => $namespace,
                        self::CLASSE => $className,
                        self::METHOD => $method,
                    ],
                ];
            }

        }

    }

}
