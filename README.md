# FX Framework

## WordPress

O pacote `fxfavalessa/fx-wordpress` integra o Core com os recursos nativos do
WordPress. O [plugin de exemplo](examples/wordpress/README.md) possui formulário
AJAX/JSON e permissão administrativa. Leia a [ajuda do adaptador](packages/wordpress/docs/index.html).

```bash
composer install --working-dir=examples/wordpress --no-dev
```

O exemplo deve ser copiado com vendor para um WordPress de desenvolvimento.
Não instala HTTP do FX, Eloquent ou Smarty. Coexistência com bibliotecas de versões
conflitantes ainda requer estratégia de distribuição; veja os limites no manual.

## Ajuda e evolução

### Instalação mínima disponível

O pacote independente `fxfavalessa/fx-core` está em `packages/core/` e contém
container, configuração e providers. O pacote completo continua compatível e inclui
esse mesmo código. HTTP, Database, View, Console, Auth, Validation e Windows também
possuem pacotes locais independentes. Consulte [os pacotes opcionais](docs/index.html#pacotes).

```bash
composer install --working-dir=examples/minimal --no-dev
php examples/minimal/index.php
php examples/minimal/verify.php
```

Leia [o guia do Core](packages/core/docs/index.html) ou a
[ajuda de instalação mínima](docs/index.html#core). Após atualizar este checkout,
execute `composer install` para atualizar o autoload dos arquivos movidos.

### Componentes opcionais

Na aplicação consumidora, configure um repositório Composer `path` apontando para
`../fxframework/packages/*`. Escolha os requisitos `fxfavalessa/fx-http`,
`fxfavalessa/fx-database`, `fxfavalessa/fx-view`, `fxfavalessa/fx-console`,
`fxfavalessa/fx-auth`, `fxfavalessa/fx-validation` ou `fxfavalessa/fx-windows`.
Durante o desenvolvimento local, use `@dev`, `minimum-stability: dev` e
`prefer-stable: true` para resolver dependências FX transitivas. Não houve publicação.

O Artisan básico não instala as camadas opcionais. Comandos são habilitados pelos
pacotes presentes. Os nove cenários em `examples/` podem ser verificados com:

```bash
composer install --working-dir=examples/web --no-dev
php examples/verify-package.php web
```

As classes foram movidas de `src/` para `packages/*/src/`, mantendo namespaces.
Assets estão em `packages/windows/resources/fxwindows`; use
`Fx\Framework\Windows\Assets::directory()` em integrações que precisem localizá-los.

Abra [a central de ajuda HTML](docs/index.html) no navegador. Ela funciona offline,
com busca, índice por assunto, exemplos e indicações do que ainda está planejado.

- [Arquitetura modular](docs/architecture.md)
- [Etapas e critérios de revisão](docs/roadmap.md)
- [Histórico de alterações](CHANGELOG.md)

O estado original está preservado na tag Git local `v1.0.01`. A versão interna
`0.2.0` desse marco foi mantida. A evolução será feita em etapas documentadas.

Nucleo PHP reutilizavel extraido do projeto FX Corrente. O pacote fornece os primeiros componentes genericos para banco de dados, views, respostas HTTP e protecao CSRF.

## Requisitos

- PHP 8.1 ou superior
- Composer 2
- Extensoes exigidas pelas dependencias do Composer

## Instalacao

```bash
composer install
```

O diretorio `vendor/` nao faz parte do framework e e gerado pelo comando acima.

## Uso local no FX Corrente

No `composer.json` da aplicacao, adicione:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../fxframework",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "fxfavalessa/fx-framework": "@dev"
    }
}
```

Em seguida, execute `composer update fxfavalessa/fx-framework` na aplicacao.

## Componentes iniciais

- `Fx\\Framework\\Database\\Database`: inicializacao do Eloquent.
- `Fx\\Framework\\View\\View`: renderizacao isolada com Smarty.
- `Fx\\Framework\\Http\\Csrf`: tokens criptograficamente seguros.
- `Fx\\Framework\\Http\\Response`: respostas HTML e JSON.

## Injecao de dependencias

O framework usa o container do Illuminate, o mesmo nucleo de injecao de dependencias
utilizado pelo Laravel. A classe `Application` acrescenta o ciclo de vida de service
providers.

`CoreApplication` oferece esse ciclo sem serviços HTTP; `Application` herda o Core
e registra os serviços web. Providers agora recebem `CoreApplication` no construtor.

```php
use Fx\Framework\Foundation\Application;
use Fx\Framework\Support\ServiceProvider;

$app = new Application(dirname(__DIR__));

$app->bind(Contrato::class, Implementacao::class);
$app->singleton(ClienteApi::class, fn () => new ClienteApi('token'));

$servico = $app->make(MeuServico::class);
$resultado = $app->call([MeuController::class, 'salvar']);
```

Um provider da aplicacao pode agrupar os bindings:

```php
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Contrato::class, Implementacao::class);
    }

    public function boot(): void
    {
        // Inicializacao executada depois de todos os registros.
    }
}

$app->registerProviders([AppServiceProvider::class])->boot();
```

Tambem estao disponiveis `instance`, `alias`, `scoped`, `tag`, `makeWith` e injecao
contextual por `$app->when(...)->needs(...)->give(...)`.

### Request automatico nos controllers

O dispatcher registra a requisicao atual no container e injeta os parametros
nomeados da rota:

```php
use Fx\Framework\Http\Request;

final class ClienteController
{
    public function update(Request $request, string $id): array
    {
        $name = $request->input('name');

        return ['id' => $id, 'name' => $name];
    }
}
```

O roteador entrega o controller, o metodo e os parametros ao dispatcher:

```php
use Fx\Framework\Routing\ControllerDispatcher;

$dispatcher = new ControllerDispatcher($app);
$resultado = $dispatcher->dispatch(
    ClienteController::class,
    'update',
    ['id' => '42']
);
```

Quando uma requisicao nao e informada, `Request::capture()` utiliza automaticamente
os dados globais da requisicao HTTP atual. A mesma instancia pode ser resolvida como
`Fx\\Framework\\Http\\Request`, `Illuminate\\Http\\Request` ou pelo alias `request`.

## FX Artisan

O executavel e declarado na chave `bin` do Composer. Dentro de uma aplicacao que
instalou o framework, use:

```bash
vendor/bin/fxartisan help
vendor/bin/fxartisan make:controller Clientes
vendor/bin/fxartisan make:model Cliente
vendor/bin/fxartisan make:request Cliente
vendor/bin/fxartisan cache:clear
```

No Windows, o Composer tambem cria o proxy `vendor\\bin\\fxartisan.bat`.

Aplicacoes inicializadas possuem um executavel na raiz, seguindo a experiencia do
Laravel Artisan:

```bash
php fxartisan
php fxartisan about
php fxartisan route:list
php fxartisan serve --host=127.0.0.1 --port=8000
php fxartisan migrate:status
php fxartisan make:middleware Authenticate
php fxartisan optimize:clear
```

## FX Windows

Publique o JavaScript e o CSS na aplicacao:

```bash
php fxartisan fxwindows:install
```

O helper e registrado automaticamente em todas as views Smarty do framework. No layout:

```smarty
-{fxwindows_assets}-
```

Em uma pagina de formulario, use `forms=true` para ativar Enter, Alt+1, Alt+S,
`.tudomaisculo`, validacao e Select2 puro:

```smarty
-{fxwindows_assets forms=true}-
```

Uma janela pode ser criada com uma unica chamada:

```smarty
-{fxwindow id="clientes" texto="Novo cliente" url="/clientes/novo"
    titulo="Cadastro de Clientes" largura=900 altura=600 nivel=1 foco="#nome"}-
```

Use `php fxartisan help <comando>` para ver argumentos e opcoes. Comandos proprios
da aplicacao podem ser classes do Symfony Console retornadas por
`app/Console/commands.php`.

Os comandos antigos de CRUD, migracao e introspeccao de tabelas continuam marcados
como legado porque dependem diretamente da estrutura e dos templates do FX Corrente.
Eles devem ser migrados para um pacote de geradores da aplicacao, sem gravar arquivos
dentro de `vendor/`.

As regras de negocio, controllers, models e templates do FX Corrente permanecem na aplicacao.

## Aplicacao completa

O framework inclui um kernel HTTP, roteador, middleware, validacao, autenticacao,
autorizacao, migrations e tratamento centralizado de excecoes. Para iniciar uma
aplicacao vazia:

```bash
vendor/bin/fxartisan app:init
```

O comando cria `app/`, `bootstrap/`, `config/`, `database/migrations/`, `public/`,
`resources/views/`, `routes/` e `storage/`.

### Rotas e middleware

```php
$router->get('/clientes/{id}', [ClienteController::class, 'show']);
$router->post('/clientes', [ClienteController::class, 'store'])
    ->middleware(Authenticate::class);
$router->resource('/clientes', ClienteController::class);
```

Middleware implementa `Fx\Framework\Middleware\Middleware` e recebe `Request` e
o callback `$next`.

Novas aplicações geradas registram `VerifyCsrfToken` no router web. Envie `_csrf`
no corpo ou `X-CSRF-TOKEN` no cabeçalho das operações de escrita. Em Smarty,
`-{csrf_field}-` gera o campo oculto. Aplicações existentes devem adicionar o
middleware e o campo explicitamente; consulte a [migração](docs/index.html#historico).
Routers criados diretamente continuam sem proteção CSRF automática.

### Validacao

```php
$data = $request->validate([
    'name' => 'required|string|max:255',
    'email' => 'required|email',
]);
```

Regras disponiveis: `required`, `nullable`, `string`, `integer`, `numeric`,
`boolean`, `array`, `email`, `min`, `max`, `in` e `confirmed`.

### Autenticacao e autorizacao

Implemente `UserProvider` para consultar os usuarios da aplicacao e use
`SessionGuard` para `attempt`, `login`, `logout`, `user` e `check`. O `Gate`
registra abilities e oferece `allows` e `authorize`.

### Migrations e CRUD

```bash
vendor/bin/fxartisan make:migration create_clientes_table
vendor/bin/fxartisan migrate
vendor/bin/fxartisan migrate:rollback
vendor/bin/fxartisan make:crud Cliente
```

`make:crud` gera model Eloquent, request validado, controller REST, migration,
views Smarty e cinco rotas resource.
