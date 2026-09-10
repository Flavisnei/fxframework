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

## Recuperação de senha opcional

O exemplo inclui o transporte symfony/mailer. A recuperação só aparece quando
config/mail.local.json existe. Copie a estrutura de config/mail.php: informe dsn,
from, admin_url HTTPS terminado em /admin e key com 64 caracteres hexadecimais.
Guarde o arquivo fora do Git. Não troque a chave com mensagens pendentes.
Execute `php vendor/bin/fxartisan admin:mail --init` antes de abrir o painel.
Consulte `php vendor/bin/fxartisan admin:mail --status` sem enviar mensagens.
Depois de solicitar recuperação para uma conta existente, execute
`php vendor/bin/fxartisan admin:mail --limit=1` para tentar enviar uma mensagem.
O comando de envio deve rodar em outro terminal, mantendo o servidor ativo.
Confira entrada/spam e use o link em até 30 minutos. O link é de uso único.
Falha SMTP não confirma entrega; confira remetente autorizado e regras do provedor.
Este exemplo não compartilha contas nem fila com examples/admin.
