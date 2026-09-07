# Arquitetura alvo do FX

Estado: direção aprovada na conversa, implementação modular ainda pendente.
O pacote atual continua único e mantém suas dependências existentes.

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

Os nomes e o versionamento dos futuros pacotes serão fixados na etapa de extração.
Não trocar container ou ORM antes de comparar compatibilidade, tamanho e custo.
Manter inicialmente o pacote atual como ponto de compatibilidade é uma opção a avaliar.

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

Corrigir Request antes da resolução de controllers/middleware, regras de validação,
404 de modelos ausentes e CSRF antes de disponibilizar o preset Admin. Implementar
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
