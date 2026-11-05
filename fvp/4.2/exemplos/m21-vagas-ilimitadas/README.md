# M21. Vagas ilimitadas (`capacity NULL`)

Pedido, como o professor faria na hora: "Evento online não tem limite
de vagas. Permita cadastrar um Evento sem vagas: campo em branco quer
dizer ilimitado. A listagem e o show não podem mostrar zero nem
'0 disponíveis' para esses Eventos."

Chance: média-baixa. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M21.

Branch no clone: `fvp/m21-vagas-ilimitadas`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                     |
|-----------|----------------------------------------------|
| `089c120` | `feat: Allow events with unlimited capacity` |

## O que mudou

- `database/schema.sql`: `capacity INT NULL` (era `NOT NULL`), com
  comentário "NULL = vagas ilimitadas". O
  `CHECK (capacity >= 1)` fica como está: com `NULL` a condição dá
  `UNKNOWN`, e o MySQL só rejeita `FALSE`.
- `app/Models/Event.php`:
  - PHPDoc `@property int|string|null $capacity`.
  - Em `validates()`, o `notEmpty('capacity')` sai. Vagas em branco
    (`''`, como chega do form) viram `null`; só com número valem o
    `integerMin(1)` e a regra "não menor que as Inscrições":

```php
// Vagas em branco = ilimitadas: grava NULL (a coluna INT não aceita '').
if ($this->capacity === '') {
    $this->capacity = null;
}

if (!$this->hasUnlimitedCapacity() &&
    Validations::integerMin('capacity', $this, 1)) {
    $this->validatesCapacityAgainstEnrollments();
}
```

  - Método novo `hasUnlimitedCapacity(): bool` (`null` ou `''`).
  - `availableSpots()` passa a `?int`: devolve `null` quando as vagas
    são ilimitadas (o Evento nunca lota); senão, a conta de antes.
- `index.phtml`: Evento ilimitado mostra "2 (ilimitadas)" na coluna
  Vagas; os demais seguem "inscritos/capacity".
- `show.phtml`: Evento ilimitado mostra "2 (vagas ilimitadas)", sem o
  "(N disponíveis)".
- `_form.phtml`: dica "Deixe em branco para vagas ilimitadas." sob o
  campo Vagas (o `min="1"` continua).
- `populate.php`: os Eventos online de índice 1 e 11 (ids 2 e 12)
  ficam com `capacity` `null`. O de id 2 tem 2 Inscrições, então a
  tela mostra o caso "ilimitado com inscritos". O outro online (id 8)
  continua com 40 vagas: online não é obrigado a ser ilimitado.
- Testes:
  - `EventTest`: `capacity` sai da lista de obrigatórios (formulário
    vazio não marca Vagas). Novos: em branco salva `NULL` e volta
    `null` do banco; `null` é válido; com número não é ilimitado;
    ilimitado com 3 Inscrições continua com `availableSpots()` `null`;
    lotado (3/3) pode virar ilimitado; ilimitado com 3 Inscrições não
    aceita vagas 2; o `CHECK` do banco ainda barra `capacity = 0`.
  - `EventsControllerTest`: create com Vagas em branco grava `NULL`;
    index mostra "1 (ilimitadas)" e não "1/0"; show não mostra
    "disponíveis"; edit abre o campo vazio e com a dica.
  - `CreateWithInvalidDataCest`: o formulário vazio não marca mais
    `#event_capacity`.
  - Cest novo `tests/Acceptance/admin/events/UnlimitedCapacityCest.php`
    (cadastra com Vagas em branco; tira o limite do Evento lotado).

## Por que assim

- Decisão: vagas em branco = ilimitado, para qualquer modalidade. O
  pedido cita online como exemplo; o catálogo descreve "capacity
  NULL" sem amarrar à modalidade. Se o professor disser "só online
  pode ser ilimitado", veja a variante abaixo.
- `NULL` no banco e não um número mágico (0, -1, 999999): `0` bate no
  `CHECK`, e qualquer número fixo entra nas contas de vagas como se
  fosse limite de verdade.
- A conversão `'' → null` fica no model (início de `validates()`): o
  form manda `''`, e o `Model::save()` grava todas as colunas. `''`
  numa coluna `INT` no modo estrito do MySQL dá
  `Incorrect integer value` (500). No model vale para o form, os
  testes e o populate; o controller continua sem regra.
- `availableSpots()` devolve `null` em vez de um número grande: quem
  usa precisa decidir o que mostrar (o retorno `?int` deixa isso
  explícito). "Ilimitado" não é "0 disponíveis".
- Regra P02 (vagas não abaixo das Inscrições) continua: só não se
  aplica quando não há limite. Tirar o limite de um Evento lotado é
  permitido; voltar a limitar abaixo das Inscrições é barrado.
- Texto nas views no lugar do `(int) $event->capacity` (ver
  Armadilhas). `capacity` não é texto livre, então não precisa de
  `htmlspecialchars`; o cast `(int)` fica só no ramo com limite.

### Variante: só Evento online pode ser ilimitado

Mesma base (schema, `hasUnlimitedCapacity`, views). Em `validates()`,
presencial e híbrido voltam a exigir Vagas:

```php
if ($this->capacity === '') {
    $this->capacity = null;
}

if ($this->modality !== 'online') {
    Validations::notEmpty('capacity', $this);
}

if (!$this->hasUnlimitedCapacity() &&
    Validations::integerMin('capacity', $this, 1)) {
    $this->validatesCapacityAgainstEnrollments();
}
```

Ajuste a dica do form para "Em branco = ilimitado (só Eventos
online)." e devolva `capacity` à lista do formulário vazio nos testes
(o `params()` padrão é presencial). Nos testes de ilimitado, use
`'modality' => 'online'`.

## Como verificar

```
./run up -d db php php_test web
# schema mudou: refaz o banco de desenvolvimento
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 165 testes, Integration 49, Acceptance 25
(base: 159 / 45 / 23). Unit: 7 novos e 1 a menos (o caso `capacity`
saiu do `requiredFieldsProvider`). PHPCS e PHPStan sem erros.
`db:populate` cria os 16 Eventos e 14 Inscrições sem erro.

Testes novos:

- `EventTest`:
  - `test_blank_capacity_should_save_as_unlimited`
  - `test_null_capacity_should_be_valid`
  - `test_event_with_capacity_should_not_be_unlimited`
  - `test_unlimited_event_should_never_be_full`
  - `test_limited_event_with_enrollments_can_become_unlimited`
  - `test_unlimited_event_cannot_get_capacity_below_enrollments`
  - `test_database_should_still_reject_capacity_below_one`
- `EventsControllerTest`:
  - `test_create_with_blank_capacity_saves_unlimited_event`
  - `test_index_shows_unlimited_instead_of_zero_capacity`
  - `test_show_unlimited_event_does_not_show_zero_available`
  - `test_edit_unlimited_event_keeps_capacity_blank`
- `UnlimitedCapacityCest`:
  - `createEventWithUnlimitedCapacity`
  - `fullEventCanBecomeUnlimited`

No navegador (populate): `/admin/events` mostra "2 (ilimitadas)" na
linha de "Introdução a APIs REST com PHP"; `/admin/events/2` mostra
"2 (vagas ilimitadas)". Na base, o mesmo Evento com `NULL` mostraria
"2/0" e "(0 disponíveis)". Em "Novo Evento", deixe Vagas em branco e
salve: o show mostra "0 (vagas ilimitadas)". Por HTTP, de dentro da
rede do compose:

```
docker compose exec -T web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec -T web curl -s -b /tmp/c \
    http://localhost/admin/events/2 | grep -A4 '>Vagas<'
```

## Relação com outros cenários

- M5 (`m5-validacao-condicional-modalidade`): mesma ideia de regra
  por modalidade. Com os dois, a variante "só online pode ser
  ilimitado" acima encaixa no mesmo `if ($this->modality ...)` do M5;
  junte os ramos.
- N6 (`n6-badge-lotado-contador`): o badge "Lotado" usa
  `availableSpots() === 0`, que com `null` dá `false`: Evento
  ilimitado nunca aparece lotado. Não troque por `!availableSpots()`
  nem `<= 0` (ver Armadilhas). O total de vagas do painel precisa
  pular os `NULL` ou mostrar "ilimitado".
- P2 (`p2-inscricao-participante`): a validação de vaga usa
  `availableSpots() <= 0`, e `null <= 0` é `true` no PHP. Com este
  cenário, troque por:

```php
$spots = $event->availableSpots();
if ($spots !== null && $spots <= 0) {
    $this->addError('base', 'não há vagas neste evento!');
}
```

- O1 (`o1-rota-eventos-lotados`): filtra com `=== 0`; ilimitado fica
  fora da lista, como deve.
- N13 (`n13-filtro-find-in-set-vagas`): `capacity > (SELECT COUNT...)`
  com `NULL` dá `NULL` e o Evento ilimitado some do filtro "com
  vagas". Use `(capacity IS NULL OR capacity > (...))`.
- O4 (`o4-resposta-json`): `(int) capacity` vira `0`; mande `null`
  (`$e->capacity === null ? null : (int) $e->capacity`) e
  `available_spots` `null`.
- N8 (`n8-ordenar-pelo-cabecalho`): `ORDER BY capacity` põe os `NULL`
  primeiro no `ASC`.
- M2 (`m2-validacao-maxlength`): "vagas até 500" mexe na mesma linha
  do `integerMin`; mantenha o `!hasUnlimitedCapacity() &&` na frente.
- S4 (`s4-testes-de-borda`): testes de borda escritos sobre a base
  supõem Vagas obrigatório; aqui em branco passa a ser válido.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m21-vagas-ilimitadas`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m21-vagas-ilimitadas
cd /caminho/do/seu/repositorio
git remote add fvp-m21 \
    s50/fvp/4.2/exemplos/m21-vagas-ilimitadas/projeto
git fetch fvp-m21 fvp/m21-vagas-ilimitadas
git cherry-pick 089c120
git remote remove fvp-m21
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/m21-vagas-ilimitadas/solucao.bundle \
    fvp/m21-vagas-ilimitadas:fvp-m21
git cherry-pick 089c120
# opcional, limpa a ref
git branch -D fvp-m21
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/m21-vagas-ilimitadas
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

Depois de aplicar: `./run db:populate` (o schema mudou).

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue
# ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `database/schema.sql`: M1, M3 e M5 mexem no mesmo bloco de colunas
  do `events`. Fique com as duas mudanças.
- `Event::validates()`: M2, M5 e M6 mexem nas linhas vizinhas. O bloco
  de `capacity` deste cenário substitui o `if (notEmpty(...) &&
  integerMin(...))` da base; leve a regra do outro cenário para
  dentro dele.
- `index.phtml` e `show.phtml`: N6 (badge), N8 (cabeçalho) e R3
  (helper) mexem na célula Vagas. Mantenha o `if
  ($event->hasUnlimitedCapacity())` por fora e o código do outro
  cenário no ramo `else`.
- `populate.php`: outro cenário que muda o array de Eventos; junte as
  colunas e mantenha os `null` dos ids 2 e 12.
- `EventTest` (`requiredFieldsProvider`, formulário vazio) e
  `CreateWithInvalidDataCest`: outro cenário que inclui campo na lista;
  `capacity` continua fora.

## Armadilhas

- `(int) null` é `0`. Na base há três pontos com esse cast:
  `availableSpots()` no model e a célula Vagas no `index.phtml` e no
  `show.phtml`. Só trocar o schema faz a tela mostrar "2/0" e
  "(0 disponíveis)" para um Evento sem limite.
- `null <= 0` e `!null` são `true` no PHP: qualquer "lotado" escrito
  assim trata o Evento ilimitado como lotado. Compare com `=== 0` ou
  confira `null` antes.
- Esquecer o `'' → null`: o form manda `''`, o `save()` faz
  `bindValue('')` numa coluna `INT` e o MySQL estrito responde 500.
  Com `capacity` em branco o `integerMin` também marcaria "deve ser um
  número inteiro!".
- Manter `capacity INT NOT NULL` no schema: o `save()` de um Evento
  ilimitado dá `Integrity constraint violation`.
- Schema mudou: rode `./run db:populate`. Os testes recriam o schema
  sozinhos, então passar nos testes não prova que o banco de
  desenvolvimento foi atualizado.
- Não remova o `CHECK`: ele aceita `NULL` e continua barrando `0` e
  negativos gravados sem `validates()` (ex.: `update([...])`).
- `availableSpots()` agora é `?int`. Código que fazia conta com o
  retorno (`$event->availableSpots() - 1`) trata `null` como `0` sem
  aviso; confira o `null` antes.
