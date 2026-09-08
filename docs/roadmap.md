# Entregas e revisão

Cada etapa inclui implementação, testes pertinentes, revisão do diff, ajuda HTML e
changelog. A conclusão depende de evidência, não de promessa de perfeição.

| Etapa | Entrega | Critério de revisão | Estado |
| --- | --- | --- | --- |
| 0 | Git local e marco v1.0.01 | Código original preservado em tag anotada | Concluída |
| 1 | Arquitetura e central de ajuda | Navegação, busca offline, exemplos atuais e planos separados | Entregue; revisão visual pendente |
| 2 | Correções de comportamento | Regressões para validação, Request, 404; desenho e testes CSRF | Concluída |
| 3A | Core independente | Instalar mínimo sem banco/view/admin; compatibilidade documentada | Concluída |
| 3B | Pacotes opcionais | Separar HTTP, banco, views e CLI com instalação individual verificada | Concluída |
| 4 | Adaptador WordPress | Plugin exemplo e teste de coexistência sem kernel/sessão duplicados | Concluída no escopo testado |
| 5A | Instalação e presets via Artisan | Composer real, simulação, conflitos e isolamento em projetos temporários | Concluída no escopo testado |
| 5B | Ciclo de módulos | Contrato, ativação, desativação, reconhecimento de atualizações e diagnóstico | Concluída no escopo testado |
| 6 | Auth e Admin FX Windows | Login, usuários, perfis, permissões e recuperação configurável | Entregue para SQLite; revisão visual pendente |
| 7 | CRUD AJAX/JSON | Contatos: validação por campo, paginação, conflitos e ajuda | Entregue no escopo SQLite; revisão visual pendente |
| 8 | Escala e distribuição | Benchmarks reproduzíveis, versões suportadas, CI e guia de atualização | 8A e 8B entregues localmente: benchmark, CI configurada e guia; matriz remota e distribuição pendentes |

## Evidências iniciais

- Baseline: commit c6b83e3, tag v1.0.01. Versão interna original 0.2.0 preservada.
- Análise anterior: PHPUnit 22 testes / 55 assertions; auditoria Composer sem advisories.
- Reproduzidos: required com nullable aceita ausência; string numérica usa tamanho
  numérico; array vazio passa required; Request no construtor aponta para outra URI;
  ModelNotFoundException resulta em 500.
- Etapa 1 acrescenta documentação; não corrige esses defeitos nem cria pacotes modulares.
- Verificação da ajuda: 15 tópicos, 29.582 bytes entre HTML/CSS/JS, links locais
  existentes, nenhuma âncora duplicada e sintaxe JavaScript válida. Comandos conferidos
  com php bin/fxartisan list --raw. Revisão visual/interativa pendente: navegador
  integrado sem conexão e Chrome indisponível para automação nesta sessão.

## Etapa 2

- Os novos testes reproduziram 14 falhas antes das correções.
- Cobertura de required/nullable, campos ausentes, limites numéricos/textuais,
  in com array, integer com booleano, Request em construtores, 404 e cabeçalhos HTTP.
- CSRF integrado às rotas web novas e ao formulário Smarty gerado; API sem sessão
  continua opt-in. Aplicações existentes têm passos de migração no manual HTML.
- Integração executa app:init e make:crud em pasta temporária, com SQLite em memória,
  verifica rejeição sem token, renderiza token real, grava JSON validado e consulta 404.
- Regressão adicional confirmou token antigo no cache Smarty; o helper agora é
  não cacheável. Suíte final: 55 testes e 134 assertions, todos passando em PHP 8.1.12.
- Ajuda: 15 tópicos, sem links locais quebrados nem âncoras duplicadas. Interface
  preservada; revisão visual continua pendente. git diff --check sem erros.

## Evidências da etapa 3A

- composer validate --strict passou para os manifestos do framework e Core.
- Instalação real por cópia em examples/minimal: Core + quatro dependências externas;
  nenhuma classe HTTP, Eloquent ou Smarty disponível no autoload isolado.
- examples/minimal/verify.php passou para configuração, injeção e providers, sem
  alterar sessão ou substituir o container global do hospedeiro.
- Consumidor completo em pasta temporária instalou framework e satisfez o requisito
  Core pelo replace; rota JSON respondeu corretamente usando o autoload do consumidor.
- Suíte completa: 59 testes / 158 assertions, todos passando em PHP 8.1.12.
- Ajuda central (16 tópicos) e guia distribuído do Core sem referências locais
  quebradas nem IDs duplicados. Revisão visual permanece pendente.
- O lock da raiz manteve as versões. Os 44 pacotes externos do completo versus quatro
  do Core demonstram isolamento de dependências, não um benchmark de velocidade.
- O exemplo usa @dev intencionalmente durante desenvolvimento local; Composer
  avisa sobre restrição aberta. Releases publicadas continuam pendentes.

## Evidências da etapa 3B

- Nove instalações reais com vendor/autoload próprios: HTTP, Database, View,
  Console, Auth, Validation, Windows, web sem banco e combinação modular completa.
- Todas passaram verificando classes/dependências ausentes e uso de HTTP, SQLite,
  renderização Smarty, validação, guard, CLI, bootstrap gerado e publicação de assets.
- Guias HTML distribuídos por pacote, central atualizada e migração documentada.
- Os nove manifestos (completo e oito componentes) passaram composer validate --strict.
- O Core mínimo continuou passando sua verificação; suíte completa com 59 testes e
  158 assertions. Nove guias HTML sem links locais quebrados nem IDs duplicados.
- Executável Composer do CLI básico testado com list --raw, sem comandos opcionais.
- A independência de instalação está entregue; HTTP continua usando Illuminate HTTP
  e Database continua com Eloquent. Redução adicional de dependências exige outra revisão.

## Evidências da etapa 4

- fx-wordpress instala Core e quatro dependências externas, sem HTTP FX, Eloquent ou Smarty.
- Plugin de exemplo delega login ao WordPress e usa manage_options, REST/JSON e nonce nativo.
- 28 verificações reais em WordPress 6.4.3 / PHP 8.1.12 / MariaDB 10.4.27, em cópia
  e banco descartáveis. Dois adaptadores e hooks nativos coexistem sem trocar wpdb,
  sessão ou container global. Configurações, rotas e opções permanecem separadas.
- Tipos inválidos inicialmente causavam TypeError no exemplo; validador REST nativo
  explícito e sanitização defensiva corrigiram a falha. Casos inválidos retornam 400.
- Permissões 401/403 e nonces nativos verificados. Despacho REST e autenticação por
  cookie foram testados separadamente; não foi uma simulação pelo navegador.
- Limites: versão específica do WordPress, sem teste multisite, UI ou isolamento
  de bibliotecas de versões conflitantes. Documentação mantém esses limites visíveis.
- Validação final: 59 testes / 158 assertions do framework passaram, manifestos
  válidos, JavaScript com sintaxe válida e links HTML locais conferidos. Banco
  MariaDB temporário encerrado; sites existentes não foram alterados.

## Evidências da etapa 5A

- module:list apresenta nove componentes; module:install e preset:install funcionam
  com Console isolado ou distribuição completa. Nenhuma dependência nova no Core.
- Três projetos temporários instalaram os presets minimal, api e wordpress com
  Composer 2.8.4 / PHP 8.1.12 / Windows. 26 verificações reais passaram.
- Simulações preservaram manifesto/lock/vendor; falhas de resolução restauraram
  manifesto/lock. Scripts não foram executados. Instalação individual de Windows
  e reaplicação de minimal preservaram os requisitos existentes.
- Processos PHP separados verificaram os autoloads consumidores, Core, resposta
  JSON, comandos disponíveis, adaptador WordPress e assets, sem Eloquent/Smarty/Auth.
- Suíte completa: 66 testes / 186 assertions. Cobertura de argumentos separados,
  caminhos com espaços, erros, catálogo, preset indisponível e redirecionamento
  de manifesto por variável de ambiente.
- Limites: catálogo de componentes, não registro de módulos ativos; sem scaffold,
  preset admin, publicação automática ou migrations. Versões FX somente locais.
  Interrupções durante instalação não têm garantia de rollback de vendor.
- Ajuda central e manual Console atualizados. Revisão visual continua pendente.

## Evidências da etapa 5B

- fx-modules separado, requer somente Core. Console mínimo não registra os comandos
  de ciclo sem esse pacote. Nenhuma dependência externa nova na distribuição completa.
- Manifestos JSON explícitos com schema 1; ajuda HTML obrigatória. Recursos resolvidos
  dentro do módulo. Estado por aplicação com lock exclusivo e substituição atômica.
- 77 testes / 222 assertions: ordem, ciclos, dependências ausentes, desativação
  bloqueada, estado corrompido, preflight de providers, recursos externos,
  versões alteradas, preparação de novas dependências e CLI na raiz correta.
- Exemplo modules com vendor próprio, sem HTTP/Eloquent/Smarty/Auth: ativa, registra
  provider, desativa e verifica que o novo bootstrap não carrega o serviço.
- Atualização de código continua a cargo do Composer. refresh apenas valida e
  reconhece versões; não migra banco, publica assets ou concede permissões.
- Não há rollback dos efeitos de providers, lock distribuído ou recarga de workers.
  Operações de deploy e distribuição do estado precisam ser coordenadas pelo projeto.
- Core mínimo reinstalado e verificado com quatro dependências externas; Console
  isolado também verificado, sem comandos de ciclo. Lock completo manteve versões.
- Manual do pacote, ajuda Console, central e exemplo atualizados. Revisão visual
  permanece pendente; os testes são de execução PHP e estrutura da documentação.

## Evidências da etapa 6 (Admin SQLite)

- Pacote fx-admin opcional, sem Eloquent/Smarty, com FX Windows, contas, perfis,
  permissões e operações de módulos já registrados. Preset admin e admin:init.
- Sessão com cookies HttpOnly/SameSite, Secure configurável, ID/CSRF renovados,
  expiração por tempo e invalidação após mudanças na conta. Permissões lidas por operação.
- Recuperação com hash de token, expiração e uso único; callback de entrega e logger.
  Nenhum email real enviado. Exemplo mantém entrega desabilitada até configurar.
- Suíte: 90 testes / 282 assertions. Cobre autenticação, CSRF, acesso direto sem
  permissão, último administrador, gestores delegados, contas inativas, expiração,
  recuperação, rate limit persistente, paginação, conflitos e tipos inválidos.
- 18 verificações HTTP/CLI reais usando autoload exclusivo de examples/admin:
  conta aleatória em banco temporário, ativação de fx-admin, cookies, CSRF, renovação
  do ID, consulta autenticada, recursos HTML/JS/CSS, ajuda e logout. Servidor encerrado.
- Core mínimo continua verificado com quatro dependências externas. Nenhuma versão
  de biblioteca do lock completo mudou; o completo passa a exigir PDO/SQLite.
- Ajuda central e manuais atualizados; 19 tópicos, links/âncoras e busca conferidos.
  Sintaxe JavaScript válida. Navegador integrado recusou conexão; não há teste visual.
- Limites: SQLite, prefixo /admin fixo, um perfil por usuário, sem MFA/OAuth,
  infraestrutura distribuída, benchmark ou serviço de email configurado.

## Continuidade após etapa 7

CRUD de domínio entregue em examples/contacts.
Revisão visual do painel permanece pendente de navegador disponível; escala,
outros bancos e matriz de versões atuais continuam na etapa 8.

## Complemento SMTP — 2026-09-08

- Admin 0.1.1: fila SQLite com AES-256-GCM, chave externa, reserva por trabalhador,
  expiração, substituição de pendentes por destinatário e cinco tentativas máximas.
- admin:mail --init/--status não enviam; processamento SMTP explícito em CLI.
- Oito testes específicos da fila (31 assertions), incluindo chave incorreta,
  payload alterado, reserva, substituição durante entrega e comandos sem credenciais.
- Suíte completa: 98 testes / 313 assertions passaram com acesso ao diretório de
  sessões PHP; o sandbox inicialmente impediu a criação dessas sessões.
- Oito verificações reais de SMTP/MIME com Symfony Mailer 6.4.44 em capturador
  loopback. Corrigida a remoção de dot-stuffing no capturador do teste.
- Nenhuma conexão ao SMTP do usuário ou mensagem externa. Porta, credenciais e
  origem HTTPS ainda pendentes; autorização do remetente precisa ser confirmada.
- Navegador integrado continua indisponível para automação; revisão visual pendente.
- Sem STARTTLS, testes de TLS remoto ou garantia de entrega exatamente uma vez.

## Configuração do SMTP informado — 2026-09-08

- Conexão TLS implícita e autenticação verificadas na porta 465, sem enviar mensagens.
- Configuração local privada excluída do Git; chave gerada aleatoriamente.
- URL HTTPS do painel e validação de aceitação do remetente ainda pendentes.
- A autenticação não comprova autorização do remetente nem entrega na caixa postal.

## Etapa 7 — Contatos — 2026-09-08

- Módulo de negócio separado do Core, sem novas dependências no núcleo.
- Admin 0.1.2 oferece áreas em iframe FX Windows, APIs com sessão/CSRF e
  permissões explicitamente configuradas; compatibilidade descrita na ajuda do pacote.
- CRUD SQLite com email único, erros por campo, páginas de 20, detalhes sob demanda,
  busca literal e controle otimista de versão em edições/exclusões.
- contacts:init explícito, repetível sem apagar dados; ativação inclui fx-admin.
- 103 testes / 361 assertions na suíte completa; cinco testes de Contatos cobrem
  autorização por verbo, CSRF, entradas inválidas, duplicatas, conflitos e paginação.
- 34 verificações reais HTTP/CLI com vendor exclusivo de examples/contacts:
  instalação temporária, CRUD, assets, ajuda, desativação/reativação preservando
  registros e inicialização repetida. Nenhum banco ou credencial existente alterado.
- Sintaxe JS e links/âncoras da ajuda verificados. Navegador recusou conexão;
  revisão visual, teclado e execução dos formulários em navegador permanecem pendentes.
- Limites: SQLite, consulta compartilhada sem isolamento multiempresa, sem auditoria
  específica de contatos, importação/anexos/lixeira ou benchmark. Pacotes locais
  por path, sem catálogo remoto de módulos de negócio. Etapa 8 segue pendente.

## Etapa 8A — referência de desempenho local — 2026-09-08

- benchmarks/run.php inicia processos novos, intercala quatro cenários, descarta
  duas rodadas iniciais e calcula percentis nearest-rank com inventário e ambiente.
- 20 amostras válidas por cenário: mesmo trabalho Core mínimo/completo, rota JSON,
  consulta autenticada com 1.000 contatos sintéticos e paginação de 20.
- Resultado integral e interpretação em docs/benchmarks/2026-09-08.json e .md.
  Rodada de referência sem suíte simultânea; OPcache CLI desativado, PHP 8.1.12.
- Verificadas respostas, cardinalidade, bytes estáveis e ordem dos percentis.
  Sintaxe PHP válida; entradas de CLI inválidas rejeitadas. Suíte: 103 testes /
  361 assertions; exemplo Core mínimo isolado continua passando.
- Não houve otimização de runtime ou mudança de API. Vendors completos contêm
  ferramentas dev; a medição não compara implantações equivalentes de produção.
- Documentados preparo excluído, heap/pico PHP, arquivos aquecidos, bytes somente
  do corpo e limites da amostra. Sem rede/concorrência/contagem instrumentada de SQL.
- Pendências da etapa 8: matriz de versões atuais, CI, atualização/distribuição,
  carga real e outros bancos. Revisão visual continua pendente; nenhum ganho sobre
  Laravel ou suporte a alta escala foi demonstrado.

## Etapa 8B — CI configurada e guia de atualização — 2026-09-08

- Workflow GitHub Actions com PHP 8.1–8.5 em Ubuntu e 8.3 em Windows, sem
  continue-on-error, com permissões de leitura e actions fixadas por commit.
- A raiz usa o lock versionado; exemplos isolados resolvem seus próprios vendors.
- tools/verify.php oferece o mesmo ponto de entrada local/CI e propaga falhas.
- Execução local Windows/PHP 8.1.12 passou nas sete etapas: PHPUnit 103 testes /
  361 assertions, Core mínimo, HTTP isolado, módulos, Admin 18 verificações,
  Contatos 34 e SMTP loopback 8. Nenhum email externo enviado.
- Requisitos de plataforma local aprovados. YAML/matriz inspecionados; em fixture
  temporária, vendor ausente e argumentos inválidos falham, e retorno 7 do primeiro
  subprocesso é propagado sem executar a etapa seguinte.
- Ajuda offline atualizada com requisitos, diagnóstico, homologação, backup
  consistente SQLite, atualização Composer, reconhecimento de módulos e retorno.
- Não houve publicação ou execução no GitHub. PHP 8.2–8.5 e Linux ainda não foram
  verificados nesta máquina; matriz configurada não significa suporte certificado.
- PHP 8.1 e Illuminate 10 permanecem no contrato histórico; modernização de
  dependências, distribuição e testes de carga são pendências explícitas.
