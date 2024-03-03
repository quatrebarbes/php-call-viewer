<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Comparison;
use App\FileSystem;
use App\PhpAnalysis;
use App\PhpVisitor\Visitor;
use App\UmlCallTree;
use Gitonomy\Git\Repository;

const TMP_PATH = './.tmp';

ini_set('memory_limit', '1G');

$cliArgs = getopt('', ["path::", "title::", "method::"]);
$pathArg = $cliArgs['path'] ?? './';
$titleArg = $cliArgs['title'] ?? '';
$methodsArg = $cliArgs['method'] ?? [];
$methodsArg = is_array($methodsArg) ? $methodsArg : [$methodsArg];

$git = new Repository($pathArg);
$git = $git->cloneTo(TMP_PATH, false);
$currentProject = new PhpAnalysis(FileSystem::listFiles([$pathArg]));
$baseProject = new PhpAnalysis(FileSystem::listFiles([TMP_PATH]));
FileSystem::delete(TMP_PATH);

$classes = new Comparison(
    $currentProject->classes(),
    $baseProject->classes(),
    Visitor::NAMESPACE_CLASS,
    Visitor::NAMESPACE_CLASS,
);

$methods = new Comparison(
    $currentProject->methods(),
    $baseProject->methods(),
    Visitor::NAMESPACE_CLASS_METHOD,
    Visitor::HASH,
);

$targetMethods = [
    ...array_column($methods->updated, Visitor::NAMESPACE_CLASS_METHOD),
    ...array_column($methods->created, Visitor::NAMESPACE_CLASS_METHOD),
    ...array_column($methods->deleted, Visitor::NAMESPACE_CLASS_METHOD),
];
$calls = new Comparison(
    $currentProject->callers($targetMethods),
    $baseProject->callers($targetMethods),
    Visitor::TO_STRING,
    Visitor::TO_STRING,
);

echo UmlCallTree::fromComparison($titleArg, $classes, $methods, $calls);
