# N10. Página e id inválidos sem `(int)` silencioso

Pedido, como o professor faria na hora: "`/admin/events/page/abc`
mostra a página 1 e `/admin/events/page/999` mostra uma tabela vazia;
`/admin/events/1abc` abre o Evento 1. Trate página e id inválidos
sem converter com `(int)` às cegas."

Chance: média-alta. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N10.

Branch no clone: `fvp/n10-pagina-id-invalido`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                    |
|-----------|-------------------------------------------------------------|
| `c4d5d93` | `fix: Validate page and id params without silent int casts` |

## O que mudou

- `lib/Paginator.php`:
  - Método novo `isOutOfRange(): bool`: `true` se a página for
    menor que 1 ou maior que `max(1, totalOfPages())`. Com a tabela
    vazia, só a página 1 é válida (a view mostra "Nenhum evento
    cadastrado.").
  - O construtor só chama `loadRegisters()` se a página estiver no
    intervalo: não consulta à toa e não calcula `OFFSET` gigante.
- `app/Controllers/Admin/EventsController.php`:
  - Método privado `positiveInt(mixed $value): ?int`: aceita string
    só de dígitos (`ctype_digit`), sem zero à esquerda e sem estouro
    (`$value === (string) (int) $value`), e `>= 1`. Aceita `int`
    também, porque os testes de controller passam `['page' => 2]`.
  - `index`:
    - sem `page` -> página 1 (como antes);
    - `page` inválida (`abc`, `0`, `-1`, `2abc`, `02`, `1.5`,
      `99999999999999999999`, array) -> flash "Página inválida!" e
      redirect para `/admin/events`;
    - página depois da última -> flash "Página inexistente!
      Mostrando a última página." e redirect para
      `/admin/events/page/<última>` (`/page/1` com a tabela vazia).
  - `findEvent`: o id passa por `positiveInt`; inválido devolve
    `null` e as quatro ações (`show`, `edit`, `update`, `destroy`)
    caem no "Evento não encontrado!" de sempre.
- Testes:
  - `tests/Unit/Lib/PaginatorTest.php` (novo, 4 testes, com `User`
    para não depender de Eventos): páginas existentes, depois da
    última (sem registros carregados), abaixo de 1, tabela vazia.
  - `EventsControllerTest`: `page` `'2'` como string válida; 8
    páginas inválidas (data provider) -> `Location: /admin/events`;
    `page=999` com 7 Eventos -> `/admin/events/page/2`; `page=2`
    sem Eventos -> `/page/1`; 4 ids inválidos (`abc`, `1abc`, `01`,
    `-1`) no `show`; `destroy` com `1abc` não remove o Evento.
  - `EventsAccessTest` (HTTP real, parâmetros como string):
    `/page/abc`, `/page/999` e `/admin/events/<id>abc` dão 302.
  - `PaginateEventsCest::openNonexistentPage`: `/page/9` vai para a
    página 4 com o aviso; `/page/abc` vai para a 1 com "Página
    inválida!".

## Por que assim

- `(int) 'abc'` é 0 e `(int) '1abc'` é 1: o `max(1, (int) ...)`
  escondia o erro (mostrava a página 1) e o `findById((int) ...)`
  abria ou removia o Evento errado. `ctype_digit` recusa tudo que
  não for só dígitos.
- Redirect + flash, e não 404: o framework não tem página 404
  própria para essa rota, o usuário volta a uma listagem útil, e o
  teste confere `Location` (padrão dos outros testes do controller,
  como o "Evento não encontrado!").
- Página depois da última vai para a última (não para a 1): é o que
  acontece depois de remover o único Evento da última página.
- `isOutOfRange()` no `Paginator`: quem sabe quantas páginas existem
  é ele (`totalOfPages()`), e o controller continua fino.
- Zero à esquerda (`02`) recusado de propósito: a URL canônica é
  uma só, e a mesma regra recusa o estouro de `PHP_INT_MAX`.

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

Resultado obtido: Unit 163, Integration 62, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

No terminal, com o populate (16 Eventos, 4 páginas) e logado como
admin (cookie em `/tmp/c`, ver o login por `curl` do projeto):

```
# 302 para /admin/events/page/4
curl -s -o /dev/null -w '%{redirect_url}\n' -b /tmp/c \
    http://localhost/admin/events/page/9
# 302 para /admin/events
curl -s -o /dev/null -w '%{redirect_url}\n' -b /tmp/c \
    http://localhost/admin/events/page/0
# 302 para /admin/events ("Evento não encontrado!")
curl -s -o /dev/null -w '%{redirect_url}\n' -b /tmp/c \
    http://localhost/admin/events/1abc
```

## Relação com outros cenários

- N4 (`n4-per-page`): muda o número de páginas. Com 10 por página,
  a última página do populate é a 2, e o `isOutOfRange()` acompanha
  sozinho. Se aplicar os dois, junte `positiveInt` (página) e
  `perPage` (lista fechada) no `index`; o redirect para a última
  página deve levar o `per_page` junto
  (`array_merge(['page' => ...], $paginator->queryParams())`), e o
  `openNonexistentPage` deste cenário passa a esperar
  `/admin/events/page/2`.
- N1 (`n1-filtro-por-tipo-paginacao`): com filtro, a última página
  é a do filtro; leve o `kind` no redirect do mesmo jeito.
- N2 e N5 desligam a paginação nos seus modos: o redirect só vale
  no caminho paginado.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n10-pagina-id-invalido`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n10-pagina-id-invalido`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n10 \
    s50/fvp/4.2/exemplos/n10-pagina-id-invalido/projeto
git fetch fvp-n10 fvp/n10-pagina-id-invalido
git cherry-pick c4d5d93
git remote remove fvp-n10
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n10-pagina-id-invalido/solucao.bundle \
    fvp/n10-pagina-id-invalido:fvp-n10
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick c4d5d93
# opcional, limpa a ref
git branch -D fvp-n10
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n10-pagina-id-invalido/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n10-pagina-id-invalido/solucao.patch
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

- `EventsController::index`: N1, N2, N3, N4 e N5 mexem na chamada
  de `Event::paginate`. Mantenha a validação da página antes da
  chamada e o `if ($paginator->isOutOfRange())` logo depois dela,
  com a chamada combinada no meio.
- `EventsController::findEvent`: outros cenários (ex.: só o dono
  edita) também mexem; mantenha o `positiveInt` antes do
  `findById`.
- `lib/Paginator.php`: N1/N4 acrescentam `queryParams`, N3
  `orderBy`; fique com todos e com o `if` no construtor.
- `PaginateEventsCest`: N4 reescreve o arquivo inteiro; leve o
  `openNonexistentPage` para a versão do N4 trocando 4 por 2 e
  ajustando a chamada de `seeEventsOfPage`.
- Testes: métodos novos no mesmo ponto e um só
  `use ...DataProvider`.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `is_numeric` no lugar de `ctype_digit`: aceita `1.5`, `1e3`,
  `-1` e ` 1`.
- `ctype_digit` com `int`: no PHP 8.1+ é obsoleto e trata o número
  como código ASCII (`ctype_digit(2)` é `false`). Por isso o
  `is_int` separado.
- `ctype_digit('')` é `false` (ok), mas `getParam('page')` ausente
  é `null`: trate "sem página" (página 1) antes de validar.
- Esquecer o `return` depois do `redirectTo()`: no teste de
  controller o redirect só imprime `Location:` e o método segue
  renderizando.
- `isOutOfRange()` com `totalOfPages()` 0 (tabela vazia): sem o
  `max(1, ...)`, a página 1 vira "fora do intervalo" e o redirect
  entra em laço para `/page/0`.
- Redirect para a última página sem os parâmetros extras (com N1 ou
  N4 aplicados): o filtro ou o `per_page` se perde.
- `FlashMessage` sobrevive entre testes: o `setUp` do
  `EventsControllerTest` já limpa `$_SESSION['flash']`.
