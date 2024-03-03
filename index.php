<?php

$vendorPath;
foreach ([__DIR__ . '/../..', __DIR__ . '/vendor'] as $folder) {
    $autoload = $folder . '/autoload.php';
    if (file_exists($autoload)) {
        require $autoload;
        $vendorPath = $folder;
        break;
    }
}

use App\Comparison;
use App\FileSystem;
use App\PhpAnalysis;
use App\PhpVisitor\Visitor;
use App\UmlCallTree;
use Gitonomy\Git\Repository;

const UML_PATH = './.uml';
const TMP_PATH = './.tmp';
$TIMESTAMP = time();

ini_set('memory_limit', '1G');

$cliArgs = getopt('', ["path::", "title::", "method::"]);
$pathArg = $cliArgs['path'] ?? './';
$titleArg = $cliArgs['title'] ?? '';
$methodsArg = $cliArgs['method'] ?? [];
$methodsArg = is_array($methodsArg) ? $methodsArg : [$methodsArg];

FileSystem::emptyFolder(TMP_PATH);
$git = new Repository($pathArg);
$git = $git->cloneTo(TMP_PATH, false);
$currentProject = new PhpAnalysis(FileSystem::listFiles([$pathArg]));
$baseProject = new PhpAnalysis(FileSystem::listFiles([TMP_PATH]));
FileSystem::emptyFolder(TMP_PATH);

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

$umlContent = UmlCallTree::fromComparison($titleArg, $classes, $methods, $calls);

$writePath = $pathArg . UML_PATH;
$umlWritePath = $writePath . "/$TIMESTAMP.puml";
$svgWritePath = $writePath . "/$TIMESTAMP.svg";

FileSystem::createFolder($writePath);
FileSystem::writeFile($umlWritePath, $umlContent);
exec("bash $vendorPath/bin/plantuml -tsvg $umlWritePath");
$svgContent = FileSystem::readFile($svgWritePath);
$svgContent = str_replace('</svg>',"<style>g[id^='link_']:hover > path, g[id^='link_']:hover > polygon {stroke-width: 5 !important;stroke: purple !important;}</style></svg>", $svgContent);
FileSystem::writeFile($svgWritePath, $svgContent);

