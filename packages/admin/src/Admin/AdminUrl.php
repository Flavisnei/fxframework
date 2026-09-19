<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;
use Fx\Framework\Http\Request;
final class AdminUrl
{
    public static function base(Request $request, ?string $configured = null): string
    {
        $base=$request->getBaseUrl();
        if ($configured !== null && $configured !== '') {
            $url=parse_url($configured);
            if (!$url || !filter_var($configured,FILTER_VALIDATE_URL) || !in_array($url['scheme']??'',['http','https'],true) || isset($url['user']) || isset($url['query']) || isset($url['fragment'])) throw new \RuntimeException('APP_URL invalida. Use a URL publica da aplicacao, sem /admin, credenciais, query ou fragmento.');
            $base=$url['path']??'';
        }
        $base=rtrim($base,'/');
        if ($base!=='' && (!str_starts_with($base,'/') || str_starts_with($base,'//') || preg_match('~[\\\\\x00-\x20<>"\x7f]~',$base))) throw new \RuntimeException('Caminho publico invalido.');
        return $base;
    }
    public static function recoveryPath(string $path): bool
    {
        return preg_match('~\A/(?:[a-zA-Z0-9_.%+-]+/)*admin\z~', $path) === 1 && !preg_match('~(?:^|/)\.\.?(?:/|$)~',$path);
    }
}
