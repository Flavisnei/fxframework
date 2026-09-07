# Arquitetura alvo do FX

Estado: etapa 3A implementada. Core independente em packages/core, com o pacote
completo preservado. Os demais componentes continuam agrupados; extração na etapa 3B.

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
esse teste: ainda falta o adaptador e prova de coexistência entre plugins.

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
| Admin | Login, usuários, perfis, permissões, menu e dashboard | HTTP, Auth, Database, View e FX Windows |
| WordPress | Adaptação ao ambiente WordPress | Core; ambiente hospedeiro |
| Artisan | Instalação, diagnóstico e geradores | Core; Console apenas no contexto CLI |

O nome fx-core foi fixado; nomes dos demais pacotes e releases serão definidos na etapa 3B.
Não trocar container ou ORM antes de comparar compatibilidade, tamanho e custo.
O pacote completo é mantido como ponto de compatibilidade durante a extração.

## Módulos

O contrato deverá declarar identificador, versão, compatibilidade, dependências,
provider, rotas por contexto, migrations, assets, permissões e entrada de ajuda.
Instalado significa pacote presente; ativo significa habilitado naquela aplicação.
A ativação deve validar dependências e não executar migrations implicitamente em HTTP.
Desativar não apaga dados. Atualizações devem preservar personalizações e informar
passos de migração. Remoção de dados será uma operação explícita e separada.

Os futuros presets serão minimal, api, admin e wordpress. Não existem ainda comandos
create ou module:install. O Composer deve continuar sendo a fonte de resolução.

## Segurança e operação

Etapa 2 implementa registro do Request antes de controllers/middleware, correções de
validação, 404 de modelos ausentes e CSRF web. Antes do preset Admin, implementar
sessões configuráveis, cookies adequados, logging, recuperação de senha e limitação
de tentativas no módulo de autenticação. Permissões seguem recurso.acao, com negação
por padrão; acesso a registros pode exigir políticas adicionais.

WordPress precisa de prova de coexistência com outros plugins e versões de bibliotecas.
Avaliar isolamento de dependências no artefato distribuído; não duplicar autenticação.

## Desempenho

Medir endpoint JSON vazio, listagem paginada e operação autenticada. Registrar PHP,
extensões, OPcache, servidor, banco, hardware, carga, aquecimento e revisões comparadas.
Coletar p50/p95, memória, consultas, bytes de resposta, assets e tamanho instalado.
Só definir orçamento numérico depois de obter a linha de base. Menor quantidade de
dependências não demonstra, sozinha, menor latência ou maior escalabilidade.

## Documentação

docs/index.html é a entrada pública atual. Cada módulo futuro deve fornecer ajuda
com âncoras estáveis, exemplos e permissões. O Admin terá links contextuais e busca
integrada; essa integração ainda não existe. A página inicial funciona sem servidor,
com índice lateral e busca local. Mudanças de comportamento atualizam ajuda e changelog.
