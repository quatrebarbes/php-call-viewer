<?php

namespace App;

use App\PhpVisitor\Visitor;
use App\PhpVisitor\VisitorMethod;
use App\PhpVisitor\VisitorMethodCall;
use App\PhpVisitor\VisitorNewCall;
use App\PhpVisitor\VisitorRoute;
use App\PhpVisitor\VisitorStaticCall;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;

class PhpAnalysis
{

    private array $parsed = [];
    private ?array $classesCache = null;
    private ?array $methodsCache = null;
    private ?array $routesCache = null;
    private ?array $transitionsCache = null;

    public function __construct($repositoryFiles)
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        $this->parsed[] = [];
        foreach ($repositoryFiles as $repositoryFileName => $repositoryFileContent) {
            $stmts = $parser->parse($repositoryFileContent);

            $nodeTraverser = new NodeTraverser;
            $nodeTraverser->addVisitor(new NameResolver);
            $stmts = $nodeTraverser->traverse($stmts);
            
            $this->parsed[$repositoryFileName] = $stmts;
        }
    }

    public function callers(array $targetFunctions)
    {
        $res = [];
        
        $transitions = $this->transitions();
        for( $depth = 0; $depth < 10000; ++$depth) {

            $matchingTransition = array_filter($transitions, function($transition) use ($targetFunctions){
                foreach($targetFunctions as $targetFunction){
                    if(
                        mb_strtolower($targetFunction) == mb_strtolower($transition[Visitor::TO][Visitor::NAMESPACE_CLASS_METHOD]) ||
                        mb_strtolower($targetFunction) == mb_strtolower($transition[Visitor::TO][Visitor::NAMESPACE_CLASS])
                    ) {
                        return true;
                    }
                }
            });

            $resCount = count($res);

            $targetFunctions = [];
            foreach ($matchingTransition as $transition) {
                $res[$transition[Visitor::TO_STRING]] = $transition;
                $targetFunctions[] = $transition[Visitor::FROM][Visitor::NAMESPACE_CLASS_METHOD];
            }

            if ($resCount == count($res)) {
                break;
            }

        }

        return $res;
    }

    public function classes()
    {
        if (is_null($this->classesCache)) {
            $this->classesCache = [];
            $methods = $this->list(fn() => new VisitorMethod);
            foreach ($methods as $method) {
                $this->classesCache[$method[Visitor::NAMESPACE_CLASS]] = [
                    Visitor::NAMESPACE       => $method[Visitor::NAMESPACE],
                    Visitor::CLASSE          => $method[Visitor::CLASSE],
                    Visitor::NAMESPACE_CLASS => $method[Visitor::NAMESPACE_CLASS],
                ];
            }
        }

        return $this->classesCache;
    }

    public function methods()
    {
        if (is_null($this->methodsCache)) {
            $this->methodsCache = [];
            $rawMethods = $this->list(fn() => new VisitorMethod);
            foreach ($rawMethods as $rawMethod) {
                $this->methodsCache[$rawMethod[Visitor::NAMESPACE_CLASS_METHOD]] = $rawMethod;
            }
        }

        return $this->methodsCache;
    }

    public function routes()
    {
        if (is_null($this->routesCache)) {
            $this->routesCache = $this->list(fn() => new VisitorRoute($this->methods()));
        }
        
        return $this->routesCache;
    }
    
    public function transitions()
    {
        if (is_null($this->transitionsCache)) {
            $this->transitionsCache = [
                ...$this->list(fn() => new VisitorMethodCall($this->methods())),
                ...$this->list(fn() => new VisitorNewCall),
                ...$this->list(fn() => new VisitorStaticCall),
                ...$this->list(fn() => new VisitorRoute($this->methods())),
            ];
        }

        return $this->transitionsCache;
    }

    private function list($newVisitor)
    {
        $res = [];

        foreach ($this->parsed as $repositoryFileStructure) {
            $visitor = $newVisitor();
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($repositoryFileStructure);
            $res = array_merge($res, $visitor->list());
        }

        return $res;
    }

}
