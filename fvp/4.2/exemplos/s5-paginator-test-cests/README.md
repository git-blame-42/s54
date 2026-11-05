# S5. Testes que faltam: `PaginatorTest` e Cests do `show`

Pedido, como o professor faria na hora: "O `Paginator` é item da
rubrica e não tem teste próprio; escreva. E não há Cest do detalhe
do Evento nem do 'não encontrado'."

Chance: média-baixa. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, S5.

Branch no clone: `fvp/s5-paginator-test-cests`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz). Só testes:
nenhum arquivo de `app/`, `core/`, `lib/`, `config/` ou do schema mudou.

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `abb5d77` | `test: Add Paginator unit tests and event show accept...` |

(mensagem completa: `test: Add Paginator unit tests and event show
acceptance tests`)

## O que mudou

- `tests/Unit/Lib/PaginatorTest.php` (novo, 5 testes, `extends
  Tests\TestCase`): cria os Eventos no banco e monta o `Paginator`
  direto, sem passar pelo `Event::paginate()`:

  ```php
  new Paginator(
      Event::class, $page, 5, 'events', Event::columns(),
      $conditions, 'admin.events.paginate'
  );
  ```

  - última página (7 Eventos, página 2): `totalOfRegisters() === 7`,
    `totalOfPages() === 2`, 2 registros (`Evento 6` e `Evento 7`,
    instâncias de `Event`), `totalOfRegistersOfPage() === 2`,
    `entriesInfo() === 'Mostrando 6 - 7 de 7'`, `hasPreviousPage()`
    verdadeiro e `hasNextPage()` falso;
  - primeira página: `'Mostrando 1 - 5 de 7'` (o `EventTest` não
    confere o `entriesInfo()`);
  - página depois da última (página 3): `registers()` vazio e
    `entriesInfo() === 'Mostrando 11 - 10 de 7'`. É o comportamento
    ATUAL, um defeito conhecido (início maior que o fim e que o
    total); o docblock do teste avisa que o N10 trata isso;
  - `conditions: ['kind' => 'lecture']` com 3 Palestras e 4 Oficinas:
    total 3, 1 página, só Palestras, `'Mostrando 1 - 3 de 3'`;
  - tabela vazia: total 0, `totalOfPages() === 0`, sem registros, sem
    página anterior nem próxima.
- `tests/Acceptance/admin/events/ShowEventCest.php` (novo, 2 Cests,
  `extends BaseEventsCest`):
  - `showEvent`: abre `/admin/events/<id do Evento 01>` direto pela
    URL; título no `h1`, descrição, tipo "Palestra", local "Sala 1",
    data `d/m/Y`, vagas `3/3` e "0 disponíveis" (o Evento 01 da base
    está lotado), links "Editar" (`/admin/events/<id>/edit`) e
    "Voltar" (`/admin/events`);
  - `showEventNotFound`: `/admin/events/999999` volta para
    `/admin/events` com "Evento não encontrado!" e o `h1` "Eventos".

## Por que assim

- O `Paginator` consulta o banco no construtor (`loadTotals()` e
  `loadRegisters()`): o teste estende `Tests\TestCase`, que recria o
  schema a cada teste. PHPUnit puro não serve.
- Testa a classe, não o model: o `EventTest` já cobre o
  `Event::paginate()` (12 Eventos em 3 páginas, navegação, nome da
  rota). Aqui entram o que ele não confere: `entriesInfo()`, página
  depois da última, `conditions` (o `paginate()` do model não repassa
  condições) e tabela vazia.
- Página 3 com "Mostrando 11 - 10 de 7": o pedido é de testes, não de
  correção. O teste fixa o comportamento atual e diz isso; quem
  corrigir (N10) troca a asserção.
- `renderPagesNavigation()` não é chamada: ela usa `route()`, que
  lança exceção se as rotas não estiverem carregadas (o
  `Tests\TestCase` não carrega `config/routes.php`).
- `assertSame` em vez de `assertEquals`: confere também o tipo (o
  `COUNT(*)` volta como string do PDO e vira `int` na propriedade).
- Cest com URL direta: o `ListEventsCest` já chega ao `show` clicando
  em "Ver"; aqui o detalhe é aberto sozinho e o "não encontrado" ganha
  cobertura de navegador (no Integration já existia, pelo
  `EventsControllerTest`).

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

Só os testes novos:

```
./run test tests/Unit/Lib/PaginatorTest.php
./run test:browser tests/Acceptance/admin/events/ShowEventCest.php
```

Resultado obtido: Unit 164, Integration 45, Acceptance 25 (base 159 /
45 / 23; 5 testes e 2 Cests novos). PHPCS e PHPStan sem erros. Schema
não mudou.

Para ver que os testes testam algo: em `lib/Paginator.php`, no
`entriesInfo()`, troque `$this->offset + 1` por `$this->offset` e rode
`./run test tests/Unit/Lib/PaginatorTest.php`. Resultado obtido: 4 de
5 falham (`'Mostrando 6 - 7 de 7'` vira `'Mostrando 5 - 6 de 7'`, e
assim por diante); só o da tabela vazia passa, porque não chama
`entriesInfo()`. Desfaça com `git checkout lib/`.

## Relação com outros cenários

- N10 (`n10-pagina-id-invalido`): não carrega registros fora do
  intervalo. Com ele, o `offset` da página 3 fica 0 e o
  `entriesInfo()` vira "Mostrando 1 - 0 de 7": troque a asserção de
  `test_page_after_the_last_should_be_empty_with_incoherent_...` (ou
  confira `isOutOfRange()`, que o N10 cria). O N10 também cria um
  `PaginatorTest.php` (com `User`).
- N8 (`n8-ordenar-pelo-cabecalho`) e N12
  (`n12-paginacao-primeira-ultima`): também criam
  `tests/Unit/Lib/PaginatorTest.php`; junte os testes num arquivo só.
- N1, N3, N4, N9, N14, N15, O6, S1: acrescentam parâmetros ao
  construtor do `Paginator`, sempre depois de `route`. A chamada
  posicional deste teste (7 argumentos) continua valendo.
- Cenários que mexem no `show` (O3, R6, R11, M21...): o
  `ShowEventCest` é o lugar natural para as checagens novas da tela.
  M21 (vagas ilimitadas) pode mudar o texto das vagas; confira o
  `3/3`.
- S3/S4: mesma linha (só testes; documentar o comportamento atual).

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/s5-paginator-test-cests`.

### 1. A partir do clone `projeto/` (remote temporário)

No s50, `projeto/` vem sem o `.git` interno. Antes, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/s5-paginator-test-cests
```

Depois:

```
cd /caminho/do/seu/repositorio
git remote add fvp-s5 <caminho>/projeto
git fetch fvp-s5 fvp/s5-paginator-test-cests
git cherry-pick abb5d77
git remote remove fvp-s5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/s5-paginator-test-cests:fvp-s5
git cherry-pick abb5d77
# opcional, limpa a ref
git branch -D fvp-s5
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
# arquivos marcados "both added" ou "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue
# ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `tests/Unit/Lib/PaginatorTest.php`: N8, N10 e N12 também criam
  ("both added"). Junte os métodos numa classe só. Os deles usam
  `User::paginate()` ou `Event::paginate()`; os deste usam
  `new Paginator(...)` e precisam do `setUp` que cria o admin e do
  helper `createEvent()`. Um `use` de cada classe, sem repetir.
- Com o N10 aplicado, ajuste a asserção da página 3 (ver "Relação com
  outros cenários").
- `ShowEventCest.php`: nenhum exemplo cria; se você já tiver um, junte
  os métodos.

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe de teste nova), `./run phpcs`, `./run phpstan` e os testes.

## Armadilhas

- `Tests\TestCase`, não `PHPUnit\Framework\TestCase`: sem o schema, o
  construtor do `Paginator` estoura no `COUNT(*)`.
- Os 5 primeiros argumentos do construtor não têm valor padrão
  (`class`, `page`, `per_page`, `table`, `attributes`). Use
  `Event::columns()` para `attributes`; o `Paginator` já acrescenta o
  `id` no `SELECT`.
- `Event` exige `user_id` válido (chave estrangeira): o `setUp` cria
  um admin antes dos Eventos.
- `renderPagesNavigation()` em teste unitário: sem rota com o nome
  (`events.paginate` por padrão) o `Router` lança exceção. Passe
  `route:` e carregue as rotas, ou não renderize (escolha deste
  teste).
- `lib/` está fora do phpstan, mas `tests/` não: o helper com
  `array $conditions` precisa de `@param array<string, mixed>`.
- O `Paginator` não ordena (sem `ORDER BY`): `Evento 6` e `Evento 7`
  na página 2 dependem da ordem de inserção (id), como no resto da
  suíte.
- Página 3 com "Mostrando 11 - 10 de 7": não "corrija" o teste para
  passar com o que parece certo; ele documenta o defeito. Quem pedir
  a correção pede o N10.
- No Cest, o `h1` vem do `$title` do controller
  (`htmlspecialchars($event->title)`); títulos com `&` ou `<` aparecem
  escapados no HTML, mas o `see()` compara o texto visível.
- `/admin/events/999999` é um id válido que não existe. Ids como
  `abc` viram 0 pelo `(int)` e dão o mesmo flash na base; `1abc` abre
  o Evento 1 (defeito tratado no N10).
