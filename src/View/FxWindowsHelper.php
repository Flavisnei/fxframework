<?php

declare(strict_types=1);

namespace Fx\Framework\View;

final class FxWindowsHelper
{
    public const VERSION = '1.2.3';

    public static function assets(array $params = [], mixed $template = null): string
    {
        $base = rtrim((string) ($params['base'] ?? '/assets/fxwindows'), '/');
        $version = self::escape((string) ($params['version'] ?? self::VERSION));
        $forms = filter_var($params['forms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $html = '<link rel="stylesheet" href="' . self::escape($base) . '/fxwindows.css?v=' . $version . '">' . PHP_EOL;
        $html .= '<script src="' . self::escape($base) . '/fxwindows.js?v=' . $version . '"></script>';

        if ($forms) {
            $html .= PHP_EOL . '<script>document.addEventListener("DOMContentLoaded",function(){startFxForms();},{once:true});</script>';
        }

        return $html;
    }

    public static function window(array $params = [], mixed $template = null): string
    {
        $id = self::safeId((string) ($params['id'] ?? 'fxjanela'));
        $text = (string) ($params['texto'] ?? $params['text'] ?? $params['titulo'] ?? $params['title'] ?? 'Abrir');
        $title = (string) ($params['titulo'] ?? $params['title'] ?? $text);
        $url = (string) ($params['url'] ?? '#');
        $type = strtolower((string) ($params['tipo'] ?? $params['type'] ?? 'link'));

        $attributes = [
            'id' => $id,
            'class' => trim('fxjanelas ' . (string) ($params['classe'] ?? $params['class'] ?? '')),
            'data-fx-window' => '',
            'data-window-id' => $id,
            'data-window-url' => $url,
            'data-window-title' => $title,
            'data-window-width' => (string) ($params['largura'] ?? $params['width'] ?? 900),
            'data-window-height' => (string) ($params['altura'] ?? $params['height'] ?? 600),
            'data-window-level' => (string) ($params['nivel'] ?? $params['level'] ?? 1),
        ];

        $focus = $params['foco'] ?? $params['focus'] ?? null;
        if ($focus !== null && $focus !== '') {
            $attributes['data-window-focus'] = (string) $focus;
        }

        if ($type === 'button') {
            $attributes['type'] = 'button';
            return '<button' . self::attributes($attributes) . '>' . self::escape($text) . '</button>';
        }

        $attributes['href'] = '#' . $id;
        return '<a' . self::attributes($attributes) . '>' . self::escape($text) . '</a>';
    }

    private static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= $value === ''
                ? ' ' . self::escape((string) $name)
                : ' ' . self::escape((string) $name) . '="' . self::escape((string) $value) . '"';
        }
        return $html;
    }

    private static function safeId(string $value): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($value));
        return $id !== '' ? $id : 'fxjanela';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
