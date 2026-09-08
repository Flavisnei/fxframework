# Histórico

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
