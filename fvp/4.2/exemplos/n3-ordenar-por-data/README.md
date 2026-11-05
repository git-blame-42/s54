# N3. Ordenar a listagem por data no Paginator

Pedido, como o professor faria na hora: "A listagem de Eventos deve
vir ordenada pela data, os mais próximos primeiro. Faça a ordenação
no banco, para valer em todas as páginas."

Chance: média-alta. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N3 (seção M5 do preparo da 4.2).

Branch no clone: `fvp/n3-ordenar-por-data`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                               |
|-----------|--------------------------------------------------------|
| `77652e8` | `feat: Order events listing by date in the Paginator`  |

## O que mudou

- `lib/Paginator.php`: parâmetro novo no fim do construtor,
  `private ?string $orderBy = null`. Em `loadRegisters()` monta
  `ORDER BY {$this->orderBy}` (ou nada) entre o `WHERE` e o
  `LIMIT :limit OFFSET :offset`. O `COUNT(*)` de `loadTotals()` não
  precisa de ordem.
- `core/Database/ActiveRecord/Model.php`: `paginate()` ganha
  `?string $orderBy = null` e repassa ao `Paginator` (argumento
  nomeado `orderBy:`). Sem o argumento, o comportamento é o de antes.
- `app/Controllers/Admin/EventsController.php`: `index` chama
  `Event::paginate(..., orderBy: 'starts_on, id')`.
- `tests/Integration/Controllers/Admin/EventsControllerTest.php`:
  - `test_index_orders_events_by_date_across_pages`: 7 Eventos
    cadastrados com datas fora de ordem (+30, +5, +20, +1, +25, +10,
    +15 dias); a página 1 mostra +1, +5, +10, +15, +20 nessa ordem e a
    página 2 mostra +25, +30.
  - `test_index_breaks_date_ties_by_id`: dois Eventos na mesma data
    saem na ordem de `id`, depois de um Evento mais próximo.
  - helper `assertTitlesInOrder` (posição com `strpos`).
- `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`: o
  Evento novo (data de amanhã) agora abre a página 1 (primeira linha
  da tabela) e não aparece na página 4. Antes o Cest esperava o Evento
  novo na página 4 ("Mostrando 16 - 17 de 17").
- Comentários de `BaseEventsCest` e `ListEventsCest` que diziam "ordem
  do banco (id), sem ORDER BY" foram atualizados.

## Por que assim

- Ordenar no SQL é o único jeito certo com paginação: `LIMIT/OFFSET`
  escolhe as 5 linhas da página depois do `ORDER BY`. Um `usort` no
  controller ordenaria só as 5 linhas que já vieram, e a página 2
  mostraria Eventos mais próximos que os da página 1.
- `id` como desempate: sem ele, o MySQL pode devolver datas iguais em
  qualquer ordem e um Evento pode "pular" de página entre requisições.
  O teste antigo `test_index_lists_five_events_per_page` cria 7
  Eventos na mesma data e depende da ordem de `id`; o desempate o
  mantém verde.
- `ORDER BY` não aceita `bindValue` (é identificador, não valor). Por
  isso a string vem do código do controller, nunca da requisição. Se
  o professor pedir que o usuário escolha (`?sort=title`), use lista
  fechada:

```php
$allowed = ['date' => 'starts_on, id', 'title' => 'title, id'];
$sort = (string) $request->getParam('sort', 'date');
$orderBy = $allowed[$sort] ?? $allowed['date'];
```

- Parâmetro opcional no fim do construtor e de `paginate()`: os outros
  usos continuam funcionando sem mudança (compatível com quem chama
  por posição ou por nome).
- SQL continua só em `lib/`/model; o controller só diz a ordem.

## Como verificar

```
./run up -d db php php_test web
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 159, Integration 47, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

No navegador, o populate sozinho não mostra diferença: as datas
(+14 a +82 dias) já crescem junto com o `id`. Para ver, cadastre em
`/admin/events/new` um Evento para amanhã: ele aparece na primeira
linha da página 1. Sem a ordenação, ele iria para a página 4.

## Relação com outros cenários

- N6 (`n6-badge-lotado-contador`), R2 (`r2-nome-do-criador`) e R3
  (`r3-helper-formatacao`) mexem em `index.phtml`, não no controller:
  combinam sem conflito de lógica.
- Um filtro na listagem (M3 do preparo) também mexe no `Paginator`
  e em `Model::paginate` (condições). Se aplicar os dois, mantenha os
  dois parâmetros (`conditions:` e `orderBy:`) no `paginate()`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n3-ordenar-por-data`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n3-ordenar-por-data`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n3 \
    s50/fvp/4.2/exemplos/n3-ordenar-por-data/projeto
git fetch fvp-n3 fvp/n3-ordenar-por-data
git cherry-pick 77652e8
git remote remove fvp-n3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n3-ordenar-por-data/solucao.bundle \
    fvp/n3-ordenar-por-data:fvp-n3
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 77652e8
# opcional, limpa a ref
git branch -D fvp-n3
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n3-ordenar-por-data/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n3-ordenar-por-data/solucao.patch
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

- `lib/Paginator.php` (construtor e `loadRegisters`) e
  `Model::paginate`: um filtro ou busca também acrescenta parâmetros.
  Fique com todos os parâmetros, `orderBy` por último; no SQL a ordem
  é `WHERE ... ORDER BY ... LIMIT ...`.
- `EventsController::index`: outro cenário muda `per_page` ou passa
  condições. Junte os argumentos nomeados na mesma chamada.
- `CreateSuccessfullyCest`: se outro cenário também mudou a parte da
  listagem, fique com a versão deste (Evento novo na página 1).

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `usort` no controller: passa no teste de uma página só e falha na
  página 2. O teste novo confere as duas páginas.
- Esquecer o desempate por `id`: datas iguais mudam de página.
- Interpolar `?sort=` da URL no `ORDER BY`: SQL injection. Só lista
  fechada.
- `ORDER BY` depois do `LIMIT`: erro de sintaxe no MySQL.
- `Event::all()` também não ordena; se o pedido for sobre ele, a
  ordem vai no SQL de `Model::all` (ou num método do model).
- O Cest de cadastro esperava o Evento novo na página 4: com a
  ordenação ele sobe para a página 1. Esse é o teste que quebra.
