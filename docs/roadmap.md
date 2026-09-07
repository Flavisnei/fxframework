# Entregas e revisão

Cada etapa inclui implementação, testes pertinentes, revisão do diff, ajuda HTML e
changelog. A conclusão depende de evidência, não de promessa de perfeição.

| Etapa | Entrega | Critério de revisão | Estado |
| --- | --- | --- | --- |
| 0 | Git local e marco v1.0.01 | Código original preservado em tag anotada | Concluída |
| 1 | Arquitetura e central de ajuda | Navegação, busca offline, exemplos atuais e planos separados | Entregue; revisão visual pendente |
| 2 | Correções de comportamento | Regressões para validação, Request, 404; desenho e testes CSRF | Pendente |
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

## Próxima parte

Iniciar etapa 2 pela validação e pela ordem de registro do Request. Criar regressões
antes das correções. Rever a semântica pretendida de nullable, required, tamanho de
strings e campos opcionais. Depois tratar exceções HTTP e proteção CSRF web.
