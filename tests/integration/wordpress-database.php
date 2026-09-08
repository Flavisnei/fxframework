<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
$config = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$name = $config['database'];
if (!preg_match('/\Afx_wp_matrix_[a-f0-9]{24}\z/', $name)) { throw new RuntimeException('Banco temporario invalido.'); }
$pdo = new PDO('mysql:host=127.0.0.1;port='.(int)$config['port'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if (($argv[1] ?? '') === 'drop') { $pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`'); }
elseif (($argv[1] ?? '') === 'create') { $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); }
else { throw new RuntimeException('Acao invalida.'); }
