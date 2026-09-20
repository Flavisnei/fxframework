<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Page;
use Fx\Framework\Http\Request;
final class HomeController
{
    public function index(Page $page, Request $request): string
    {
        return $page->home('Minha aplicação FX', $request->getBaseUrl());
    }
}
