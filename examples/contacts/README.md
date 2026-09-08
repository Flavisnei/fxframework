# Exemplo Contatos

Módulo de negócio independente, integrado ao FX Admin e FX Windows.
Veja a [ajuda HTML offline](modules/contacts/docs/index.html) para instalação,
permissões, uso, API, erros e limitações.

Na pasta examples/contacts:

```powershell
composer install
php vendor/bin/fxartisan admin:init --email=seu-email@example.com
php vendor/bin/fxartisan contacts:init
php vendor/bin/fxartisan module:enable contacts
php -S 127.0.0.1:8080 -t public public/index.php
```

Abra http://127.0.0.1:8080/admin. Senha solicitada pelo comando, sem conta padrão.
HTTP local somente; em HTTPS configure secure_cookie=true. Não reutiliza SMTP,
credenciais nem banco de examples/admin. Pacotes por path exigem este repositório.
