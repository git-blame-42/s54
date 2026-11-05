# N4. Mudar `per_page` ou deixar o usuário escolher

Pedido, como o professor faria na hora: "Mostre 10 Eventos por página.
Depois, deixe o usuário escolher 5, 10 ou 20 por página, sem perder a
escolha ao trocar de página."

Chance: alta. Tempo estimado: 5-10 min (mais 10 com a escolha pelo
usuário). Catálogo: `s50/fvp/4.2/catalogo.md`, N4 (seção M6 do
preparo da 4.2).

Branch no clone: `fvp/n4-per-page`, a partir de `main` (`f31006b` do público; no
bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                    |
|-----------|-------------------------------------------------------------|
| `2fa21bc` | `feat: Show 10 events per page and allow choosing per_page` |

## O que mudou

- `app/Controllers/Admin/EventsController.php`:
  - `PER_PAGE = 10` e `PER_PAGE_OPTIONS = [5, 10, 20]`.
  - Método privado `perPage(Request $request): int`: só aceita
    `?per_page=` escalar, só com dígitos e que esteja na lista
    fechada; qualquer outra coisa (`0`, `7`, `-5`, `abc`, array)
    vira o padrão 10.
  - `index` passa `per_page: $perPage` e
    `queryParams: ['per_page' => $perPage]` (só quando difere do
    padrão, para os links continuarem `/admin/events/page/2`).
  - `$perPageOptions` entra no `compact` (a view usa).
- `lib/Paginator.php`: parâmetro novo no fim do construtor,
  `private array $queryParams = []` (PHPDoc `array<string, mixed>`),
  e o getter `queryParams()`.
- `core/Database/ActiveRecord/Model.php`: `paginate()` ganha
  `array $queryParams = []` (PHPDoc `array<string, mixed>`) e
  repassa ao `Paginator` com `queryParams: $queryParams`.
- `app/views/paginator/_pages.phtml`: os três links usam
  `array_merge(['page' => X], $paginator->queryParams())`. O
  `route()` põe o que não está na URI como query string:
  `/admin/events/page/2?per_page=5`.
- `app/views/admin/events/index.phtml`: bloco "Por página:" (classe
  `per-page`) com um link por opção, `active` na atual.
- Testes:
  - `EventsControllerTest`: o antigo `..._five_events_per_page`
    virou `test_index_lists_ten_events_per_page` (12 Eventos
    `Evento %02d`, página 1 com 01 a 10, página 2 com 11 e 12);
    novos `test_index_uses_per_page_param_and_keeps_it_in_page_links`
    e `test_index_ignores_invalid_per_page` (data provider com 5
    valores inválidos).
  - `EventTest`: `test_paginate_should_keep_the_given_query_params`.
  - Aceitação: `BaseEventsCest::PER_PAGE = 10`; `ListEventsCest`
    usa `PER_PAGE + 1` no `dontSee`; `CreateSuccessfullyCest` procura
    o Evento novo na página 2 ("Mostrando 11 - 17 de 17");
    `PaginateEventsCest` reescrito: 2 páginas (4 itens na paginação),
    acesso direto à página 2, escolha de 5 (4 páginas, links com
    `?per_page=5`) e de 20, e `?per_page=0` caindo no padrão.

## Por que assim

- Lista fechada: `per_page` 0 dá divisão por zero no `Paginator`
  (`ceil($total / $this->per_page)`), e um valor enorme devolve a
  tabela inteira. Nunca use o valor cru do usuário.
- `is_scalar` + `ctype_digit` antes do cast: `?per_page[]=5` chega
  como array, e `(int) 'abc'` daria 0. O catálogo sugere só
  `(int) getParam(...)` + `in_array(..., true)`, que também é seguro
  (0 cai fora da lista); a checagem extra só deixa explícito que
  texto e array são recusados.
- O `_pages.phtml` é incluído dentro de
  `Paginator::renderPagesNavigation()`; lá só existem `$paginator` e
  `$this`. Por isso o `per_page` viaja dentro do `Paginator`
  (`queryParams()`), e não como variável do controller.
- `queryParams` genérico (não um `perPage` fixo no link): é o mesmo
  mecanismo que o filtro por tipo (N1) usa para manter `?kind=`.
- Sem `per_page` no link quando é o padrão: as URLs antigas
  (`/admin/events/page/2`) continuam iguais e os Cests de navegação
  não mudam de URL.

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

Resultado obtido: Unit 160, Integration 51, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela: `/admin/events` mostra "Mostrando 1 - 10 de 16" e duas
páginas; clique em "5" em "Por página:" e a "Próxima" leva a
`/admin/events/page/2?per_page=5`. `/admin/events?per_page=0` mostra
a listagem normal de 10.

## Relação com outros cenários

- N1 (`n1-filtro-por-tipo-paginacao`) usa o mesmo `queryParams` no
  `Paginator` (mais `conditions` no `Model::paginate`). As duas
  branches saem de `main` e cada uma tem a sua cópia do mecanismo.
  Se aplicar as duas, o conflito é no `Paginator`, no `paginate()` e
  no `index`: fique com uma só propriedade `queryParams` e junte
  `['kind' => ...]` e `['per_page' => ...]` no mesmo array.
- N2 (busca) e N5 (futuros/passados) desligam a paginação nos seus
  modos; não usam `per_page`.
- S1 (teste de aceitação) parte do `BaseEventsCest`: com este
  cenário aplicado, `PER_PAGE` vale 10 e os Cests novos precisam
  contar com 2 páginas, não 4.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n4-per-page`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n4-per-page`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n4 s50/fvp/4.2/exemplos/n4-per-page/projeto
git fetch fvp-n4 fvp/n4-per-page
git cherry-pick 2fa21bc
git remote remove fvp-n4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/n4-per-page/solucao.bundle \
    fvp/n4-per-page:fvp-n4
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 2fa21bc
# opcional, limpa a ref
git branch -D fvp-n4
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n4-per-page/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/n4-per-page/solucao.patch
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

- `lib/Paginator.php` e `Model::paginate`: filtro (N1) e ordenação
  (N3) também acrescentam parâmetros. Fique com todos; `queryParams`
  aparece uma vez só.
- `EventsController::index`: junte os argumentos nomeados na mesma
  chamada de `Event::paginate(...)`.
- `_pages.phtml`: se o outro lado já tem o `array_merge`, fique com
  ele (é o mesmo código).
- `PaginateEventsCest`, `ListEventsCest`, `CreateSuccessfullyCest`:
  fique com a versão deste cenário se a outra ainda conta 5 por
  página; se a outra mudou a ordem (N3), ajuste a página esperada.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `(int) $request->getParam('per_page')` direto no `paginate`:
  `?per_page=0` dá `DivisionByZeroError` (500) e `?per_page=100000`
  derruba a ideia de paginar.
- Esquecer o `per_page` nos links: a página 1 mostra 5 e a página 2
  volta para 10 (o salto de faixa aparece no "Mostrando ...").
- `'per_page' => $perPage` direto no `_pages.phtml`: variável
  indefinida no partial, warning, `ErrorsHandler`, 500.
- `array` sem PHPDoc em `Model::paginate` (`core/`): o PHPStan nível
  6 acusa `missingType.iterableValue`. `lib/` não entra no PHPStan,
  mas o PHPDoc ajuda a ler.
- Testes com 10 ou mais Eventos: `'Evento 1'` casa com `'Evento 10'`
  a `'Evento 16'`. Use `sprintf('Evento %02d', $i)`.
- O `PaginateEventsCest` antigo clica nas páginas 3 e 4: com 10 por
  página elas não existem. Reescreva o cenário, não só os textos.
- O `CreateSuccessfullyCest` abre `/admin/events/page/4`: com 10 por
  página o Evento 17 fica na página 2.
- `EventTest` usa `paginate(1, 5)` explícito: não depende de
  `PER_PAGE` e não precisa mudar.
- A wiki e o vídeo da entrega dizem "5 por página"; se a mudança
  ficar, avise a equipe.
