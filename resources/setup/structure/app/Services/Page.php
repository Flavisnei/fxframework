<?php
declare(strict_types=1);
namespace App\Services;
final class Page
{
    public function __construct(private readonly string $views) {}
    public function home(string $title, string $base): string
    {
        // Template fixo: nunca use caminhos vindos do usuario em require.
        ob_start();
        try { require $this->views . '/home.php'; return (string) ob_get_contents(); }
        finally { ob_end_clean(); }
    }
}
