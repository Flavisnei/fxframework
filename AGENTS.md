# Desenvolvimento do FX Framework

## Direção

Framework PHP pequeno, modular e instalável sob demanda. Deve atender desde plugins
WordPress até aplicações administrativas e APIs. Não adicionar dependências ao núcleo
por conveniência de um módulo. O desenho está em docs/architecture.md.

## Entregas por etapa

- Preservar a tag histórica v1.0.01; nunca movê-la ou reescrevê-la.
- Trabalhar em mudanças pequenas, revisar o diff e verificar o comportamento afetado.
- Não anunciar recursos planejados como disponíveis. Não prometer ausência de bugs.
- Antes de mudar contratos públicos, documentar compatibilidade e migração.
- Corrigir defeitos reproduzíveis antes de ampliar a arquitetura.

## Documentação faz parte da mudança

- Toda alteração de uso, comando, configuração, comportamento ou módulo deve atualizar
  docs/index.html no mesmo conjunto de alterações e registrar a mudança em CHANGELOG.md.
- A ajuda deve explicar objetivo, requisitos, passos, exemplo, resultado esperado,
  problemas comuns e dicas. Escrever em português claro.
- Recursos ainda não implementados devem estar identificados como planejados.
- Preservar âncoras existentes para não quebrar links de ajuda contextual.
- Manter a ajuda utilizável offline, sem CDN, rastreamento ou ferramentas de build.
- Conferir busca, navegação e legibilidade ao alterar a interface da ajuda.
- Atualizar docs/roadmap.md com evidências e limitações da etapa concluída.

## Verificação

- Executar php vendor/bin/phpunit --do-not-cache-result nas alterações PHP relevantes.
- Acrescentar testes de regressão para defeitos, com exemplos que falhavam antes.
- Usar banco e diretórios temporários em testes de migrations e instalação.
- Medir desempenho antes de afirmar ganhos; comparar ambientes equivalentes.
- Não incluir vendor, segredos, logs, caches ou dados reais nos commits.
