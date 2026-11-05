# R3. Formatação na view com helper num só lugar

Pedido, como o professor faria na hora: "A data e o tipo estão
formatados repetidos nas views. Centralize a formatação, mostre a
descrição resumida (80 caracteres) na listagem e o tipo como badge
colorido."

Chance: média. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R3.

Branch no clone: `fvp/r3-helper-formatacao`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                           |
|-----------|----------------------------------------------------|
| `035fce3` | `refactor: Move event formatting to model helpers` |

## O que mudou

- `app/Models/Event.php`:
  - constante `KIND_BADGES` ao lado de `KINDS`: cor Bootstrap de cada
    tipo (`lecture` primary, `workshop` success, `short_course`
    warning, `academic_week` info);
  - `formattedStartsOn(): string`: `dd/mm/aaaa` a partir do `Y-m-d`
    do banco (`DateTime::createFromFormat('!Y-m-d', ...)`); vazio se
    não for uma data nesse formato;
  - `excerpt(int $max = 80): string`:
    `mb_strimwidth($description, 0, $max, '...', 'UTF-8')`, com as
    reticências contando no limite;
  - `kindLabel(): string` (rótulo de `KINDS`, ou o próprio valor) e
    `kindBadgeClass(): string` (`text-bg-<cor>`, `secondary` se o tipo
    não estiver no mapa).
- `app/views/admin/events/index.phtml`: coluna nova "Descrição" com
  `excerpt()`; "Tipo" vira
  `<span class="badge <?= $event->kindBadgeClass() ?>">`; "Data" usa
  `formattedStartsOn()`. Sai o `date('d/m/Y', strtotime(...))`.
- `app/views/admin/events/show.phtml`: o mesmo badge e a mesma data;
  a descrição continua inteira (sem corte) no show.
- Testes:
  - `EventTest`: 8 testes de string (data, data inválida, descrição
    curta, longa com acento, tamanho customizado, rótulo, classe do
    badge, todo tipo de `KINDS` tem cor em `KIND_BADGES`).
  - `EventsControllerTest`: listagem com descrição cortada, badge
    `text-bg-success` "Oficina" e data; show com descrição inteira e
    badge `text-bg-warning` "Minicurso".
  - `ListEventsCest`: badge com o rótulo e a cor certa nas linhas dos
    Eventos 01-03 e descrição curta na linha.

## Por que assim

- Regra de apresentação num só lugar: antes, listagem e show
  repetiam `date('d/m/Y', strtotime(...))` e
  `Event::KINDS[$event->kind] ?? ...`. O professor já cobrou "rótulos
  num só lugar" (4.1, C8). Agora as views só chamam métodos do model,
  e o mapa de cores fica ao lado do mapa de rótulos.
- Nomes do catálogo (`formattedStartsOn`, `excerpt`). O prompt
  original chamava o corte de `shortDescription`; é o mesmo método,
  só com outro nome.
- `mb_strimwidth` com `'UTF-8'`: `substr` cortaria "ação" no meio de
  um caractere de 2 bytes e geraria texto inválido.
- O corte acontece antes do `htmlspecialchars` (o model devolve
  texto puro; a view escapa). Cortar depois de escapar quebraria
  entidades como `&amp;`.
- `createFromFormat('!Y-m-d')` em vez de `strtotime`: não inventa
  data para valor vazio ou mal formado (`strtotime('')` vira 1970).
- O formato continua `dd/mm/aaaa`, então os Cests que conferem a data
  (`date('d/m/Y', ...)` no `ListEventsCest`) não precisaram mudar.
- Data por extenso ("7 de março de 2026"), se o professor pedir: a
  extensão `intl` não está na imagem (`php -m`), e `strftime` está
  deprecada. Use um array de meses no model:

```php
private const MONTHS = [1 => 'janeiro', 'fevereiro', 'março',
    'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro',
    'outubro', 'novembro', 'dezembro'];

// $d = DateTime::createFromFormat('!Y-m-d', $this->starts_on);
// $d->format('j') . ' de ' . self::MONTHS[(int) $d->format('n')]
//     . ' de ' . $d->format('Y');
```

  Nesse caso ajuste o `ListEventsCest` (confere `d/m/Y`) e o
  `EventsControllerTest` (confere a data na listagem).

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

Resultado obtido: Unit 167, Integration 47, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

No navegador (populate): `/admin/events` mostra a descrição cortada
com "..." e o tipo como badge colorido; `/admin/events/1` mostra a
descrição inteira e o mesmo badge.

## Relação com outros cenários

- N6 (`n6-badge-lotado-contador`): outro badge, na coluna "Vagas".
  Os dois juntos só conflitam no texto da tabela.
- R2 (`r2-nome-do-criador`): também acrescenta coluna e um método de
  apresentação no model (`creatorName()`); mesmo padrão, junte os
  dois.
- N3 (`n3-ordenar-por-data`): mexe no controller e no `Paginator`,
  sem conflito.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r3-helper-formatacao`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r3-helper-formatacao`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r3 \
    s50/fvp/4.2/exemplos/r3-helper-formatacao/projeto
git fetch fvp-r3 fvp/r3-helper-formatacao
git cherry-pick 035fce3
git remote remove fvp-r3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r3-helper-formatacao/solucao.bundle \
    fvp/r3-helper-formatacao:fvp-r3
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 035fce3
# opcional, limpa a ref
git branch -D fvp-r3
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r3-helper-formatacao/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r3-helper-formatacao/solucao.patch
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

- `index.phtml` e `show.phtml`: outros cenários mexem nas mesmas
  linhas da tabela e da `<dl>`. Fique com as células dos dois lados
  e confira que `<th>` e `<td>` continuam na mesma quantidade.
- `Event.php`: constantes e métodos novos em pontos próximos (um
  campo novo também mexe em `KINDS`/`$columns`). Mantenha todos; se
  um cenário criar um valor novo em `KINDS`, dê uma cor a ele em
  `KIND_BADGES` (o teste `test_every_kind_should_have_a_badge` cobra).
- `ListEventsCest`: asserções novas no mesmo trecho; mantenha todas.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Criar o helper e esquecer uma das views: a formatação continua
  duplicada. Procure com `grep -rn "date(" app/views`.
- `substr` no lugar de `mb_substr`/`mb_strimwidth`: corta acento no
  meio e a página mostra `�`.
- Escapar e depois cortar: entidade HTML pela metade.
- Classe do badge montada com o valor do banco sem o mapa
  (`text-bg-<?= $event->kind ?>`): classe inexistente e regra
  espalhada. Use `KIND_BADGES`, com `secondary` de reserva.
- Valor novo em `KINDS` sem cor em `KIND_BADGES`: badge cinza. O
  teste de mapa completo pega isso.
- Data por extenso com `strftime` (deprecado) ou `IntlDateFormatter`
  (sem `intl` na imagem): use array de meses.
