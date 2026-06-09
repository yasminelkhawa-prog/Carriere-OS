<?php
$c = file_get_contents('resources/views/candidates/index.blade.php');
$startStr = '<div class="grid gap-3 lg:grid-cols-4">';
$endStr = '                                <div class="grid gap-3 lg:grid-cols-3">';
$start = strpos($c, $startStr);
$end = strpos($c, $endStr);
$chunk = substr($c, $start, $end - $start);
$c = str_replace($chunk, '', $c);
$insertStr = '                                    <div class="mt-3 grid gap-2 md:grid-cols-2 2xl:grid-cols-4">';
$chunkToInsert = '                                    <div class="mb-4 grid gap-3 lg:grid-cols-4">' . substr($chunk, strlen($startStr));
$c = str_replace($insertStr, $chunkToInsert . $insertStr, $c);
file_put_contents('resources/views/candidates/index.blade.php', $c);
echo "Moved successfully.\n";
