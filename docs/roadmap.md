# Entregas e revisão

Cada etapa inclui implementação, testes pertinentes, revisão do diff, ajuda HTML e
changelog. A conclusão depende de evidência, não de promessa de perfeição.

| Etapa | Entrega | Critério de revisão | Estado |
| --- | --- | --- | --- |
| 0 | Git local e marco v1.0.01 | Código original preservado em tag anotada | Concluída |
| 1 | Arquitetura e central de ajuda | Navegação, busca offline, exemplos atuais e planos separados | Entregue; revisão visual pendente |
| 2 | Correções de comportamento | Regressões para validação, Request, 404; desenho e testes CSRF | Concluída |
| 3 | Core e dependências opcionais | Instalar mínimo sem banco/view/admin; compatibilidade documentada | Pendente |
| 4 | Adaptador WordPress | Plugin exemplo e teste de coexistência sem kernel/sessão duplicados | Pendente |
| 5 | Módulos e presets via Artisan | Instalar, ativar, atualizar e diagnosticar dependências em ambiente temporário | Pendente |
| 6 | Auth e Admin FX Windows | Login, usuários, perfis, permissões no servidor e recuperação de senha | Pendente |
| 7 | CRUD AJAX/JSON | Validação por campo, paginação, erros de rede e módulo exemplo completo | Pendente |
| 8 | Escala e distribuição | Benchmarks reproduzíveis, versões suportadas, CI e guia de atualização | Pendente |

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

## Próxima parte

Iniciar etapa 3 pelo mapa de dependências e pela extração mínima do Core. Preservar
compatibilidade e validar instalação mínima sem banco, templates e painel. A revisão
visual da ajuda permanece pendente pela limitação registrada na etapa 1.
