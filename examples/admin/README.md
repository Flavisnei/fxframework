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

SMTP opcional: configure config/mail.php por variáveis de ambiente, instale
symfony/mailer ^6.4 e siga [o guia da fila](../../packages/admin/docs/index.html#smtp).
O exemplo mantém envio desabilitado por padrão; não contém credenciais ou chave.

### Configuração pelo painel

Com o administrador principal, abra **Configurações de email**. Salve os dados SMTP no `.env` da raiz deste exemplo, teste conexão e entrega. Senha vazia preserva a atual. A ajuda completa está em `../../docs/index.html#mail-settings`. A configuração legada continua válida até a migração. Não copie `.env.example` sobre uma configuração existente.

Para processar recuperação automaticamente, mantenha outro terminal aberto nesta pasta:

```sh
php vendor/bin/fxartisan admin:mail --watch
```

O comando relê `.env` a cada ciclo; encerre com Ctrl+C. Produção exige supervisor ou agendamento. O arquivo não é exportado automaticamente para outros programas.
