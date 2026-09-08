# FX Example para WordPress

Plugin de demonstração: Configurações → FX Example, formulário AJAX e endpoint
REST protegido por manage_options e pela autenticação/nonce do WordPress.

1. No checkout, execute `composer install --working-dir=examples/wordpress --no-dev`.
2. Copie esta pasta, incluindo vendor, para `wp-content/plugins/fx-example` em um
   site de desenvolvimento.
3. Ative FX Example e abra sua página em Configurações.

[Ajuda HTML](help.html). O manifesto usa caminhos do checkout; execute Composer
antes de copiar, ou ajuste repositories no local de destino.

A opção `fx-example:name` é preservada ao desativar. Nenhum usuário, role, tabela,
sessão PHP ou login próprio é criado pelo plugin. O ambiente de teste cria usuários
apenas para a verificação, separadamente do plugin.
