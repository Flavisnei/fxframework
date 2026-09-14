<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=new Fx\Framework\Foundation\Application(dirname(__DIR__));
$app->make(Fx\Framework\Routing\Router::class)->get('/',static fn()=>new Symfony\Component\HttpFoundation\JsonResponse(['ok'=>true,'message'=>'FX HTTP pronto']));
$app->boot();
$app->make(Fx\Framework\Http\Kernel::class)->handle()->send();
