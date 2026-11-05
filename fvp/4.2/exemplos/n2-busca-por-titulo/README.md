# N2. Busca por título (`LIKE`)

Pedido, como o professor faria na hora: "Coloque um campo de busca na
listagem de Eventos que encontre os Eventos pelo título, mesmo
digitando só uma parte."

Chance: média. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N2.

Branch no clone: `fvp/n2-busca-por-titulo`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                       |
|-----------|--------------------------------|
| `8e556de` | `feat: Search events by title` |

## O que mudou

- `app/Models/Event.php`: método novo, depois de `hasEnrollments()`,
  `public static function searchByTitle(string $term): array` com
  PHPDoc `@return array<static>`. SQL
  `SELECT id, <colunas> FROM events WHERE title LIKE :term`, com
  `prepare` + `bindValue(':term', "%{$term}%")` e
  `array_map(fn($row) => new static($row), ...)`. Imports novos:
  `Core\Database\Database` e `PDO`.
- `app/Controllers/Admin/EventsController.php` (`index`):
  `$q = $request->getParam('q'); $q = is_string($q) ? trim($q) : '';`.
  Com `$q !== ''`, `$events = Event::searchByTitle($q)` e
  `$paginator = null`; senão, o caminho de antes. `$q` está sempre no
  `compact` (também quando vazio).
- `app/views/admin/events/index.phtml`:
  - `<form method="get">` para `route('admin.events.index')` com
    `<input type="search" id="search_q" name="q"
    value="<?= htmlspecialchars($q) ?>">` e botão "Buscar".
  - Sem resultados com busca: `Nenhum evento encontrado para
    "<termo>".` (termo com `htmlspecialchars`).
  - `renderPagesNavigation()` só se `$paginator !== null`.
- Testes:
  - `EventTest`: `test_search_by_title_should_match_part_of_the_title`
    (2 Eventos; `git` casa só um, sem diferenciar maiúsculas; `de`
    casa os dois) e `test_search_by_title_should_not_inject_sql`.
  - `EventsControllerTest`:
    - `test_index_searches_by_title_without_pagination`: 7 Eventos
      "Oficina de Git NN" + 1 Palestra; `q = ' git '` traz os 7, sem
      a Palestra e sem `.pagination`; o campo volta com `value="git"`.
    - `test_index_search_without_results_shows_message_and_escapes_term`
      (`q = '<b>x</b>'` aparece escapado).
    - `test_index_with_empty_or_invalid_search_lists_paginated`
      (`''`, `'   '` e array: listagem paginada normal).
  - Aceitação: `tests/Acceptance/admin/events/SearchEventsCest.php`
    (`extends BaseEventsCest`): busca "Evento 1" (casa Evento 10 a
    16, sem paginação), busca sem resultado e busca vazia.

## Por que assim

- SQL só no model, com `prepare` + `bindValue`: o termo nunca é
  concatenado no SQL (o teste com `' OR '1'='1` prova).
- `Model::where` só faz igualdade; `LIKE` pede método próprio.
- Sem paginação na busca (como a forma 1 do N1): o `Paginator` só
  sabe `WHERE coluna = valor`. Paginar a busca pediria um `LIKE` no
  `Paginator` e o `q` nos links (mecanismo `queryParams` do N1/N4).
- `is_string` antes do `trim`: `?q[]=x` chega como array e o `trim`
  daria `TypeError`; aqui vira busca vazia.
- `$q` sempre no `compact`: a view usa `$q` no `value` do campo, e
  variável indefinida vira 500 pelo `ErrorsHandler`.
- `htmlspecialchars($q)` no `value` e na mensagem: o termo vem do
  usuário (XSS).
- A comparação do MySQL (collation `utf8mb4_*_ci`) já ignora
  maiúsculas: `git` acha "Git".

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

Resultado obtido: Unit 161, Integration 48, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela: digite "Oficina" no campo e clique em "Buscar"; a URL fica
`/admin/events?q=Oficina`, só Oficinas aparecem e a paginação some.
Busca vazia mostra a listagem normal com paginação.

## Relação com outros cenários

- N1 (`n1-filtro-por-tipo-paginacao`) filtra com igualdade e
  mantém a paginação; aqui é `LIKE` sem paginação. Juntos, os dois
  formulários ficam no topo da `index.phtml`; decida se o filtro
  vale junto com a busca (exigiria `kind` no SQL de busca).
- N5 (`n5-eventos-futuros-passados`) usa o mesmo molde: métodos com
  SQL no model e `$paginator = null` na view.
- S1 (`s1-teste-aceitacao-filtro`) ensina a escrever o Cest; o
  `SearchEventsCest` daqui segue o mesmo roteiro.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n2-busca-por-titulo`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n2-busca-por-titulo`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n2 \
    s50/fvp/4.2/exemplos/n2-busca-por-titulo/projeto
git fetch fvp-n2 fvp/n2-busca-por-titulo
git cherry-pick 8e556de
git remote remove fvp-n2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n2-busca-por-titulo/solucao.bundle \
    fvp/n2-busca-por-titulo:fvp-n2
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 8e556de
# opcional, limpa a ref
git branch -D fvp-n2
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n2-busca-por-titulo/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n2-busca-por-titulo/solucao.patch
```

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

- `EventsController::index`: filtro (N1), per_page (N4), ordenação
  (N3) e abas (N5) mexem na mesma chamada de `Event::paginate`.
  Mantenha o `if ($q !== '')` por fora e a chamada combinada no
  `else`; junte as variáveis no `compact`.
- `index.phtml`: outro formulário ou links no topo, e o
  `renderPagesNavigation()` no fim. Fique com os dois blocos e com
  o `if ($paginator !== null)`.
- `Event.php`: outros métodos depois de `hasEnrollments()` (N5) e
  os `use` no topo; fique com todos, sem repetir `use PDO`.
- Testes: métodos novos no mesmo ponto; fique com os dois lados.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `$q` fora do `compact`: variável indefinida na view, 500.
- `searchByTitle` sem `@return array<static>`: PHPStan nível 6.
- `"... LIKE '%{$term}%'"` concatenado: SQL injection. Sempre
  `prepare` + `bindValue` com os `%` no valor, não no SQL.
- Ecoar `$q` sem `htmlspecialchars`: XSS no `value` do campo.
- `%` e `_` digitados viram curinga do `LIKE` (buscar `_` acha
  tudo). É aceitável; saiba explicar ou escape com `addcslashes`.
- Chamar `$paginator->renderPagesNavigation()` com `$paginator`
  nulo: 500 na busca.
- `(string) $request->getParam('q')` com `?q[]=x`: warning "Array to
  string conversion", que o `ErrorsHandler` transforma em 500.
- Teste com "Evento 1": casa "Evento 10" a "Evento 16" (é o que o
  Cest usa de propósito).
