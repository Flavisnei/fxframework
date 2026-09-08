<?php

declare(strict_types=1);

namespace Fx\Framework\View;

use Smarty;
use Fx\Framework\Http\Csrf;

final class View
{
    private Smarty $smarty;

    public function __construct(
        string $templateDirectory,
        string $compileDirectory,
        string $cacheDirectory
    ) {
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($templateDirectory);
        $this->smarty->setCompileDir($compileDirectory);
        $this->smarty->setCacheDir($cacheDirectory);
        $this->smarty->setLeftDelimiter('-{');
        $this->smarty->setRightDelimiter('}-');
        $this->smarty->registerPlugin('function', 'csrf_field', static function (): string {
            if (!class_exists(Csrf::class)) {
                throw new \RuntimeException('O helper csrf_field requer o pacote fxfavalessa/fx-http.');
            }
            return '<input type="hidden" name="_csrf" value="'
                . htmlspecialchars(Csrf::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        }, false);
        $this->smarty->registerPlugin('function', 'fxwindows_assets', [FxWindowsHelper::class, 'assets']);
        $this->smarty->registerPlugin('function', 'fxwindow', [FxWindowsHelper::class, 'window']);
    }

    public function render(string $template, array $data = []): string
    {
        try {
            foreach ($data as $key => $value) {
                $this->smarty->assign((string) $key, $value);
            }

            return $this->smarty->fetch($template);
        } finally {
            // Cada render deve ser isolado para evitar vazamento de dados entre respostas.
            $this->smarty->clearAllAssign();
        }
    }

    public function engine(): Smarty
    {
        return $this->smarty;
    }
}
