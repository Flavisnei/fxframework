# Integração WordPress

O teste wordpress.php usa WordPress e wpdb reais, não stubs. Ele grava opções em
um site descartável. Não executar em um site de produção ou com dados do usuário.

## Preparação

1. Instale as dependências com `composer install --working-dir=examples/wordpress --no-dev`.
2. Prepare um WordPress descartável com banco separado e sem envio de e-mails/cron.
3. Instale o exemplo FX Example como plugin ativo ou carregue seu arquivo principal
   por um mu-plugin antes de plugins_loaded.
4. Crie os usuários `fx_review_admin` (administrator) e `fx_review_subscriber` (subscriber).
5. Crie o arquivo vazio `.fx-test-environment` na raiz desse WordPress.
6. Aponte `FX_WP_TEST_ROOT` para a pasta que contém wp-load.php e execute o teste:

```powershell
$env:FX_WP_TEST_ROOT = 'C:/caminho/do/wordpress-descartavel'
php tests/integration/wordpress.php
```

Resultado esperado: 28 verificações reais passaram. O teste usa o autoload isolado
do exemplo; não carrega vendor da raiz do framework. O teste de nonces chama a
verificação nativa de cookies separadamente, pois rest_do_request é despacho interno
e não reproduz sozinho a autenticação de uma requisição HTTP externa.

Cobertura: dois adaptadores e hook nativo, containers/configurações/opções separados,
mesmo wpdb, sem sessão/kernel FX, usuário dinâmico, capacidades, endpoints distintos,
gravação JSON, sanitização, tipos e limites inválidos, anônimo/assinante/admin,
nonce válido, inválido e ausente.

Execução desta etapa: WordPress 6.4.3 (código local), PHP 8.1.12 e MariaDB 10.4.27
em banco temporário ouvindo apenas em 127.0.0.1, separado dos sites existentes.
Isso não valida automaticamente WordPress mais recente, multisite, UI no navegador
ou plugins com versões conflitantes de dependências. Desligue o banco temporário
após o teste. Os testes PHPUnit normais não carregam esse script automaticamente.

## Instalação e presets com Composer real

Execute na raiz do framework, após composer install:

~~~powershell
php tests/integration/installation.php C:/composer/composer.phar
~~~

O argumento é opcional se Composer estiver no PATH; no Windows o teste procura
composer.phar. Requer acesso aos repositórios Composer ou cache disponível, PHP
com as extensões dos pacotes e proc_open habilitado. Não integra a suíte PHPUnit
normal para evitar downloads implícitos em testes unitários.

Cria três projetos em uma pasta fx-install-review-* nova no temporário do sistema.
A saída informa o caminho; os arquivos ficam disponíveis para inspeção. Executa
os presets minimal/api/wordpress, simulações, instalação individual de Windows,
reaplicação de minimal e conflitos deliberados (^999.0). Os erros de dependências
são esperados; o resultado final deve ser OK: 26 verificacoes. Também verifica
que scripts não rodaram e executa probes em processos com autoload próprio.

Não acessa banco ou WordPress em execução. O teste WordPress deste arquivo apenas
confere presença do adaptador sem camadas HTTP; os testes do host estão descritos
acima. Validado com Composer 2.8.4, PHP 8.1.12 e Windows; Unix ainda não testado.

## Admin via HTTP real

Instale o vendor próprio de examples/admin e execute na raiz:

~~~powershell
php tests/integration/admin.php
~~~

Cria projeto e SQLite temporários, inicializa uma conta aleatória por admin:init,
ativa fx-admin e inicia PHP em porta livre de 127.0.0.1. Verifica cookies HttpOnly
e SameSite, CSRF, login com renovação de sessão, listagem, assets, ajuda e logout.
Servidor termina em finally; arquivos ficam no temporário informado. Nenhum usuário
existente é alterado. Exige proc_open, PDO/SQLite e allow_url_fopen para o cliente HTTP.
O teste não controla navegador e não envia email.
