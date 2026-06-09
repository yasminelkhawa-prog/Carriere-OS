<?php
$lines = file('resources/views/candidates/index.blade.php');
$newLayout = file_get_contents('new_layout.blade.php');
$out = implode('', array_slice($lines, 0, 1072)) . $newLayout . "\n" . implode('', array_slice($lines, 1072, 1207 - 1072 + 1)) . implode('', array_slice($lines, 1345, 2));
file_put_contents('resources/views/candidates/index.blade.php', $out);
