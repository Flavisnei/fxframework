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


MariaDB opcional: configure database em config/admin.php e config/contacts.php conforme [a ajuda de migração](modules/contacts/docs/index.html#mariadb-admin). Crie previamente o banco e execute os mesmos comandos de inicialização. Sem configuração de Contatos, permanece SQLite. Não há cópia automática de dados entre bancos.

Se você já usava o exemplo e `composer install` avisar que o lock está desatualizado,
execute nesta pasta:

```powershell
composer update "fxfavalessa/*" --minimal-changes --no-plugins --no-scripts
composer install --no-plugins --no-scripts
```

Isso sincroniza os pacotes FX copiados de `../../packages` e o lock local do exemplo.
O segundo comando deve terminar sem o aviso de lock desatualizado. Não apague o
banco nem execute novamente a criação de contas para resolver esse aviso. Os locks
dos exemplos são locais e ignorados pelo Git. A mensagem de financiamento é informativa.
