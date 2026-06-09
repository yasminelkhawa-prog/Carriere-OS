<?php
$dir = __DIR__ . '/lang/fr';
$files = glob($dir . '/*.php');
$errors = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Find unescaped single quotes after l, d, s, n, c, qu, j
    $content = preg_replace("/(?<!\\\\)\b([ldsncj])'/i", "$1\\'", $content);
    $content = preg_replace("/(?<!\\\\)\b(qu)'/i", "$1\\'", $content);
    
    file_put_contents($file, $content);
    
    // Test syntax
    exec("php -l " . escapeshellarg($file), $output, $returnVar);
    if ($returnVar !== 0) {
        echo "Syntax error in $file\n";
        $errors++;
    }
}

if ($errors === 0) {
    echo "All syntax errors fixed!\n";
} else {
    echo "$errors files still have syntax errors.\n";
}
