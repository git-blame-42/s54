# N6. Dado calculado na tela (badge "Lotado", total no painel)

Pedido, como o professor faria na hora: "Na listagem e nos detalhes,
mostre 'Lotado' quando o Evento não tiver mais vagas. No painel
administrativo, mostre quantos Eventos e quantas Inscrições existem."

Chance: média. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N6 (seção M12 do preparo da 4.2).

Branch no clone: `fvp/n6-badge-lotado-contador`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                           |
|-----------|----------------------------------------------------|
| `a42e0c9` | `feat: Show full badge and admin dashboard totals` |

## O que mudou

- `app/views/admin/events/index.phtml`: a célula "Vagas" mantém
  `inscritos/capacidade` e acrescenta
  `<span class="badge text-bg-danger">Lotado</span>` quando
  `$event->availableSpots() === 0`.
- `app/views/admin/events/show.phtml`: o mesmo badge depois de
  "(N disponíveis)".
- `app/Controllers/Admin/DashboardController.php`: `index` calcula
  `$total = count(Event::all())` e
  `$totalEnrollments = count(Enrollment::all())` e os passa no
  `compact` junto com `$title` (`$total` é o nome usado no catálogo
  para o total de Eventos).
- `app/views/admin/dashboard/index.phtml`: dois cards Bootstrap,
  `#total_events` ("Eventos") e `#total_enrollments` ("Inscrições"),
  acima do link para a listagem.
- Testes:
  - `EventsControllerTest`:
    `test_index_shows_full_badge_only_when_no_spots_left` (Evento com
    `capacity` 2 e 2 Inscrições tem o badge; Evento com vagas não;
    um único badge na página) e `test_show_full_event_has_badge`.
  - `tests/Integration/Controllers/Admin/DashboardControllerTest.php`
    (novo): totais zerados sem registros; 3 Eventos e 3 Inscrições.
  - `ListEventsCest`: além de "3/3", confere "Lotado" só na linha do
    Evento 01.
  - `AdminDashboardCest`: totais 0 no cenário sem dados.
  - `tests/Acceptance/admin/events/DashboardTotalsCest.php` (novo):
    painel com 16 Eventos e 4 Inscrições (dados do `BaseEventsCest`)
    e badge no show do Evento lotado.

## Por que assim

- `availableSpots()` já existe no model (capacidade menos Inscrições,
  nunca negativo). A view só pergunta ao model; a regra de "lotado"
  não é reescrita na view nem gravada no banco.
- O formato `inscritos/capacidade` continua igual: testes antigos
  (`1/10`, `3/3`, `0/22`) conferem essa string.
- Os totais são calculados no controller e entregues à view pelo
  `compact`; a view não consulta o banco. `count(Model::all())` usa só
  o que o framework já oferece (não há `Model::count()`); para 16
  Eventos é aceitável. Com muitos registros, o certo seria um
  `SELECT COUNT(*)` num método estático do model.
- `id` nos cards: o teste confere o número certo no card certo, sem
  depender de "16" aparecer em outro lugar da página.

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

Resultado obtido: Unit 159, Integration 49, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

No navegador (populate): `/admin/events` mostra "3/3 Lotado" na
linha do primeiro Evento; `/admin` mostra Eventos 16 e Inscrições 14.

## Relação com outros cenários

- N3 (`n3-ordenar-por-data`): mexe no controller e no `Paginator`,
  não na view; combina sem conflito.
- R2 (`r2-nome-do-criador`) e R3 (`r3-helper-formatacao`) também
  mexem em `index.phtml` e `show.phtml`: conflito de texto na
  tabela, fácil de juntar (cada um mexe numa célula diferente).
- R3 usa badge por tipo (`Event::KIND_BADGES`); o "Lotado" é outro
  badge, na coluna "Vagas".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n6-badge-lotado-contador`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n6-badge-lotado-contador`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n6 \
    s50/fvp/4.2/exemplos/n6-badge-lotado-contador/projeto
git fetch fvp-n6 fvp/n6-badge-lotado-contador
git cherry-pick a42e0c9
git remote remove fvp-n6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n6-badge-lotado-contador/solucao.bundle \
    fvp/n6-badge-lotado-contador:fvp-n6
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick a42e0c9
# opcional, limpa a ref
git branch -D fvp-n6
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n6-badge-lotado-contador/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n6-badge-lotado-contador/solucao.patch
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

- `app/views/admin/events/index.phtml` e `show.phtml`: outros
  cenários mudam colunas da tabela. Fique com as duas mudanças; a
  célula "Vagas" deste cenário só ganha o `if` do badge.
- `ListEventsCest`: asserções novas no mesmo trecho; mantenha todas.
- `DashboardController`/`dashboard/index.phtml`: se outro cenário
  também passou variáveis ao painel, junte tudo no mesmo `compact`.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Variável fora do `compact` (`$total` esquecido): warning na
  view, que o `ErrorsHandler` vira página 500.
- Mudar `1/10` para "1 de 10" ou trocar o número pelo badge quebra
  testes antigos que conferem a string. Acrescente, não substitua.
- `enrollmentsCount()` (usado por `availableSpots()`) faz uma
  consulta por linha: N+1 na listagem. Aceitável com 5 por página;
  saiba dizer.
- Comparar com `<= 0` ou `=== 0`: `availableSpots()` já devolve
  `max(0, ...)`, então `=== 0` basta. Não use `capacity ==
  enrollmentsCount()` direto na view (regra duplicada).
- O Evento lotado do populate é o primeiro (3/3): aparece na página 1.
