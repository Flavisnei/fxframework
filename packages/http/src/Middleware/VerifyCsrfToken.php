<?php

declare(strict_types=1);

namespace Fx\Framework\Middleware;

use Fx\Framework\Http\Csrf;
use Fx\Framework\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Protecao para rotas web que utilizam a sessao nativa do PHP. */
final class VerifyCsrfToken implements Middleware
{
    public function process(Request $request, callable $next): mixed
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Nao aceitar token na URL: URLs podem ser registradas em logs e historico.
        $data = $request->isJson() ? $request->json()->all() : $request->request->all();
        $token = $request->headers->get('X-CSRF-TOKEN') ?? ($data['_csrf'] ?? null);
        if (!is_string($token) || $token === '' || !Csrf::validate($token)) {
            throw new HttpException(403, 'Token CSRF invalido.');
        }

        return $next($request);
    }
}
