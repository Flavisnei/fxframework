<?php
declare(strict_types=1);
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Fx\Framework\Http\Kernel::class)->handle()->send();
