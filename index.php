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

ini_set('memory_limit', '1G');

$cliArgs = getopt('', ["title::", "path::", "source::", "target::", "filename::"]);
$titleArg = $cliArgs['title'] ?? '';
$pathArg = $cliArgs['path'] ?? './';
$sourceArg = $cliArgs['source'] ?? null;
$targetArg = $cliArgs['target'] ?? null;
$filenameArg = $cliArgs['filename'] ?? time();

echo 'Read & parse the source files...' . PHP_EOL;

FileSystem::emptyFolder(TMP_PATH);
$git = new Repository($pathArg);
$git = $git->cloneTo(TMP_PATH, false);
$git = $git->getWorkingCopy();
if (is_null($targetArg)) { // Compare with the head, if no target is set
    $baseProject = new PhpAnalysis(FileSystem::listFiles([TMP_PATH]));
} else {
    $git->checkout($targetArg);
    $baseProject = new PhpAnalysis(FileSystem::listFiles([TMP_PATH]));
}
if (is_null($sourceArg)) { // Use working directory, if no source is set
    $currentProject = new PhpAnalysis(FileSystem::listFiles([$pathArg]));
} else {
    $git->checkout($sourceArg);
    $currentProject = new PhpAnalysis(FileSystem::listFiles([TMP_PATH]));
}
FileSystem::emptyFolder(TMP_PATH);

echo 'List the classes & methods...' . PHP_EOL;

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

echo 'Browse the method calls...' . PHP_EOL;

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

echo 'Write the TUML specification...' . PHP_EOL;

$umlContent = UmlCallTree::fromComparison($titleArg, $classes, $methods, $calls);

$writePath = $pathArg . UML_PATH;
$umlWritePath = $writePath . "/$filenameArg.puml";
$svgWritePath = $writePath . "/$filenameArg.svg";

echo 'Generate the svg file...' . PHP_EOL;

FileSystem::createFolder($writePath);
FileSystem::writeFile($umlWritePath, $umlContent);
exec("bash $vendorPath/bin/plantuml -tsvg $umlWritePath");
$svgContent = FileSystem::readFile($svgWritePath);
$svgContent = str_replace('</svg>',"<style>g[id^='link_']:hover > path, g[id^='link_']:hover > polygon {stroke-width: 5 !important;stroke: purple !important;}</style></svg>", $svgContent);
FileSystem::writeFile($svgWritePath, $svgContent);

$svgWritePath = realpath($svgWritePath);
echo "Please get your SVG:$svgWritePath " . PHP_EOL;
