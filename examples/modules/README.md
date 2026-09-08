# Exemplo de módulos

Na pasta deste exemplo:

~~~powershell
composer install
php vendor/bin/fxartisan module:enable hello
php index.php
php vendor/bin/fxartisan module:doctor
php vendor/bin/fxartisan module:disable hello
php index.php
php verify.php
~~~

O primeiro index imprime Modulo hello carregado; após desativar, Modulo hello inativo.
verify.php usa estado temporário próprio. Não inclui HTTP, banco ou templates.

[Ajuda do módulo](modules/hello/help.html) · [Manual do gerenciador](../../packages/modules/docs/index.html)
