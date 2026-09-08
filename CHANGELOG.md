# Histórico

## 2026-09-08 — Etapa 8L: homologação e versões fixas

- Runner Apache/MariaDB com sessões Admin distintas, CSRF e CRUD JSON de Contatos; bancos descartáveis e evidência de 480 operações sem erros.
- Runner WordPress descartável com checksums oficiais e email bloqueado; verificações multisite de opções e permissões.
- Gerador de repositório aceita SemVer/RC, fixa dependências internas, preserva versões e recusa regravação; publicação permanece explícita.
- Candidata 1.1.0-rc.1 nos onze pacotes, com dependências FX exatas; upgrade/retorno de oito pacotes aprovado preservando conta MariaDB.
- WordPress 7.1: 28 verificações simples e 37 multisite com Illuminate 10 aprovadas; CI inclui coexistência com Illuminate 13.
- Atualização/retorno requerem preservar manifesto, lock e backup do banco; sem alteração de schema nesta etapa.

## Etapa 8K — Admin e Contatos em MariaDB — 2026-09-08

- Dez tarefas da CI aprovadas, incluindo MariaDB 10.4/10.11; suíte local MariaDB 117/643 e integração HTTP 34/34.

- Repositório Composer atualizado com snapshots de 5bbb8c2; pacote Admin baixado por ZIP validado em consumidor separado.

- Admin 0.1.3 aceita database mysql via AdminConfig::connection, com SQLite anterior preservado. Schema InnoDB, bloqueios transacionais para operações críticas e fila cifrada compatível.
- Contatos 0.1.1 aceita config/contacts.php e conexão MariaDB, preservando configuração SQLite padrão e controle de versão.
- admin:init atualizado; DDL separado da primeira conta para respeitar commits implícitos do MariaDB. Não há transferência automática de dados entre bancos.
- Ajuda central e distribuída atualizada com configuração, compatibilidade, migração e limites de bloqueio.

## Etapa 8J — carga e compatibilidade MariaDB local — 2026-09-08

- Teste opt-in via Apache local com token efêmero, leituras e POST transacionais em banco MariaDB temporário. Sem alterações no runtime dos pacotes.
- Confirmadas dez verificações Database no MariaDB local root/sem senha. Rodada de quatro clientes: 200 operações sem erro e contador íntegro.
- Reproduzida ausência de suporte MariaDB em AdminStore e ContactStore. Ajuda documenta a diferença entre Database validado e painel ainda incompatível.
- Navegador e SMTP adiados pelo usuário.

## Etapa 8I — distribuição e homologação — 2026-09-08

- Preparador de snapshots independentes e repositório Composer próprio; onze branches packages/* com fontes e ajuda por pacote. Versões de desenvolvimento, sem Packagist.
- Ferramenta de carga HTTP com sessões separadas, dados sintéticos e limpeza temporária; 200 consultas locais sem erros.
- Teste Database em MariaDB: dez verificações aprovadas; Admin/Contatos permanecem SQLite.
- SMTP autenticou; destinatário recusado com 553 antes de DATA. Entrega não confirmada. Ajuda inclui instalação, reprodução e limitações.

## Etapa 8H — catálogo de instalação extensível — 2026-09-08

- Catálogo HTTPS e instalação do preset mínimo conferidos com revisão fixa; oito tarefas da CI 34258207624 aprovadas.

- module:list, module:install e preset:install aceitam --catalog e --catalog-sha256. Arquivos locais e HTTPS com hash obrigatório; TLS verificado, sem redirecionamentos e com leitura limitada.
- Formato schema 1 valida aliases, pacotes, descrições e presets. Nenhuma inclusão automática de repositórios, scripts, permissões ou migrations. Sem opções novas, comportamento anterior preservado.
- API opcional InstallCatalog no Console; ComposerInstaller mantém argumentos anteriores e aceita catálogo como último argumento opcional. Nenhuma dependência nova no Core.
- Catálogo FX distribuído em docs/catalog.json, ajuda central e do Console atualizadas. Testes cobrem rejeição antes do Composer e instalação real em consumidor temporário isolado.

## Etapa 8G — confirmação de CI e roteiro visual — 2026-09-08

- Confirmadas oito tarefas aprovadas no GitHub Actions para o commit 9b6e01f; evidência com links por tarefa em docs/compatibility/github-actions-34233932875.json.
- Ajuda offline inclui preparo, cenários e resultados esperados para revisão manual de login, janelas, usuários, permissões, Contatos e saída.
- Revisão em navegador permanece pendente: a conexão de automação foi recusada pelo ambiente. Nenhuma alteração de runtime nesta etapa.

## Etapas 8E e 8F — compatibilidade e concorrência — 2026-09-08

- Contratos Composer permitem Illuminate 10 ou 13 e Symfony 6.4 ou 7.4. O lock principal mantém as versões anteriores; Illuminate 13 exige PHP 8.3. Migração e diferenças documentadas na ajuda central e nos quatro pacotes afetados.
- Exemplos fixam a identidade dev-main dos pacotes locais para funcionar também em checkout destacado; teste de regressão cobre os quinze manifests. Core mínimo verifica o inventário antigo e o moderno sem serviços extras.
- CI acrescenta resolução moderna em PHP 8.3/8.5 e exemplos isolados. Resultado remoto ainda deve ser consultado no GitHub.
- Teste multiprocesso temporário de Contatos exige uma gravação vencedora, conflitos nas restantes e preservação do registro. tools/verify.php passa a reunir oito etapas.
- Repositório de fontes e instruções para clonagem acrescentados ao README e à ajuda. A tag histórica v1.0.01 permanece preservada; pacotes não publicados no Packagist.

## Etapa 8D — distribuição local de fontes — 2026-09-08

- tools/package.php gera ZIP de HEAD com manifesto de revisão e SHA-256, sem publicação.
- Filtragem por caminho de dependências, configurações privadas conhecidas, dados, logs e chaves, inclusive entradas de diretórios vazios; destino novo fora do repositório.
- Teste de regressão verifica snapshot, exclusões, checksum e recusa de sobrescrita. CI inclui extensão ZIP.
- Ajuda offline explica geração, conferência, extração, instalação e limites. Não é versão estável nem pacote offline com dependências.


## Etapa 8C — PHP real e registro de comandos — 2026-09-08

- Verificações locais completas executadas em PHP 8.2.12 e 8.5.10 no Windows.
- Corrigido registro de nove comandos: AsCommand substitui metadados estáticos ignorados pelo Symfony Console 7, preservando Console 6.4.
- Regressão cobre nomes e descrições; 104 testes / 379 assertions passam no stack atual e na experiência Illuminate 13/Console 7.4 em PHP 8.5.
- Sem mudança nos requisitos ou lock principal; migração de dependências permanece experimental. Ajuda central e do pacote atualizadas.


## Etapa 8B — CI e atualização — 2026-09-08

- Workflow GitHub Actions para PHP 8.1–8.5 em Ubuntu e 8.3 em Windows; configuração local, execução remota pendente.
- tools/verify.php reúne sete verificações locais/isoladas e encerra no primeiro erro, sem instalar dependências nem enviar SMTP externo.
- Ajuda HTML com requisitos reais, estado de compatibilidade e passos de atualização/retorno em homologação.
- Contratos e dependências principais preservados. Modernização de Illuminate e certificação da matriz ainda pendentes.


## Etapa 8A — benchmark local — 2026-09-08

- Ferramenta CLI sem dependências novas: Core mínimo/completo, JSON e contatos autenticados com dados sintéticos.
- Relatório de 20 amostras por cenário, percentis, memória PHP, bytes do corpo, inventário e ambiente.
- Ajuda HTML com execução, metodologia e limitações; nenhuma mudança em APIs ou otimização de runtime.
- Referência local não é teste de carga nem comparação com Laravel; versões modernas, CI e distribuição continuam pendentes.


## Etapa 7 — Contatos e extensões Admin — 2026-09-08

- Exemplo Contatos isolado: CRUD SQLite, AJAX/JSON, páginas de 20, erros por campo, email único e versão para conflitos de edição/exclusão.
- Admin 0.1.2: addArea, api, FieldErrors e catálogo de permissões configurável. Sem dependências novas no Core.
- Compatibilidade: Administrador reservado recebe todas as permissões explícitas da configuração; outros perfis exigem concessão. Sem alteração de esquema; atualizar pacote e executar module:refresh fx-admin.
- contacts:init cria tabela sem apagar dados; module:enable contacts inclui fx-admin. Desativação preserva banco.
- Ajuda offline central, do Admin e do módulo com instalação, uso, exemplos, problemas e limites.


## Configuração SMTP local — 2026-09-08

- Exemplo Admin aceita config/mail.local.json ignorado pelo Git; ambiente não vazio
  tem prioridade. Envio continua condicionado a FX_MAIL_ENABLED=1.
- Credenciais e chave de cifragem permanecem exclusivamente na configuração privada.


## Em desenvolvimento — complemento SMTP — 2026-09-08

- Admin 0.1.1 acrescenta fila SQLite cifrada com AES-256-GCM, reserva, expiração
  e novas tentativas limitadas. Requisição HTTP apenas enfileira a recuperação.
- Transporte opcional Symfony Mailer ^6.4 por SMTPS, sem dependência nova no Core.
- admin:mail inicializa, consulta ou processa a fila; configuração por ambiente.
- Migração: inicializar fila explicitamente e reconhecer a versão por module:refresh.
- Testes locais não enviam mensagens externas; dados do provedor ainda precisam ser
  configurados e validados antes de habilitar envio real.

## Em desenvolvimento — etapa 6 (Admin SQLite) — 2026-09-07

- fx-admin opcional com login, logout, contas, perfis, permissões e módulos em FX Windows.
- Formulários JSON/CSRF, paginação, sessão com expiração e renovação, limites persistentes
  de tentativas, proteção do último administrador e invalidação após mudanças na conta.
- Recuperação com tokens aleatórios armazenados como hash, expiração e uso único;
  entrega por callback configurável, sem envio real de mensagens nos testes.
- preset admin e admin:init; exemplo standalone e ajuda HTML/contextual.
- Compatibilidade: distribuição completa passa a requerer PDO/SQLite. Núcleo mínimo
  permanece independente. Senhas iniciais nunca são predefinidas ou mostradas pelo CLI.
- Escopo inicial SQLite, sem benchmark, revisão visual concluída ou email pré-configurado.

## Em desenvolvimento — etapa 5B — 2026-09-07

- Novo fx-modules opcional, dependente somente do Core, incluído no completo.
- Manifesto JSON schema 1, registro explícito e ajuda HTML obrigatória.
- Ativação com dependências e bloqueio de ciclos, providers inválidos e recursos externos.
- Desativação preserva dados e é bloqueada por dependentes ativos. Estado com lock e troca atômica.
- CLI module:status/doctor/enable/disable/refresh disponível quando fx-modules está presente.
- Bootstrap explícito registra providers por dependência; atualização exige reconhecer versões.
- Exemplo Hello com autoload isolado e manual offline. Rotas/migrations/assets/permissões
  são metadados e não são executados/publicados/concedidos automaticamente.

## Em desenvolvimento — etapa 5A — 2026-09-07

- Artisan instala componentes do catálogo via module:install e apresenta module:list.
- preset:install adiciona minimal, api ou wordpress a projetos Composer existentes.
- Resolução/download pelo Composer, restrição configurável e simulação dry-run.
- Processo sem shell, scripts/plugins desabilitados e erros propagados ao CLI.
- Presets preservam requisitos não selecionados; não geram aplicações nem ativam módulos.
- Ajuda central e manual distribuído explicam versões locais, requisitos e solução de erros.
- Contrato e ciclo de módulos reservados para 5B; painel/preset Admin permanece na etapa 6.

## Em desenvolvimento — etapa 4 — 2026-09-07

- Pacote fx-wordpress com dependência apenas do Core, usando hooks, REST API,
  usuário atual, capabilities, wpdb e opções nativos.
- Instâncias mantêm containers/configurações separados; rotas e opções são
  prefixadas pelo slug. Não inicia sessão ou kernel e não troca a conexão do host.
- Plugin FX Example com página de configurações, envio JSON, nonce REST e
  autorização manage_options; campo validado e sanitizado no servidor.
- Teste real com WordPress 6.4.3, PHP 8.1.12 e MariaDB 10.4.27 descartável:
  28 verificações passaram. O teste detectou e motivou correção do validador REST
  explícito no exemplo para impedir TypeError em entradas JSON do tipo array.
- Manual HTML do adaptador e ajuda incluída no plugin. Integração descrita em
  tests/integration/README.md, com limites de cobertura explícitos.
- Coexistência validada para instâncias e hooks nativos na versão testada; isolamento
  de versões conflitantes de bibliotecas, multisite e UI no navegador seguem pendentes.

## Em desenvolvimento — etapa 3B — 2026-09-07

- Pacotes independentes fx-http, fx-database, fx-view, fx-console, fx-auth,
  fx-validation e fx-windows, além do Core já extraído.
- Namespaces preservados. Fontes movidas para packages/*/src; pacote completo
  inclui as mesmas fontes e declara replace de cada componente com self.version.
- HTTP não exige banco ou Auth. O handler reconhece suas exceções quando presentes.
  View exige somente Smarty; csrf_field informa a necessidade de HTTP quando usado.
- Artisan básico exige Core e Symfony Console; comandos opcionais aparecem conforme
  os pacotes instalados. make:crud requer HTTP, Database e View.
- app:init funciona sem Database; bootstrap novo só inicializa Eloquent se disponível.
  Aplicações antigas preservam seu bootstrap e precisam revisar essa chamada ao remover banco.
- Assets movidos para fx-windows com localizador próprio; publicação pelo CLI funciona
  tanto na distribuição completa quanto na instalação modular.
- Nove cenários de instalação/verificação e sete guias HTML de componentes adicionados.
- As versões do lock completo foram preservadas. HTTP Kernel e polyfill mbstring,
  que já eram dependências transitivas, agora são requisitos diretos do completo.
- Instalação e resolução ainda via Composer local; presets/download pelo Artisan,
  publicação dos pacotes e dashboard continuam pendentes.

## Em desenvolvimento — etapa 3A — 2026-09-07

- Pacote local fxfavalessa/fx-core extraído com container Illuminate, configuração
  em memória e ciclo de service providers; sem dependências de HTTP, banco ou views.
- CoreApplication não substitui o container global por padrão. Application completa
  preserva o comportamento web e o registro global legado.
- Container e ServiceProvider mantêm namespaces, com arquivos em packages/core/src.
  Autoload do pacote completo inclui o Core, declarado em replace com self.version.
- ServiceProvider passa a receber CoreApplication. Providers que redeclaram a
  propriedade protegida app com Application devem ajustar o tipo ou remover a
  redeclaração. Providers específicos de HTTP continuam exigindo o pacote completo.
- Exemplo mínimo com instalação Composer própria e verificação independente.
- Guia HTML incluído no Core e central de ajuda atualizada. Demais componentes
  ainda não possuem pacotes independentes; isso será a etapa 3B.
- composer.lock da raiz conserva versões: apenas hash do manifesto atualizado.

## Em desenvolvimento — etapa 2 — 2026-09-07

- Corrigidas combinações required/nullable, campos opcionais, arrays vazios, tamanho
  de strings numéricas e validação integer/in sem coerções indevidas.
- Request atual registrado antes de resolver controllers e middleware; FX, Illuminate
  e alias request recebem a mesma instância. Serviços singleton já criados não são recriados.
- ModelNotFoundException retorna 404 sem detalhes de model em produção; exceções HTTP
  preservam status e cabeçalhos. Erros 5xx continuam sem detalhes fora de debug.
- Middleware VerifyCsrfToken aceita corpo de formulário/JSON ou X-CSRF-TOKEN;
  operações web de escrita sem token válido retornam 403. GET/HEAD/OPTIONS não
  iniciam sessão pelo middleware. Tokens na query string são rejeitados.
- app:init registra CSRF no router web; make:crud inclui o helper Smarty csrf_field.
  Routers instanciados diretamente não ganham sessão ou CSRF obrigatórios.
- O campo CSRF é renderizado novamente mesmo quando o cache Smarty está habilitado.
- Migração: arquivos existentes são preservados. Adicionar middleware e campo/token
  aos formulários das aplicações existentes; revisar required e numeric nas regras
  afetadas. Guia detalhado em docs/index.html#historico.
- Sem novas dependências Composer e sem alteração da tag v1.0.01.

## Em desenvolvimento — etapa 1 — 2026-09-07

- Central de ajuda HTML offline com índice, busca sem distinção de acentos, links
  diretos e impressão; guias do código atual e indicação dos recursos planejados.
- Arquitetura modular alvo e roteiro de entregas com critérios de revisão.
- Política de atualização da ajuda em AGENTS.md.
- Cache local do PHPUnit ignorado pelo Git.
- Nenhuma alteração no comportamento PHP nesta etapa.

## v1.0.01 — marco histórico — 2026-09-07

- Estado original do framework preservado no primeiro commit local.
- Tag de identificação solicitada pelo mantenedor; a constante interna 0.2.0 foi
  preservada. A numeração de releases futuras será normalizada ao preparar os pacotes.
- Dependências restauráveis pelo composer.lock; vendor, segredos e caches excluídos.
