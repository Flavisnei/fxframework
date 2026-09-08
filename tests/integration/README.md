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
