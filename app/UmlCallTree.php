<?php

namespace App;

use App\PhpVisitor\Visitor;
use App\Comparison;

class UmlCallTree
{

    private const COLOR = 'color';

    private const BLACK = 'black';
    private const RED   = 'red';
    private const GREEN = 'green';
    private const BLUE  = 'blue';

    public static function fromComparison (string $title, Comparison $class, Comparison $method, Comparison $call)
    {

        array_walk($class ->created, function(&$class ){ $class [self::COLOR] = self::GREEN; });
        array_walk($method->created, function(&$method){ $method[self::COLOR] = self::GREEN; });
        array_walk($call  ->created, function(&$call  ){ $call  [self::COLOR] = self::GREEN; });

        array_walk($class ->deleted, function(&$class ){ $class [self::COLOR] = self::RED;   });
        array_walk($method->deleted, function(&$method){ $method[self::COLOR] = self::RED;   });
        array_walk($call  ->deleted, function(&$call  ){ $call  [self::COLOR] = self::RED;   });

        array_walk($method->updated, function(&$method){ $method[self::COLOR] = self::BLUE;  });

        $tree = [];

        $tree = self::namespaceClassMethodStructure($tree, $class ->created);
        $tree = self::namespaceClassMethodStructure($tree, $method->created);
        $tree = self::namespaceClassMethodStructure($tree, array_column($call->created, Visitor::FROM));
        $tree = self::namespaceClassMethodStructure($tree, array_column($call->created, Visitor::TO  ));

        $tree = self::namespaceClassMethodStructure($tree, $class ->deleted);
        $tree = self::namespaceClassMethodStructure($tree, $method->deleted);
        $tree = self::namespaceClassMethodStructure($tree, array_column($call->deleted, Visitor::FROM));
        $tree = self::namespaceClassMethodStructure($tree, array_column($call->deleted, Visitor::TO  ));

        $tree = self::namespaceClassMethodStructure($tree, $method->updated);
        $tree = self::namespaceClassMethodStructure($tree, array_column($call->identical, Visitor::FROM));
        $tree = self::namespaceClassMethodStructure($tree, array_column($call->identical, Visitor::TO  ));

        $uml = '@startuml' . PHP_EOL;
        if (strlen($title)) {
            $uml .= "title $title" . PHP_EOL;
        }
        $uml .= self::getStructureString($tree);
        $uml .= self::getCallString($call);
        $uml .= 'note AS legend' . PHP_EOL .
            '  <b>Legend</b>' . PHP_EOL .
            '  - <color:'.self::BLACK.'>unchanged</color>' . PHP_EOL .
            '  - <color:'.self::BLUE.'>update</color>' . PHP_EOL .
            '  - <color:'.self::GREEN.'>created</color>' . PHP_EOL .
            '  - <color:'.self::RED.'>deleted</color>' . PHP_EOL .
            'end note' . PHP_EOL;
        $uml .= '@enduml' . PHP_EOL;

        return $uml;
    }

    private static function namespaceClassMethodStructure(array $accumulator, array $calls) : array
    {
        foreach ($calls as $call) {
            $ns = $call[Visitor::NAMESPACE] ?? '';
            if (!strlen($ns)) continue;
            $accumulator[$ns] = $accumulator[$ns] ?? [];

            $cl = $call[Visitor::CLASSE] ?? '';
            if (!strlen($cl)) continue;
            $accumulator[$ns][$cl] = $accumulator[$ns][$cl] ?? [];

            $fn = $call[Visitor::METHOD] ?? '';
            if (!strlen($fn)) continue;
            $accumulator[$ns][$cl][$fn] = $accumulator[$ns][$cl][$fn] ?? $call;
        }

        return $accumulator;
    }

    private static function getStructureString(array $tree) : string
    {
        $res = '';
        foreach ($tree as $namespace => $classes) {
            $resNamespace = '';

            foreach ($classes as $classe => $methods) {
                $namespaceClass = $methods[array_key_first($methods)][Visitor::NAMESPACE_CLASS];

                if (!strlen($classe)) {
                    $classe = '/';
                }
                $resNamespace .= "  class " . md5($namespaceClass) . " as \"$classe\" {" . PHP_EOL;

                foreach ($methods as $method => $methodDetail) {
                    $color = $methodDetail[self::COLOR] ?? self::BLACK;
                    $resNamespace .= "    {method} <color:$color>$method</color>" . PHP_EOL;
                }

                $resNamespace .= "  }" . PHP_EOL;
            }

            if(strlen($namespace)){
                $namespace = str_replace('\\', '.', $namespace);
                $namespace = str_replace('/', '.', $namespace);
                $resNamespace = "namespace $namespace {" . PHP_EOL . $resNamespace . '}' . PHP_EOL;
            }
            
            $res .= $resNamespace;
        }
        return $res;
    }

    private static function getCallString(Comparison $calls) : string
    {
        $res = '';
        foreach ($calls as $chunk) {
            foreach ($chunk as $call) {
                $color = $call[self::COLOR] ?? self::BLACK;
                $res .= md5($call[Visitor::FROM][Visitor::NAMESPACE_CLASS]) . '::';
                $res .= $call[Visitor::FROM][Visitor::METHOD];
                $res .= " -[#$color]-> ";
                $res .= md5($call[Visitor::TO][Visitor::NAMESPACE_CLASS]) . '::';
                $res .= $call[Visitor::TO][Visitor::METHOD] . PHP_EOL;
            }
        }

        return $res;
    }

}
