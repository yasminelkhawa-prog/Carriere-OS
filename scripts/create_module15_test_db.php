<?php
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', '1234');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS numa_module15_test');
$pdo->exec('CREATE DATABASE numa_module15_test');
echo "created\n";
