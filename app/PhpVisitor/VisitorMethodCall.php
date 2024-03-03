<?php

namespace App\PhpVisitor;

use PhpParser\Node;
use PhpParser\PrettyPrinter;

class VisitorMethodCall extends Visitor {

    protected PrettyPrinter\Standard $printer;
    protected array $use = [];
    protected string $parent = '';
    protected string $method = '';
    protected array $methodTypes = [];
    protected array $visited = [];

    public function __construct($methods)
    {
        parent::__construct($methods);
        $this->printer = new PrettyPrinter\Standard;
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->useNamespace($node->name);
        }
        if ($node instanceof Node\Stmt\Class_) {
            if (isset($node->namespacedName)) {
                $this->useClass($node->namespacedName);
            }
            if (isset($node->extends->name)) {
                $this->parent = $node->extends->name;
                $this->useClass($node->extends->name);
            }
        }
        if ($node instanceof Node\Stmt\Use_) {
            $this->useClass($node->uses[0]->name->name);
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->method = $node->name->name;
            $this->methodTypes = [];
            foreach ($node->params as $param) {
                if (isset($param->type->name)) {
                    $this->methodTypes[$param->var->name] = $param->type->name;
                }
            }
        }

        if ($node instanceof Node\Expr\Assign) {
            if ($node->expr instanceof Node\Expr\New_) {
                $this->methodTypes[substr($this->printer->prettyPrintExpr($node->var), 1)] = $node->expr->class->name;
            } else {
                $expr = $node->expr;
                while (isset($expr->var)){
                    $expr = $expr->var;
                }
                if (isset($expr->class->name)) {
                    $this->methodTypes[substr($this->printer->prettyPrintExpr($node->var), 1)] = $expr->class->name;
                }
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            
            $namespaceClass = $this->findMethodClass($node);
            $namespace = null;
            $className = null;
            if (!is_null($namespaceClass)) {
                $namespace = explode('\\', $namespaceClass);
                $className = array_pop($namespace);
                $namespace = implode('\\', $namespace);
            }

            $this->visited[] = [
                self::FROM => [
                    self::METHOD => $this->method,
                ],
                self::TO => [
                    self::NAMESPACE => $namespace,
                    self::CLASSE => $className,
                    self::METHOD =>  $node->name->name ?? '/!\\ Dynamic call `$var->{$funcName}()` /!\\',
                ],
            ];
        }
    }

    private function useNamespace(string $namespace)
    {
        $methods = array_filter($this->methods, function($item) use ($namespace) {
            return $item[self::NAMESPACE] == $namespace;
        });
        $this->use = array_merge($this->use, $methods);
    }
    private function useClass(string $namespaceClass)
    {
        $methods = array_filter($this->methods, function($item) use ($namespaceClass) {
            return $item[self::NAMESPACE_CLASS] == $namespaceClass;
        });
        $this->use = array_merge($this->use, $methods);
    }

    private function findMethodClass(Node $node)
    {

        // Check if only one method has this name
        // Based on "use" statements, then on every listed methods
        // Notice: it's NOT perfect, that's approximative but easy
        foreach ([$this->use, $this->methods] as $scope) {
            $possibleMethods = array_filter($scope, function($item) use ($node) {
                return isset($node->name->name) && // null on dynamic calls: $var->{$funcName}()
                    mb_strtolower($item[self::METHOD]) == mb_strtolower($node->name->name);
            });
            if (count($possibleMethods) == 1) {
                return array_shift($possibleMethods)[self::NAMESPACE_CLASS];
            }
        }

        // Find static class call
        $var = $node;
        while (isset($var->var)){
            $var = $var->var;
            if (isset($var->class->name)) {
                return $var->class->name;
            }
        }

        // Namespaced name is set
        if (isset($var->name->namespacedName->name)) {
            return $var->name->namespacedName->name;
        }

        // Code analysis of the method give the answer
        if (isset($this->methodTypes[(string) $var->name])) {
            return $this->methodTypes[(string) $var->name];
        }

        // Call method of THIS class
        // Notice: traits are not managed
        if($var->name == 'this'){
            $thisClass = $this->namespace . '\\' . $this->class;
            if (isset($this->methods[$thisClass . '@' . $node->name->name])) {
                return $thisClass;
            }
            if (isset($this->methods[$this->parent . '@' . $node->name->name])) {
                return $this->parent;
            }
        }

        return null;
    }

}
