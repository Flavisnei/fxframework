<?php
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath(__DIR__ . '/public' . rawurldecode(is_string($path) ? $path : '/'));
$public = realpath(__DIR__ . '/public');
if ($file !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file)) return false;
require __DIR__ . '/public/index.php';
