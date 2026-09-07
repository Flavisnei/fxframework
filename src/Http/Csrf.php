<?php

declare(strict_types=1);

namespace Fx\Framework\Http;

use RuntimeException;

final class Csrf
{
    public static function token(string $key = '_csrf'): string
    {
        self::startSession();

        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    public static function validate(?string $token, string $key = '_csrf'): bool
    {
        self::startSession();

        return isset($_SESSION[$key])
            && is_string($_SESSION[$key])
            && is_string($token)
            && hash_equals($_SESSION[$key], $token);
    }

    public static function regenerate(string $key = '_csrf'): string
    {
        self::startSession();
        $_SESSION[$key] = bin2hex(random_bytes(32));

        return $_SESSION[$key];
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException("Nao foi possivel iniciar a sessao: headers enviados em {$file}:{$line}.");
        }

        if (!session_start()) {
            throw new RuntimeException('Nao foi possivel iniciar a sessao para protecao CSRF.');
        }
    }
}
