# Medições locais do FX

Objetivo: estabelecer uma referência reproduzível antes de otimizar o framework.
Nenhum pacote é instalado e nenhuma configuração real da aplicação é carregada
pelo benchmark. A ferramenta não altera APIs, bancos existentes nem módulos ativos.

Requisitos: PHP 8.1+, PDO SQLite, `proc_open` habilitado, diretório temporário
gravável e vendors instalados na raiz, `examples/minimal`, `examples/http` e
`examples/contacts`. Execute `composer install` em cada pasta ausente. Os exemplos
precisam desta cópia do repositório, pois usam repositórios Composer por path.
Depois de editar um pacote, atualize também sua cópia no vendor do exemplo
(`composer reinstall fxfavalessa/fx-admin`, por exemplo).

Na raiz, em PowerShell:

```powershell
php benchmarks/run.php 20 > "$env:TEMP\fx-benchmark.json"
```

O argumento é o número de amostras por cenário (5–200, padrão 20). São descartadas
duas rodadas iniciais. Progresso sai em stderr; stdout contém exclusivamente JSON.
Resultado esperado: quatro cenários com métricas min, p50, p95 e max, inventário
de dependências e ambiente. Percentis usam nearest-rank: posição `ceil(p * n)`.
Guarde resultados locais fora do Git; o relatório revisado em `docs/benchmarks`
é uma referência sintética, sem dados pessoais.

## Cenários e fronteiras da medição

| Cenário | Vendor | Operação cronometrada |
| --- | --- | --- |
| core-minimal | examples/minimal | Criar Core, configurar, registrar/resolver binding e boot |
| core-complete | raiz | Exatamente o mesmo trabalho do Core, com instalação completa |
| http-json | examples/http | Criar aplicação e rota, boot e despachar resposta JSON fixa |
| contacts-page | examples/contacts | Despachar página autenticada de 20 contatos entre 1.000 sintéticos |

Cada amostra roda em processo PHP novo. `autoload_ms` mede somente o require do
autoload Composer; `operation_ms` mede o trabalho descrito na tabela, incluindo
autoload de classes ainda não usadas. O início do processo PHP, inventário e
preparo dos dados ficam fora dos tempos. O cenário Contatos prepara banco SQLite
**em memória**, esquema, usuário, hash de senha, sessão, aplicação e rota antes
do cronômetro. Não mede login nem bootstrap completo do painel.

Os cenários são intercalados em ordem fixa. O inventário e as rodadas iniciais
aquecem o cache de arquivos do sistema operacional. Portanto, processo novo não
significa disco frio. OPcache CLI do processo pai é transmitido aos filhos;
as demais opções `php -d` do pai não são propagadas. Use a mesma configuração PHP
por arquivo/ambiente para comparar execuções. Xdebug e versões aparecem no JSON.

`memory_before_bytes` e `memory_after_bytes` são uso do heap PHP antes/depois da
operação; `process_peak_bytes` é o pico alocado pelo PHP de **todo o processo**,
incluindo preparo, arredondado pelo alocador. Não é RSS nem memória exclusiva de
uma requisição. `body_bytes` conta somente o corpo JSON sem compressão, sem
cabeçalhos HTTP, cookies, TCP/TLS ou assets. `vendor_bytes` soma tamanhos lógicos
de arquivos, não espaço efetivo em disco nem o restante da aplicação.

## Como interpretar e comparar

Use a mesma máquina, PHP, extensões, configuração de OPcache, versões de
dependências, modo Composer (incluindo dev e otimização de autoload), dados e
quantidade de amostras. Compare o mesmo cenário em duas revisões; repita para
avaliar variação antes de atribuir ganho ao código. O JSON registra versões,
hash do lock e dos scripts. `installed_with_dev` informa o modo Composer;
nem todo exemplo tem dependências de desenvolvimento próprias.

O inventário da raiz atual inclui PHPUnit e outras dependências de desenvolvimento.
A comparação mínimo/completo descreve essas instalações locais, não prova o custo
de uma implantação completa com `--no-dev`. Cenários HTTP e Contatos têm trabalhos
e preparação diferentes e não devem ser ordenados como uma competição de rapidez.

Não há servidor, navegador, rede, concorrência, MySQL/PostgreSQL, WordPress real,
instrumentação de contagem de consultas ou teste Laravel neste benchmark. Não
extrapole requisições por segundo nem afirme suporte a workers persistentes ou
alta escala a partir desses números. Estes continuam sendo trabalhos separados.

## Problemas comuns e dicas

- Vendor ausente: instale na pasta indicada pela mensagem. A raiz não substitui
  o autoload isolado de um exemplo.
- `proc_open` desabilitado: use um PHP CLI de desenvolvimento que permita criar
  subprocessos; a ferramenta encerra sem gerar relatório de sucesso.
- Sessão/diretório temporário sem acesso: ajuste a pasta temporária do processo;
  o teste cria sua própria pasta, destruindo sessão e pasta ao terminar a amostra.
- Grande variação: feche trabalhos pesados, confira antivírus/indexação e repita
  com mais amostras. Não descarte seletivamente resultados desfavoráveis.
- Alteração de configuração/dependências entre execuções: guarde o JSON e explique
  a diferença; não apresente isso como ganho causado por uma otimização isolada.


## Apache e MariaDB locais

`python benchmarks/mariadb-http-load.py --webroot=C:/xampp/htdocs --port=3306 --clients=4 --requests=50` testa GET/POST de FX HTTP/Database. Requer Apache local porta 80, vendor raiz, PDO MySQL e permissão CREATE/DROP DATABASE. Usuário/senha em FX_TEST_MYSQL_USER/FX_TEST_MYSQL_PASSWORD; padrão local root/sem senha. Cria endpoint com token e banco aleatórios e os remove; não usa Admin/Contatos, que não suportam MariaDB. Consulte a ajuda central #mariadb-carga. Não é dimensionamento de produção.
