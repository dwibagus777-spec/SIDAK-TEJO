<?php
// Parse autoload_static.php without loading it (to avoid class redeclaration)
$content = file_get_contents("vendor/composer/autoload_static.php");

// The directory where autoload_static.php is located
$baseDir = __DIR__ . '/vendor/composer';

// Find the classMap array in ComposerStaticInit class
if (preg_match('/public static \$classMap = array \(([\s\S]*?)\);/s', $content, $m)) {
    $arrayContent = $m[1];
    
    // Parse the array manually - each line is 'Class' => __DIR__ . '/path',
    $lines = explode("\n", $arrayContent);
    $classMap = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line === ')' || $line === ',') continue;
        // Remove trailing comma
        $line = rtrim($line, ',');
        if (preg_match("/^'([^']+)'\s*=>\s*(.+)$/", $line, $mm)) {
            $class = $mm[1];
            $pathExpr = $mm[2];
            // Replace __DIR__ with the actual baseDir before eval
            $pathExpr = str_replace('__DIR__', var_export($baseDir, true), $pathExpr);
            // Evaluate the path expression
            $path = eval("return $pathExpr;");
            $classMap[$class] = $path;
        }
    }
    
    $checked = 0;
    $missing = [];
    foreach ($classMap as $class => $path) {
        $checked++;
        if (!file_exists($path)) {
            $missing[] = $class . " => " . $path;
        }
    }
    echo "Static classMap checked: $checked\n";
    echo "Missing: " . count($missing) . "\n";
    if ($missing) {
        foreach ($missing as $m) echo $m . "\n";
    }
} else {
    echo "classMap not found in autoload_static.php\n";
}