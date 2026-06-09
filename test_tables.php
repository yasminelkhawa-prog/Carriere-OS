<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = \DB::select('SHOW TABLES');
foreach ($tables as $table) {
    $key = array_keys(get_object_vars($table))[0];
    if (stripos($table->$key, 'pipeline') !== false || stripos($table->$key, 'stage') !== false || stripos($table->$key, 'setting') !== false) {
        echo $table->$key . "\n";
    }
}
