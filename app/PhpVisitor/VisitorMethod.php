<?php

namespace App\PhpVisitor;

use PhpParser\Node;
use PhpParser\PrettyPrinter;

class VisitorMethod extends Visitor {

    protected PrettyPrinter\Standard $printer;

    public function __construct()
    {
        parent::__construct();
        $this->printer = new PrettyPrinter\Standard;
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->visited[] = [
                self::FROM => [
                    self::METHOD => $node->name->name,
                    self::HASH => md5($this->printer->prettyPrint([$node])),
                ],
            ];
        }
    }

    public function list()
    {
        $res = parent::list();
        return array_column($res, self::FROM);
    }

}
