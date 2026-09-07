# FX Core

Container, configuração em memória e ciclo de providers, sem HTTP, banco ou views.

Abra [a ajuda HTML deste pacote](docs/index.html). A instalação local usa um
repositório Composer do tipo path apontando para esta pasta e requer
`fxfavalessa/fx-core` com `@dev` enquanto o pacote está em desenvolvimento.

```php
require __DIR__ . '/vendor/autoload.php';
$app = new Fx\Framework\Foundation\CoreApplication(__DIR__);
$app->config()->set('app.name', 'Meu projeto');
echo $app->config()->get('app.name');
```

O pacote completo `fxfavalessa/fx-framework` já contém este código. Escolha Core
ou completo; não copie classes entre pacotes. O Core não inclui FX Artisan.
