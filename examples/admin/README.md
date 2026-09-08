# Exemplo FX Admin

Na pasta examples/admin:

~~~powershell
composer install
php vendor/bin/fxartisan admin:init --email=seu-email@example.com
php vendor/bin/fxartisan module:enable fx-admin
php -S 127.0.0.1:8080 -t public public/index.php
~~~

Senha solicitada sem exibição. Abra http://127.0.0.1:8080/admin. O exemplo é HTTP local
com secure_cookie=false; em HTTPS use true. Configure entrega de recuperação e logs
conforme [o manual HTML](../../packages/admin/docs/index.html).

A partir da raiz do repositório, php tests/integration/admin.php testa CLI e HTTP
com banco e servidor temporários, sem alterar contas deste exemplo.
