<?php
// Parse autoload_static.php
$content = file_get_contents("vendor/composer/autoload_static.php");
$baseDir = __DIR__ . '/vendor/composer';

if (preg_match('/public static \$classMap = array \(([\s\S]*?)\);/s', $content, $m)) {
    $arrayContent = $m[1];
    $lines = explode("\n", $arrayContent);
    $staticMap = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line === ')' || $line === ',') continue;
        $line = rtrim($line, ',');
        if (preg_match("/^'([^']+)'\s*=>\s*(.+)$/", $line, $mm)) {
            $class = $mm[1];
            $pathExpr = str_replace('__DIR__', var_export($baseDir, true), $mm[2]);
            $path = eval("return $pathExpr;");
            $staticMap[$class] = realpath($path);
        }
    }
}

// Parse autoload_classmap.php
$vendorDir = __DIR__ . '/vendor';
$baseDir2 = __DIR__;
$classmapContent = file_get_contents("vendor/composer/autoload_classmap.php");
if (preg_match('/return array\(([\s\S]*?)\);/s', $classmapContent, $m)) {
    $arrayContent = $m[1];
    $lines = explode("\n", $arrayContent);
    $classmapMap = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line === ')' || $line === ',') continue;
        $line = rtrim($line, ',');
        if (preg_match("/^'([^']+)'\s*=>\s*(.+)$/", $line, $mm)) {
            $class = $mm[1];
            $pathExpr = $mm[2];
            $pathExpr = str_replace('$vendorDir', var_export($vendorDir, true), $pathExpr);
            $pathExpr = str_replace('$baseDir', var_export($baseDir2, true), $pathExpr);
            $path = eval("return $pathExpr;");
            $classmapMap[$class] = realpath($path);
        }
    }
}

// Compare with realpath
echo "Static map entries: " . count($staticMap) . "\n";
echo "Classmap map entries: " . count($classmapMap) . "\n";

$diffStaticOnly = array_diff_key($staticMap, $classmapMap);
$diffClassmapOnly = array_diff_key($classmapMap, $staticMap);

echo "Only in static: " . count($diffStaticOnly) . "\n";
echo "Only in classmap: " . count($diffClassmapOnly) . "\n";

$commonKeys = array_intersect_key($staticMap, $classmapMap);
$pathDiffs = 0;
foreach ($commonKeys as $class => $staticPath) {
    $classmapPath = $classmapMap[$class];
    if ($staticPath !== $classmapPath) {
        $pathDiffs++;
        if ($pathDiffs <= 5) {
            echo "PATH DIFF: $class\n  static: $staticPath\n  classmap: $classmapPath\n";
        }
    }
}
echo "Path differences on common keys (after realpath): $pathDiffs\n";

// Check missing files in both
$missingInStatic = 0;
$missingInClassmap = 0;
foreach ($staticMap as $class => $path) {
    if ($path === false || !file_exists($path)) $missingInStatic++;
}
foreach ($classmapMap as $class => $path) {
    if ($path === false || !file_exists($path)) $missingInClassmap++;
}
echo "Missing files in static map: $missingInStatic\n";
echo "Missing files in classmap map: $missingInClassmap\n";