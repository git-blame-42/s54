# O12. Busca e ordenação na resposta JSON da listagem

Pedido, como o professor faria na hora: "A listagem em JSON aceita
`?q=` e `?sort=`, com total e página no corpo." Variante: "parâmetro
inválido responde 422 com os erros em JSON".

Chance: média-baixa. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O12.

Branch no clone: `fvp/o12-busca-ordenacao-json`, empilhada sobre N16
(`fvp/n16-busca-filtro-ordenacao`) e O4 (`fvp/o4-resposta-json`,
trazido por cherry-pick), que partem de `main` (`f31006b` do
público; no bundle `5dedab5`, ver README raiz). O12 usa
`listingFilters()` e `listingPage()` de N16 e a saída JSON de O4
(`acceptJson()` + `renderJson`).

| Commit    | Mensagem                                                |
|-----------|---------------------------------------------------------|
| `f9d7cf3` | (base, N16) `feat: Search, filter and sort admin ...`   |
| `34b8b3e` | (base, O4) `feat: Return JSON for admin events listing` |
| `88ed8e1` | `feat: Search and sort the admin events JSON listing`   |

A mensagem completa do commit de N16 é `feat: Search, filter and sort
admin events together`. O commit de O4 é o mesmo do cenário O4 (só
mudou o pai, por isso o SHA é outro).

## O que mudou

- `app/Controllers/Admin/EventsController.php` (`index`): depois de
  `[$filters, $errors] = Event::listingFilters(...)` (N16), duas
  saídas JSON com *early return*:
  - `$errors !== []` e `acceptJson()`: `http_response_code(422)`,
    `renderJson('admin/events/index', compact('errors'))`, sem
    chamar `listingPage()`;
  - sem erro e `acceptJson()`: `listingPage()` como no HTML e
    `renderJson` com `events`, `paginator`, `filters` e `errors`.
  - O HTML continua como em N16 (aviso e padrão, status 200).
- `app/views/admin/events/index.json.php` (de O4): no topo, `if
  ($errors !== []) { $json['errors'] = $errors; return; }`; no
  sucesso, além de `events` e `page` (O4), `per_page`, `total`,
  `total_pages` e `filters` (os filtros aplicados).
- `EventsControllerTest`: `http_response_code(200)` no `setUp` e
  seis testes novos:
  - `test_index_json_searches_sorts_and_counts` (o do catálogo:
    `q=Oficina`, título desc, total 2, página 1 de 1, `filters`);
  - `test_index_json_filters_by_kind_and_paginates` (7 Oficinas,
    página 2: Oficina 6 e 7, `total_pages` 2);
  - `test_index_json_page_beyond_the_last_returns_the_last`
    (`page=9` com 2 Eventos devolve `"page":1`);
  - `test_index_json_rejects_invalid_params_with_422` (o do
    catálogo: `sort=description`, `dir=up`);
  - `test_index_json_rejects_invalid_search_and_kind_without_listing`
    (`q[]`, `kind=party`: corpo só com `errors`);
  - `test_index_html_with_invalid_param_keeps_status_200_and_warns`.
- `EventsAccessTest` (por HTTP, Guzzle), três novos: JSON com busca
  e totais (200); `sort=x` com `Accept: application/json` dá 422,
  `Content-Type` JSON e `{"errors":{"sort":"Ordenação inválida."}}`
  (com `'http_errors' => false`); o mesmo `sort=x` em HTML dá 200
  com o aviso.
- Sem Cest: não há tela nova; o HTML segue coberto pelo
  `SearchEventsCest` de N16.

## Por que assim

- Reaproveita o model de N16: o JSON lê e valida os parâmetros com
  o mesmo `listingFilters()` e consulta com o mesmo
  `listingPage()`. Nenhuma regra nova, só outra saída.
- Validar antes de consultar: o 422 sai antes de `listingPage()`.
  Responder 200 com a lista padrão esconderia o erro do cliente.
- Só o JSON responde 422: quem pede JSON é um programa e precisa do
  status para saber que errou. O HTML continua tolerante (aviso e
  padrão, N16), porque quem vê a tela lê o aviso.
- `renderJson` não define status: o `http_response_code(422)` vem
  antes dele.
- `total`, `page` e `total_pages` vêm do `Paginator`, não de
  `count($events)` (que daria só os 5 da página). `page` sai já
  ajustado pelo `Paginator` de N16.
- `queryParams` não vai para o JSON: só serve aos links do HTML. O
  cliente monta a navegação a partir de `total_pages` e guarda os
  filtros (devolvidos em `filters`).
- Uma rota e uma ação, duas saídas escolhidas pelo `Accept` (O4).
  Num projeto só-API, o servidor sempre devolveria JSON, leria a
  página por `?page=` e responderia 401 em JSON sem sessão; aqui
  sem cookie continua o 302 para `/login` do middleware.

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 178, Integration 66, Acceptance 25 (base
159 / 45 / 23; com N16 + O4 antes deste commit: 178 / 57 / 25).
PHPCS e PHPStan sem erros. Schema não mudou.

Pelo terminal (populate), de dentro da rede do compose:

```
docker compose exec web curl -s -c /tmp/c -d \
    'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    -H 'Accept: application/json' \
    'http://localhost/admin/events?q=de&sort=title&dir=desc'
docker compose exec web curl -s -i -b /tmp/c \
    -H 'Accept: application/json' \
    'http://localhost/admin/events?sort=x'
```

Saída obtida (fim do primeiro e o segundo):

```
..."page":1,"per_page":5,"total":14,"total_pages":3,
"filters":{"q":"de","kind":null,"sort":"title","dir":"desc"}}

HTTP/1.1 422
{"errors":{"sort":"Ordenação inválida."}}
```

## Relação com outros cenários

- N16 (`n16-busca-filtro-ordenacao`): base. Sem N16 pronto, faça
  antes o `Paginator` e os métodos `listingFilters`/`listingPage`
  dele.
- O4 (`o4-resposta-json`): base. Traz `acceptJson()` na `index` e a
  view `index.json.php`.
- O8 (`o8-status-http`): o mesmo cuidado com `http_response_code`
  no processo do PHPUnit.
- P15 (`p15-inscricao-json`): outra resposta JSON com status de
  erro.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois
de `s50/fvp/recriar.sh s50/fvp/4.2/exemplos/o12-busca-ordenacao-json`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/o12-busca-ordenacao-json`.

Escolha o que aplicar:

- Sem N16 nem O4 no seu repositório: aplique o range inteiro
  `5dedab5..88ed8e1` (N16 + O4 + O12, três commits, nessa ordem).
- Com N16 e O4 já aplicados: aplique só o topo, `88ed8e1`.
- Com só um deles: aplique os outros commits um a um, na ordem da
  tabela (`git cherry-pick f9d7cf3` ou `34b8b3e`, depois
  `88ed8e1`).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o12 \
    s50/fvp/4.2/exemplos/o12-busca-ordenacao-json/projeto
git fetch fvp-o12 fvp/o12-busca-ordenacao-json
# range (N16 + O4 + O12):
git cherry-pick 5dedab5..88ed8e1
# ou só o topo, se já tem N16 e O4:
# git cherry-pick 88ed8e1
git remote remove fvp-o12
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/o12-busca-ordenacao-json/solucao.bundle \
    fvp/o12-busca-ordenacao-json:fvp-o12
git cherry-pick 5dedab5..88ed8e1
# ou só o topo: git cherry-pick 88ed8e1
# opcional, limpa a ref
git branch -D fvp-o12
```

### 3. A partir do `solucao.patch` (`git am`)

O patch traz os três commits (N16, O4 e O12), em ordem.

```
git am s50/fvp/4.2/exemplos/o12-busca-ordenacao-json/solucao.patch
# já tem N16 ou O4? quando o commit dele vier vazio ou conflitar:
# git am --skip
```

Depois de aplicar: rode os testes (sem mudança de schema).

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
# ou: git am --continue
git cherry-pick --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `EventsController::index`: O4 aplicado sobre uma `index` que não
  é a de N16 (ex.: com N1 ou N8) conflita no bloco do
  `acceptJson()`. Ordem certa: `listingFilters`, o 422 do JSON,
  `listingPage`, o JSON de sucesso, o HTML.
- `index.json.php`: se o seu O4 lista outros campos, mantenha os
  seus e acrescente o bloco de `errors` no topo e `per_page`,
  `total_pages` e `filters` no fim.
- `EventsControllerTest` e `EventsAccessTest`: testes novos no
  mesmo ponto que os de O4; fique com todos (e com um só
  `http_response_code(200)` no `setUp`).

## Armadilhas

- `http_response_code(422)` depois do `renderJson`: o corpo já foi
  enviado com 200.
- O status fica no processo do PHPUnit: sem
  `http_response_code(200)` no `setUp`, o teste seguinte herda o
  422.
- No 422 a view recebe só `$errors`: o `return` logo depois de
  `$json['errors']` encerra só a view (o `renderJson` ainda faz o
  `echo`) e evita `$events` indefinido, warning que vira 500.
- `errors` vazio vira `[]` e com chaves vira objeto; no 422 sempre
  há chaves.
- `total` vem do `Paginator` como `int` (a propriedade tipada
  converte o texto de `fetchColumn()`); `assertSame(2, ...)` depois
  de `json_decode` confere isso.
- O Guzzle lança exceção em 4xx: `'http_errors' => false` na
  requisição do teste de acesso.
- `/admin/events/page/2?page=5`: vale o 2 (o parâmetro de rota
  sobrescreve o da query string). Um cliente JSON pode usar só
  `/admin/events?page=N`.
- Sem cookie, o cliente JSON recebe 302 para `/login`, não 401.
  Mudar o middleware é outro pedido.
- `acceptJson()` exige `Accept: application/json` exato (O4).
