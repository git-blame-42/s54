# N12. Paginação Primeira/Última + janela de 3 números

Pedido, como o professor faria na hora: "Na paginação, coloque os
botões Primeira e Última e mostre no máximo 3 números em volta da
página atual."

Chance: média. Tempo estimado: 10-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N12.

Branch no clone: `fvp/n12-paginacao-primeira-ultima`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

Commit:

- `29f6bcd` `feat: Add first/last links and a three-page window to
  pagination`

## O que mudou

- `lib/Paginator.php`, três métodos novos:
  - `firstPage(): int` (sempre 1);
  - `lastPage(): int` (`max(1, totalOfPages)`: sem registros, a
    única página é a 1);
  - `pagesWindow(int $size = 3): array` (`@return array<int>`): até
    `$size` números centrados na página atual e deslocados para
    caber entre a primeira e a última. Com 4 páginas: atual 1 →
    `[1, 2, 3]`; atual 3 → `[2, 3, 4]`; atual 4 → `[2, 3, 4]`.
    Página acima da última usa a última; `$size < 1` vira 1.
- `app/views/paginator/_pages.phtml`: closure
  `$pageUrl = fn(int $page): string => route(...)` no topo (uma
  chamada a `route()` só, linhas curtas) e a ordem Primeira,
  Anterior, janela, Próxima, Última. Na primeira página, Primeira e
  Anterior ficam `disabled`; na última, Próxima e Última.
- Testes:
  - `tests/Unit/Lib/PaginatorTest.php` (novo, 11): janela no início,
    segunda, meio, penúltima, fim e página fora (99) com 7 páginas;
    `firstPage`/`lastPage` com 16 Eventos (4 páginas); uma página;
    sem registros; janela maior que o total; tamanhos 5, 4, 1 e 0.
  - `EventsControllerTest` (1):
    `test_index_shows_first_and_last_links_and_a_window_of_three_pages`
    (21 Eventos, página 2: links Primeira/Anterior para 1,
    Próxima para 3, Última para 5, números 1-3 com o 2 ativo, sem o
    4; na página 1, Primeira desabilitada).
  - `PaginateEventsCest` ajustado: na página 1 são 7 itens (eram 6
    com 4 números), 2 desabilitados e sem link "4"; na página 3 a
    janela anda (link "4", sem "1"). Cenário novo
    `jumpToLastAndFirstPages` (Última → página 4, Primeira → 1).

## Por que assim

- A regra (que números mostrar) fica no `Paginator`, testável sem
  HTML; a view só percorre `pagesWindow()`. É o mesmo padrão de
  `hasPreviousPage()`/`hasNextPage()`.
- `lastPage()` com `max(1, ...)`: com 0 registros o
  `totalOfPages()` é 0 e a janela ficaria `range(1, 0)` = `[1, 0]`.
- `$size` parametrizado com padrão 3: o pedido muda o número sem
  mexer na lógica; tamanho par puxa a janela para a direita
  (`[3, 4, 5, 6]` na página 4 com `$size = 4`).
- Closure `$pageUrl` na view: tira a repetição de
  `route($paginator->getRouteName(), ['page' => ...])` e mantém as
  linhas abaixo de 130 colunas.
- Primeira/Última aparecem sempre (desabilitadas quando não cabem),
  como Anterior/Próxima já faziam: o número de itens fica estável.

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

Resultado obtido: Unit 170, Integration 46, Acceptance 24 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela (populate, 16 Eventos, 4 páginas): logue como admin e abra
`/admin/events/page/3`. A navegação mostra Primeira, Anterior, 2, 3,
4, Próxima, Última. Sem porta no host:

```
docker compose exec web curl -s -c /tmp/c -d \
    'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/page/3
```

## Relação com outros cenários

- N4 (`n4-per-page`): muda o `per_page`; com mais ou menos páginas a
  janela continua com 3 números. Se aplicar os dois, confira as
  contagens do `PaginateEventsCest`.
- T1 (`t1-populate-campo-eventos-admin`): mais Eventos no populate
  dão mais páginas, e a janela passa a fazer diferença na tela.
- S5 (testes que faltam, `PaginatorTest`): este cenário cria o
  `tests/Unit/Lib/PaginatorTest.php`; se o S5 também criar, junte os
  testes num arquivo só.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/n12-paginacao-primeira-ultima
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n12-paginacao-primeira-ultima`. Cherry-pick
cria commit novo (SHA diferente); preserva conteúdo e mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n12 <caminho>/projeto
git fetch fvp-n12 fvp/n12-paginacao-primeira-ultima
git cherry-pick 29f6bcd
git remote remove fvp-n12
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/n12-paginacao-primeira-ultima:fvp-n12
git cherry-pick 29f6bcd
# opcional, limpa a ref
git branch -D fvp-n12
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am <caminho>/solucao.patch
# só o diff, sem commit
git apply --3way <caminho>/solucao.patch
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

Arquivos que costumam conflitar e por quê:

- `app/views/paginator/_pages.phtml`: filtro/busca (N1, N2) que
  passam parâmetros na URL da página mexem nos mesmos `href`. Fique
  com esta estrutura e ponha os parâmetros extras dentro da closure
  `$pageUrl` (um lugar só).
- `PaginateEventsCest.php`: N4 e T1 mudam o total de páginas e de
  itens. Recalcule: 7 itens enquanto houver 3 páginas ou mais.
- `EventsControllerTest.php`: testes novos no mesmo ponto.

Depois de aplicar: `./run phpcs` e os testes; `./run test:browser`
para o Cest da paginação.

## Armadilhas

- `lib/` fica fora do phpstan, mas mantenha `@return array<int>`
  (padrão do projeto e documentação do retorno).
- Página 0 ou negativa direto no `Paginator` quebra antes da janela:
  o OFFSET fica negativo e o MySQL recusa. O controller já usa
  `max(1, (int) $request->getParam('page', 1))`; não tire.
- Sem registros, `totalOfPages()` é 0: use `lastPage()` (mínimo 1)
  para não gerar `range(1, 0)`.
- `range($start, $end)` com `$start > $end` devolve a lista ao
  contrário, não vazia: por isso o `$start` é limitado antes.
- No Cest, `click('2', '.pagination')` só funciona se o 2 estiver na
  janela; na página 4 o "1" não aparece. Use Primeira/Última para
  saltos.
- A variável do `foreach` na view chama `$page`; o
  `renderPagesNavigation()` roda num escopo próprio, então não colide
  com nada do controller.
