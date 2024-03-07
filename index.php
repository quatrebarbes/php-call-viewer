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
use Gitonomy\Git\Admin as GitAdmin;
use Gitonomy\Git\Repository;

ini_set('memory_limit', '1G');

$cliArgs = getopt('', ["title::", "path::", "tmpPath::", "outPath::", "repo::", "base::", "head::", "filename::"]);
$titleArg = $cliArgs['title'] ?? '';
$pathArg = realpath($cliArgs['path'] ?? getcwd());
$tmpPathArg = realpath($cliArgs['tmpPath'] ?? (getcwd() . '/.tmp'));
$outPathArg = realpath($cliArgs['outPath'] ?? (getcwd() . '/.uml'));
$repoArg = $cliArgs['repo'] ?? null;
$baseArg = $cliArgs['base'] ?? null;
$headArg = $cliArgs['head'] ?? null;
$filenameArg = $cliArgs['filename'] ?? time();

echo '- Read & parse the source files...' . PHP_EOL;

FileSystem::emptyFolder($tmpPathArg);
$git;
if (is_null($repoArg)) {
    $git = new Repository($pathArg);
    $git = $git->cloneTo($tmpPathArg, false);
} else {
    $git = GitAdmin::cloneTo($tmpPathArg, $repoArg, false);
}
$git = $git->getWorkingCopy();
if (is_null($baseArg)) { // By default, compare with the head
    $baseProject = new PhpAnalysis(FileSystem::listFiles([$tmpPathArg]));
} else {
    $git->checkout($baseArg);
    $baseProject = new PhpAnalysis(FileSystem::listFiles([$tmpPathArg]));
}
if (is_null($headArg)) { // By default, use working directory
    $headProject = new PhpAnalysis(FileSystem::listFiles([$pathArg]));
} else {
    $git->checkout($headArg);
    $headProject = new PhpAnalysis(FileSystem::listFiles([$tmpPathArg]));
}
FileSystem::emptyFolder($tmpPathArg);

echo '- List the classes & methods...' . PHP_EOL;

$classes = new Comparison(
    $headProject->classes(),
    $baseProject->classes(),
    Visitor::NAMESPACE_CLASS,
    Visitor::NAMESPACE_CLASS,
);
$methods = new Comparison(
    $headProject->methods(),
    $baseProject->methods(),
    Visitor::NAMESPACE_CLASS_METHOD,
    Visitor::HASH,
);

echo '- Browse the method calls...' . PHP_EOL;

$targetMethods = [
    ...array_column($methods->updated, Visitor::NAMESPACE_CLASS_METHOD),
    ...array_column($methods->created, Visitor::NAMESPACE_CLASS_METHOD),
    ...array_column($methods->deleted, Visitor::NAMESPACE_CLASS_METHOD),
];
$calls = new Comparison(
    $headProject->callers($targetMethods),
    $baseProject->callers($targetMethods),
    Visitor::TO_STRING,
    Visitor::TO_STRING,
);

echo '- Write the TUML specification...' . PHP_EOL;

$umlContent = UmlCallTree::fromComparison($titleArg, $classes, $methods, $calls);

$umlOutPath = $outPathArg . "/$filenameArg.puml";
$svgOutPath = $outPathArg . "/$filenameArg.svg";

echo '- Generate the svg file...' . PHP_EOL;

FileSystem::createFolder($outPathArg);
FileSystem::writeFile($umlOutPath, $umlContent);
exec("bash $vendorPath/bin/plantuml -tsvg $umlOutPath");
$svgContent = FileSystem::readFile($svgOutPath);
$svgContent = str_replace('</svg>',"<style>g[id^='link_']:hover > path, g[id^='link_']:hover > polygon {stroke-width: 5 !important;stroke: purple !important;}</style></svg>", $svgContent);
FileSystem::writeFile($svgOutPath, $svgContent);

$svgOutPath = realpath($svgOutPath);
echo PHP_EOL;
echo "Please get your SVG:$svgOutPath " . PHP_EOL;
