# Histórico

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
