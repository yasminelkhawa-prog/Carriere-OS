<?php
$files = glob(__DIR__ . '/lang/fr/*.php');
$errors = 0;
foreach ($files as $f) {
    exec('php -l ' . escapeshellarg($f), $o, $r);
    if ($r !== 0) {
        echo "Error in $f\n";
        $errors++;
    }
}
if ($errors === 0) {
    echo "All syntax errors fixed!\n";
}
