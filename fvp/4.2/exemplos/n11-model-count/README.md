# N11. `Model::count()` no framework

Pedido, como o professor faria na hora: "Contar registros carregando
todos os objetos é desperdício. Crie `Model::count()` no framework,
com `SELECT COUNT(*)`, e use na contagem de inscrições e no painel."

Chance: média. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N11.

O N+1 da listagem não acaba aqui: continua uma consulta por linha, só
mais barata (ver "Por que assim").

Branch no clone: `fvp/n11-model-count`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `9c950be` | `feat: Add Model::count() and use it for enrollments...` |

(mensagem completa: `feat: Add Model::count() and use it for
enrollments and dashboard`)

## O que mudou

- `core/Database/ActiveRecord/Model.php`: método estático
  `count(array $conditions = []): int` (PHPDoc
  `@param array<string, mixed> $conditions`). Monta
  `SELECT COUNT(*) FROM <tabela>` e, se houver condições,
  `WHERE col = :col AND ...` (igualdade, como o `where()`), faz
  `bindValue` de cada valor e devolve `(int) fetchColumn()`.
- `core/Database/ActiveRecord/HasMany.php`: `count(): int`, que
  delega para `related::count([foreignKey => id])`. Ex.:
  `$user->events()->count()`.
- `app/Models/Event.php`: `enrollmentsCount()` passa de
  `count(Enrollment::where([...]))` (carrega todas as inscrições)
  para `Enrollment::count(['event_id' => $this->id])`.
  `availableSpots()` e `hasEnrollments()` usam esse método e ganham
  junto.
- `app/Controllers/Admin/DashboardController.php` e
  `app/views/admin/dashboard/index.phtml`: `$eventsCount =
  Event::count()` no `compact` e um badge com o total ao lado do link
  "Eventos" (`id="events_count"`). A base não tinha
  `count(Event::all())` no painel (é o que o N6 acrescenta); aqui o
  total entra já com `Event::count()`.
- Testes:
  - `tests/Unit/Core/Database/ActiveRecord/ModelCountTest.php` (novo,
    7): tabela vazia = 0; sem condições conta todos (igual a
    `count(all())`); uma e duas condições; sem resultado = 0; valor
    com aspas é ligado por `bindValue` (`x' OR '1'='1` conta 0);
    `HasMany::count()`; `enrollmentsCount()` por Evento.
  - `tests/Integration/Controllers/Admin/DashboardControllerTest.php`
    (novo, 2): painel mostra 3 com três Eventos e 0 sem Eventos.
  - `EventsControllerTest` (1):
    `test_index_shows_enrollments_over_capacity_for_each_event`
    (listagem com `2/2` e `0/7`, cada um na sua linha).
  - `AdminDashboardCest`: `accessAdminAsAdmin` confere o `0` no
    badge.

## Por que assim

- Uma consulta `COUNT(*)` no banco em vez de criar um objeto por
  linha só para contar. A listagem chama `enrollmentsCount()` por
  Evento (e `availableSpots()` de novo no show): continua uma
  consulta por Evento (N+1), mas cada uma devolve só um número.
- Assinatura no padrão do `Model`: estático, condições como no
  `where()`/`findBy()`, `prepare` + `bindValue`, PHPDoc com o tipo
  do array para o phpstan nível 6.
- O SQL fica no core/model; controller só chama `Event::count()`.
- `where()` e o `Paginator` (que tem o próprio `COUNT(*)`) não foram
  mexidos: menos conflito com outros cenários.
- `HasMany::count()` aproveita o mesmo método; `BelongsToMany` já
  tinha `count()`.

## Como verificar

```
./run db:populate
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 166, Integration 48, Acceptance 23 (base 159 /
45 / 23; nenhum Cest novo, um ajustado). PHPCS e PHPStan sem erros;
PSR-4 estrito ok. Schema não mudou.

Na tela (populate): logue como `admin@example.com` / `123456` e abra
`/admin`: o badge ao lado de "Eventos" mostra 16. A listagem
`/admin/events` mostra as mesmas vagas de antes (`3/3`, `2/80`...).

## Relação com outros cenários

- N6 (`n6-badge-lotado-contador`): mostra o total de Eventos no
  painel; com este cenário, troque `count(Event::all())` por
  `Event::count()`. O badge "Lotado" do N6 usa `availableSpots()`,
  que fica mais leve aqui.
- O3 (`o3-inscritos-no-show`): `participants()` é `BelongsToMany`,
  que já tem `count()`; `$event->participants()->count()` daria o
  mesmo que `enrollmentsCount()`. Não foi trocado (não pedido).
- N7 (`n7-painel-resumo`): também conta no painel. Ele mostra o total
  de Eventos num cartão (`#total_events`, `array_sum` do
  `countByKind()`) e cria `Enrollment::total()`. Com os dois: um total
  de Eventos só na tela (o cartão do N7 ou o badge deste), e
  `Enrollment::total()` vira `Enrollment::count()`. O N7 também cria um
  `DashboardControllerTest.php`.
- Qualquer filtro por igualdade (N1) pode usar
  `Event::count(['kind' => ...])` para um total sem paginar.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/n11-model-count
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é `s50/fvp/4.2/exemplos/n11-model-count`.
Cherry-pick cria commit novo (SHA diferente); preserva conteúdo e
mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n11 <caminho>/projeto
git fetch fvp-n11 fvp/n11-model-count
git cherry-pick 9c950be
git remote remove fvp-n11
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/n11-model-count:fvp-n11
git cherry-pick 9c950be
# opcional, limpa a ref
git branch -D fvp-n11
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

- `DashboardController.php` e `admin/dashboard/index.phtml`: N6 e N7
  mexem nos mesmos pontos. Com o N6, fique com uma variável só no
  `compact` e com `Event::count()` no lugar de `count(Event::all())`.
  Com o N7, junte as variáveis dele (`countByKind`, `totalEvents`,
  `totalEnrollments`, `upcoming`) e não mostre o total de Eventos duas
  vezes: ou o cartão `#total_events` ou o badge `#events_count`.
- `tests/Integration/Controllers/Admin/DashboardControllerTest.php`:
  N6 e N7 também criam esse arquivo ("both added"). Junte os métodos
  numa classe só e confira as contagens esperadas de cada teste.
- `AdminDashboardCest.php`: N7 também mexe em `accessAdminAsAdmin`
  (confere os zeros dos cartões); fique com as duas checagens.
- `Event.php`, `enrollmentsCount()`: se outro cenário mudou o
  método, mantenha a mudança dele e troque só o
  `count(Enrollment::where(...))` por `Enrollment::count(...)`.
- `Model.php`/`HasMany.php`: outro cenário que mexe no core (raro);
  os métodos novos são independentes, fique com os dois.
- `EventsControllerTest.php`: testes novos no mesmo ponto; mantenha
  os dois.

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe de teste nova), `./run phpcs`, `./run phpstan` e os testes.

## Armadilhas

- O core É analisado pelo phpstan nível 6 (só `lib/` fica de fora):
  sem `@param array<string, mixed> $conditions` e sem o tipo de
  retorno `int`, o `./run phpstan` falha.
- `fetchColumn()` devolve string (`"3"`) no PDO/MySQL: converta com
  `(int)`, senão `assertSame(3, ...)` falha e o tipo `int` do
  retorno quebra em modo estrito.
- As chaves das condições viram nome de coluna no SQL (interpoladas,
  como no `where()`): só valores são ligados por `bindValue`. Nunca
  passe chave vinda do usuário.
- `count` sem condições não pode gerar `WHERE ` vazio: o `where()`
  do framework tem esse defeito com array vazio; aqui o `WHERE` só
  entra se houver condições.
- Não confunda com a função `count()` do PHP: dentro do `Model`,
  `count($array)` continua sendo a função nativa; o método é
  `static::count()`/`Event::count()`.
- Pasta nova de teste (`tests/Unit/Core/Database/ActiveRecord`):
  namespace igual ao caminho, senão o `--strict-psr` reclama.
