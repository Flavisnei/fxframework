# Arquitetura alvo do FX

Estado: etapas 3A, 3B, 4, 5A, 5B e 6 (SQLite) implementadas. Core, componentes opcionais e adaptador
WordPress em packages/, com a distribuição completa preservada.

## Extração inicial

fxfavalessa/fx-core usa o namespace Fx\Framework e fornece CoreApplication,
Container, ServiceProvider e Config\Repository. Sua única dependência direta é
illuminate/container, que traz illuminate/contracts e dois contratos PSR.
Não há dependência de Illuminate HTTP/Database, Smarty ou Symfony no Core.

O pacote completo usa a mesma fonte em packages/core/src e declara replace com
self.version, evitando instalar duas cópias dessas classes. A estratégia segue
o [schema do Composer](https://getcomposer.org/doc/04-schema.md#replace).
Nenhum pacote foi publicado; instalação local usa repositories do tipo path.

Namespaces existentes permanecem. A propriedade app de ServiceProvider agora é
CoreApplication; providers web permanecem vinculados funcionalmente à Application
completa. O Core não altera o container global por padrão, mas a Application web
e Container() mantêm o comportamento global anterior por compatibilidade.

O exemplo examples/minimal instala por cópia, usando vendor próprio e verificando
ausência das camadas opcionais. Não afirmar compatibilidade WordPress apenas com
esse teste: a etapa 4 acrescentou o adaptador e verificou coexistência de instâncias
com hooks nativos; conflitos de versões entre plugins continuam fora dessa cobertura.

## Princípios

1. Instalação mínima sem banco, templates, autenticação ou painel obrigatórios.
2. Recursos opcionais em pacotes Composer. Artisan coordena a experiência; Composer
   resolve versões e baixa pacotes durante instalação/deploy, nunca em uma requisição.
3. Aplicação hospedeira controla o ciclo de vida. O adaptador WordPress usa hooks,
   usuários, capabilities e conexões do ambiente, sem iniciar sessão ou kernel paralelo.
4. Serviços compartilhados não guardam dados de requisições anteriores.
5. JSON e AJAX prioritários no painel, paginação e carregamento de assets sob demanda.
6. Autorização no servidor; validação e proteção CSRF integradas ao contexto apropriado.
7. Ajuda HTML offline como parte de cada recurso entregue.

## Limites propostos

| Componente | Responsabilidade | Dependências conceituais |
| --- | --- | --- |
| Core | Container, configuração, contratos e providers | Nenhuma camada superior |
| HTTP | Request, response, rotas, pipeline, exceções | Core |
| Database | Conexões e migrations; ORM em integração opcional | Core |
| Auth | Contratos de identidade e autorização; adaptadores de sessão | Core, HTTP no adaptador web |
| View | Templates e integração de helpers | Core |
| FX Windows | Janelas e comportamento visual | Navegador; independente do Core |
| Admin | Login, usuários, perfis, permissões, menu e dashboard | HTTP, Auth, Modules, FX Windows e PDO SQLite |
| WordPress | Adaptação ao ambiente WordPress | Core; ambiente hospedeiro |
| Artisan | Instalação, diagnóstico e geradores | Core; Console apenas no contexto CLI |

Pacotes definidos: fx-core, fx-http, fx-database, fx-view, fx-console, fx-auth,
fx-validation, fx-windows, fx-wordpress, fx-modules e fx-admin. Todos continuam em desenvolvimento local.
Não trocar container ou ORM antes de comparar compatibilidade, tamanho e custo.
O pacote completo é mantido como ponto de compatibilidade durante a extração.

## Dependências entregues na etapa 3B

- HTTP requer Core e Validation; não requer banco, Auth ou views. Seu handler usa
  instanceof para reconhecer exceções opcionais sem carregar esses pacotes obrigatoriamente.
- Database usa Eloquent; Core e HTTP não são requisitos. Separar ORM é trabalho futuro.
- View usa somente Smarty. Helpers de CSRF exigem HTTP quando chamados, com mensagem
  explícita se ausente; helpers FX Windows exigem assets publicados pela aplicação.
- Console requer Core e Symfony Console. Comandos de cada camada só são registrados
  quando suas classes estão disponíveis. CRUD exige HTTP, Database e View.
- Auth requer HTTP; é composto pelas primitivas existentes, sem telas administrativas.
- Validation usa polyfill mbstring. Windows inclui assets e localizador, sem bibliotecas PHP.
- Os guias HTML de cada pacote são distribuídos em docs/index.html dentro do pacote.
- A distribuição completa inclui todos os fontes com autoload único e replace por versão.

Nove instalações isoladas em examples verificam limites e execução real. Os manifestos
locais usam path com wildcard e minimum-stability dev, pois repositórios de dependências
não são herdados pelo consumidor. Releases estáveis ainda serão preparadas.

## Módulos

O contrato schema 1 declara ID, versão, provider, dependências por ID, ajuda HTML
e metadados opcionais de rotas por contexto, migrations, assets e permissões.
Compatibilidade PHP/pacotes e restrições de versão são resolvidas pelo Composer.
Instalado significa pacote presente; ativo significa habilitado naquela aplicação.
A ativação deve validar dependências e não executar migrations implicitamente em HTTP.
Desativar não apaga dados. Atualizações devem preservar personalizações e informar
passos de migração. Remoção de dados será uma operação explícita e separada.

A etapa 5A entrega module:list, module:install e preset:install no Console. O catálogo
contém os nove componentes FX. Presets minimal (Core), api (HTTP + Console) e
wordpress (adaptador) adicionam requisitos em um composer.json existente. Não removem
dependências nem geram aplicações. Composer resolve versões e instala; Artisan não
reescreve manifestos ou lock por conta própria. Scripts e plugins ficam desabilitados.

A instalação usa proc_open com argumentos separados, sem shell, e oferece dry-run.
A etapa 5B entrega fx-modules, dependente apenas do Core. Registro explícito em
config/modules.json; estado de ativação em storage/framework/modules.json. Providers
são registrados por dependência antes do boot. CLI opcional habilita status, doctor,
enable, disable e refresh. Atualização do código permanece no Composer; refresh
valida e reconhece versões após revisão, sem executar migrations ou conceder acesso. Não há create; preset admin adiciona Admin e Console, sem gerar configuração.

## Segurança e operação

Etapa 2 implementa registro do Request antes de controllers/middleware, correções de
validação, 404 de modelos ausentes e CSRF web. O Admin SQLite acrescenta sessão com expiração, cookies configuráveis, logging por
callback, recuperação por integração de entrega e limitador persistente SQLite. Permissões seguem recurso.acao, com negação
por padrão; acesso a registros pode exigir políticas adicionais.

O adaptador WordPress delega hooks, REST, usuário, capabilities, wpdb e opções ao
host. Foram testadas duas instâncias e hooks nativos em WordPress 6.4.3 com banco
descartável. Ainda falta isolamento de versões conflitantes no artefato distribuído,
multisite e matriz com versões recentes. Não duplicar autenticação do WordPress.

## Desempenho

Medir endpoint JSON vazio, listagem paginada e operação autenticada. Registrar PHP,
extensões, OPcache, servidor, banco, hardware, carga, aquecimento e revisões comparadas.
Coletar p50/p95, memória, consultas, bytes de resposta, assets e tamanho instalado.
Só definir orçamento numérico depois de obter a linha de base. Menor quantidade de
dependências não demonstra, sozinha, menor latência ou maior escalabilidade.

## Documentação

docs/index.html é a entrada pública atual. Cada módulo futuro deve fornecer ajuda
com âncoras estáveis, exemplos e permissões. O Admin inclui links contextuais para seu manual. Busca unificada no painel ainda
não existe; o manual usa índice e a central mantém busca local. A página inicial funciona sem servidor,
com índice lateral e busca local. Mudanças de comportamento atualizam ajuda e changelog.

## Admin inicial (etapa 6)

fx-admin depende de Auth (que requer HTTP), Modules e Windows, além de PDO/SQLite.
Não depende de Eloquent ou Smarty. O completo reúne as mesmas fontes e agora exige
ext-pdo/ext-pdo_sqlite; o mínimo continua sem essas extensões obrigatórias.

Schema e primeira conta são criados pelo admin:init; bootstrap nunca migra banco.
Contas têm um perfil; consultas de identidade e permissões são renovadas por operação.
Estado de módulos continua separado do banco. Recuperação precisa de callback de
entrega configurado; os testes não enviam email. Backend distribuído, outros bancos,
MFA, OAuth, prefixo customizável e benchmarks ficam fora deste escopo inicial.

## SMTP opcional (Admin 0.1.1)

A recuperação pode usar fila SQLite com payload AES-256-GCM e chave externa.
HTTP apenas enfileira; admin:mail processa em CLI, com reservas e tentativas limitadas.
Symfony Mailer é instalado pelo consumidor, sem dependência obrigatória no Core/Admin.
TLS implícito obrigatório em produção; STARTTLS ainda não suportado. A fila tem
semântica de entrega pelo menos uma vez, sem garantia de entrega na caixa postal.
