# N7. Painel administrativo com resumo

Pedido, como o professor faria na hora: "No painel administrativo
mostre quantos Eventos há por tipo, os próximos três Eventos e o total
de Inscrições. As contas ficam no model."

Chance: média. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N7.

Branch no clone: `fvp/n7-painel-resumo`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                          |
|-----------|---------------------------------------------------|
| `b36ba31` | `feat: Show event summary on the admin dashboard` |

## O que mudou

- `app/Models/Event.php`:
  - `countByKind(): array` (`@return array<string, int>`):
    `SELECT kind, COUNT(*) AS total FROM events GROUP BY kind`, com
    todos os tipos de `KINDS` começando em `0` (ordem de `KINDS`).
  - `upcoming(int $limit = 3): array` (`@return array<static>`):
    `WHERE starts_on >= :today ORDER BY starts_on ASC, id ASC LIMIT
    :limit`, com `:today = date('Y-m-d')` e `:limit` ligado como
    `PDO::PARAM_INT`.
- `app/Models/Enrollment.php`: `total(): int`, `SELECT COUNT(*)` com
  `fetchColumn()` e `(int)`.
- `app/Controllers/Admin/DashboardController.php`: monta
  `$countByKind`, `$totalEvents` (`array_sum` do anterior, sem outra
  consulta), `$totalEnrollments` e `$upcoming` (constante
  `UPCOMING_LIMIT = 3`); tudo no `compact` com `$title`.
- `app/views/admin/dashboard/index.phtml`: dois cartões (Eventos e
  Inscrições, ids `total_events`/`total_enrollments`), tabela "Eventos
  por tipo" (`#events_by_kind`, rótulos de `Event::KINDS`) e
  "Próximos eventos" (`#upcoming_events`: data, título com link para o
  `show` do admin e tipo) ou "Nenhum evento agendado.". O nome no "Olá"
  passou a ser escapado.
- Testes:
  - `EventTest`: `countByKind` com 2 Palestras e 1 Oficina (zeros nos
    demais) e sem Eventos; `upcoming` em ordem de data com hoje
    incluído e passado fora; limite respeitado (inclusive `0`).
  - `EnrollmentTest::test_total_should_count_every_enrollment`.
  - `tests/Integration/Controllers/Admin/DashboardControllerTest.php`
    (novo): painel com os números e os 3 próximos na ordem; painel
    vazio com zeros.
  - `AdminDashboardCest`: `accessAdminAsAdmin` confere os zeros;
    `adminDashboardShowsSummary` (novo) cria 4 Eventos e 1 Inscrição e
    confere totais, linhas da tabela por tipo, os 3 próximos e o link.

## Por que assim

- Agregação no banco, no model: um `GROUP BY` e um `COUNT(*)` em vez
  de carregar todos os registros e contar em PHP (o
  `count(Enrollment::all())` sugerido no catálogo também funciona, mas
  traz todas as linhas). O controller só junta.
- Zeros a partir de `KINDS`: o `GROUP BY` não devolve tipo sem Evento;
  sem o preenchimento, a linha some da tabela.
- `upcoming` em vez de `nextEvents` (nome do passo do catálogo): é o
  nome pedido no enunciado e o mesmo do N5, que o catálogo cita como
  base. Se o N5 já estiver aplicado com outro formato, use um só.
- `starts_on >= hoje`: Evento de hoje ainda é "próximo" (mesma regra
  de "hoje" do `notPastDate`). Desempate pelo `id` deixa a ordem
  estável.
- `total` de Eventos como `array_sum($countByKind)`: o número já está
  na consulta por tipo.
- SQL com `prepare`; o nome da tabela vem de `static::$table`, como em
  `Model::where`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
./run db:populate
```

Resultado obtido: Unit 164 testes, Integration 47, Acceptance 24
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. Sem schema nem
classe nova em `app/` (o teste novo é de `tests/`).

Testes novos:

- `EventTest`:
  - `test_count_by_kind_should_group_events_by_kind_with_zeros`
  - `test_count_by_kind_without_events_should_be_all_zeros`
  - `test_upcoming_should_return_the_next_events_by_date`
  - `test_upcoming_should_ignore_past_events_and_respect_the_limit`
- `EnrollmentTest::test_total_should_count_every_enrollment`
- `DashboardControllerTest`:
  - `test_index_shows_totals_counts_by_kind_and_next_three_events`
  - `test_index_without_events_shows_zeros`
- `AdminDashboardCest::adminDashboardShowsSummary`

No navegador, depois do `./run db:populate`, abra `/admin` como
`admin@example.com`: 16 Eventos, 14 Inscrições; por tipo Palestra 5,
Oficina 4, Minicurso 4, Semana acadêmica 3 (distribuição do populate);
próximos: os três primeiros do populate (hoje + 14, 15 e 21 dias).
Pelo terminal:

```
docker compose exec web sh -c "curl -s -c /tmp/a \
  -d 'user[email]=admin@example.com&user[password]=123456' \
  http://localhost/login >/dev/null; \
  curl -s -b /tmp/a http://localhost/admin" \
  | grep -E 'display-6|text-end'
```

## Relação com outros cenários

- N5 (`n5-eventos-futuros-passados`): também filtra por
  `starts_on >= hoje`. Se os dois forem aplicados, mantenha um método
  `upcoming` só (o deste aceita `$limit`).
- N6 (`n6-badge-lotado-contador`): contador simples na tela e um
  `DashboardControllerTest` próprio. Conflito provável no controller,
  na view do painel e no arquivo de teste: junte as variáveis no
  `compact` e os testes no mesmo arquivo.
- N11 (`n11-model-count`): também mexe no painel e conta Eventos. Ele
  põe `$eventsCount = Event::count()` no `compact` e um badge
  `#events_count` ao lado do link "Eventos", e cria o seu próprio
  `DashboardControllerTest.php`. Se os dois forem aplicados:
  - fique com um total de Eventos só: o cartão `#total_events` deste
    (`array_sum` do `countByKind`, sem consulta extra) ou o
    `Event::count()` do N11, não os dois números na mesma tela;
  - `Enrollment::total()` vira `Enrollment::count()`; troque e apague
    o `total()`;
  - junte os testes dos dois `DashboardControllerTest` num arquivo e
    confira as contagens esperadas (o painel vazio deste espera zeros;
    o do N11 espera `3` no badge com três Eventos).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n7-painel-resumo`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/n7-painel-resumo`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n7 s50/fvp/4.2/exemplos/n7-painel-resumo/projeto
git fetch fvp-n7 fvp/n7-painel-resumo
git cherry-pick b36ba31
git remote remove fvp-n7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/n7-painel-resumo/solucao.bundle \
    fvp/n7-painel-resumo:fvp-n7
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick b36ba31
# opcional, limpa a ref
git branch -D fvp-n7
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n7-painel-resumo/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/n7-painel-resumo/solucao.patch
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

- `DashboardController.php` e `admin/dashboard/index.phtml`: N6 e N11
  mexem nos mesmos. Fique com as variáveis e blocos dos dois, mas com
  um total de Eventos só (ver N11 acima).
- `tests/Integration/Controllers/Admin/DashboardControllerTest.php`:
  N6 e N11 também criam esse arquivo, então o conflito é "both added".
  Junte os métodos de teste numa classe só (um `setUp`/`tearDown`).
- `AdminDashboardCest.php`: N11 acrescenta a checagem do badge em
  `accessAdminAsAdmin`; fique com as duas checagens.
- `Event.php` (imports `Database`/`PDO` e métodos estáticos novos):
  N5 e N3 também acrescentam. Um `use` de cada; métodos lado a lado.
- `EventTest.php`, `EnrollmentTest.php`, `AdminDashboardCest.php`:
  testes novos no fim; fique com os dois lados.

Depois de aplicar: rode os testes e o `./run phpcs` (sem schema).

## Armadilhas

- `LIMIT :limit` sem `PDO::PARAM_INT` vira `'3'` com aspas e o MySQL
  recusa a consulta.
- `GROUP BY` não devolve tipos sem Evento: preencha com `0`.
- `COUNT(*)` e `fetchColumn()` devolvem string: `(int)` antes de
  comparar com `assertSame`.
- Variável fora do `compact` (ou sem `$title`): página 500.
- `AdminDashboardCest` e o teste de controller rodam com banco vazio:
  a view precisa funcionar com zero Eventos ("Nenhum evento agendado.").
- `upcoming` usa a data do PHP (`date('Y-m-d')`), não `CURDATE()` do
  MySQL: os fusos podem diferir e o Evento de hoje sumir.
- O Cest confere linhas da tabela por posição (`tr:nth-child`): a ordem
  é a de `KINDS`; mudar a ordem da constante muda o teste.
