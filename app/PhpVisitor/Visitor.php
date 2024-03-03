<?php

namespace App\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class Visitor extends NodeVisitorAbstract
{

    const FROM = 'from';
    const TO = 'to';

    const NAMESPACE = 'namespace';
    const CLASSE = 'class';
    const METHOD = 'method';
    const HASH = 'hash';
    const NAMESPACE_CLASS = 'namespaceClass';
    const CLASSE_METHOD = 'classMethod';
    const NAMESPACE_CLASS_METHOD = 'namespaceClassMethod';

    const TO_STRING = 'toString';
    
    protected array $methods = [];
    protected string $namespace = '';
    protected string $class = '';
    protected array $visited = [];

    public function __construct(?array $methods = [])
    {
        $this->methods = $methods;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name;
        }

        if (
            $node instanceof Node\Stmt\Class_ ||
            $node instanceof Node\Stmt\Trait_
        ) {
            $this->class = $node->name ?? '';
        }
    }

    public function list()
    {
        $res = $this->visited;
        $res = $this->addDefault($res);
        $res = $this->addStaticRules($res);
        $res = $this->format($res);
        return $res;
    }

    private function addDefault($visited)
    {
        $res = [];
        foreach ($visited as $key => $item) {
            $res[$key] = $item;

            if (empty($res[$key][self::FROM])){
                $res[$key][self::FROM] = [];
            }
            if (empty($res[$key][self::FROM][self::NAMESPACE])){
                $res[$key][self::FROM][self::NAMESPACE] = !empty($this->namespace) ? $this->namespace : '_namespace_';
            }
            if (empty($res[$key][self::FROM][self::CLASSE])){
                $res[$key][self::FROM][self::CLASSE] = !empty($this->class) ? $this->class : '_class_';
            }
            if (empty($res[$key][self::FROM][self::METHOD])){
                $res[$key][self::FROM][self::METHOD] = '_method_';
            }
            if (empty($res[$key][self::FROM][self::HASH])){
                $res[$key][self::FROM][self::HASH] = '_hash_';
            }

            if (empty($res[$key][self::TO])){
                $res[$key][self::TO] = [];
            }
            if (empty($res[$key][self::TO][self::NAMESPACE])){
                $res[$key][self::TO][self::NAMESPACE] = '_namespace_';
            }
            if (empty($res[$key][self::TO][self::CLASSE])){
                $res[$key][self::TO][self::CLASSE] = '_class_';
            }
            if (empty($res[$key][self::TO][self::METHOD])){
                $res[$key][self::TO][self::METHOD] = '_method_';
            }
            if (empty($res[$key][self::TO][self::HASH])){
                $res[$key][self::TO][self::HASH] = '_hash_';
            }
        }
        return $res;
    }

    private function addStaticRules($visited)
    {
        $res = [];
        foreach ($visited as $item) {

            // Transformers are created in our code, but transform is called inside Laravel's files
            if (
                isset($item[self::TO][self::NAMESPACE]) &&
                preg_match("/.*Transformer/i", $item[self::TO][self::CLASSE]) &&
                '__construct' == $item[self::TO][self::METHOD]
            ){
                array_push($res, [
                    self::TO => [
                        self::NAMESPACE => $item[self::TO][self::NAMESPACE],
                        self::CLASSE => $item[self::TO][self::CLASSE],
                        self::METHOD => 'transform',
                        self::HASH => '', // needed for method listing, not for transitions
                    ],
                    self::FROM => $item[self::FROM]
                ]);
            }

        }
        return array_merge($visited, $res);
    }

    private function format($visited)
    {
        $res = [];
        foreach ($visited as $key => $item) {
            foreach ([self::FROM, self::TO] as $src) {
                $res[$key][$src] = [];
                $res[$key][$src][self::NAMESPACE] = (string) $item[$src][self::NAMESPACE];
                $res[$key][$src][self::CLASSE] = (string) $item[$src][self::CLASSE];
                $res[$key][$src][self::METHOD] = (string) $item[$src][self::METHOD];
                $res[$key][$src][self::HASH] = (string) $item[$src][self::HASH];
                $res[$key][$src][self::NAMESPACE_CLASS] =
                    $res[$key][$src][self::NAMESPACE] . '\\' .
                    $res[$key][$src][self::CLASSE];
                $res[$key][$src][self::CLASSE_METHOD] =
                    $res[$key][$src][self::CLASSE] . '@' .
                    $res[$key][$src][self::METHOD];
                $res[$key][$src][self::NAMESPACE_CLASS_METHOD] =
                    $res[$key][$src][self::NAMESPACE] . '\\' .
                    $res[$key][$src][self::CLASSE] . '@' .
                    $res[$key][$src][self::METHOD];
            }
            $res[$key][self::TO_STRING] =
                $res[$key][self::FROM][self::NAMESPACE_CLASS_METHOD] . ' > ' .
                $res[$key][self::TO][self::NAMESPACE_CLASS_METHOD];
        }
        return $res;
    }
}
