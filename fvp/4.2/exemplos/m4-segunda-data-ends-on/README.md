# M4. Segunda data (`ends_on`) não anterior a `starts_on`

Pedido, como o professor faria na hora: "O Evento passa a ter data de
término (`ends_on`), obrigatória, que não pode ser antes da data de
início. Mostre a data no formulário, na listagem e no detalhe."

Chance: média-alta. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M4.

Branch no clone: `fvp/m4-segunda-data-ends-on`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                             |
|-----------|--------------------------------------|
| `97503d2` | `feat: Add end date to events`       |

## O que mudou

- `database/schema.sql`: `ends_on DATE NOT NULL` depois de `starts_on`.
- `app/Models/Event.php`:
  - `@property string $ends_on` e a chave em `$columns`;
  - em `validates()`: `notEmpty('ends_on') && validDate('ends_on') &&
    validatesEndsOnNotBeforeStartsOn()`;
  - método privado `validatesEndsOnNotBeforeStartsOn()`: converte as
    duas datas com `DateTimeImmutable::createFromFormat('!Y-m-d', ...)`
    (helper privado `parseDate`, que confere o formato de volta) e só
    compara se as duas forem válidas. Erro em `ends_on`:
    "não pode ser anterior à data de início!". Mesmo dia é válido.
- `EventsController`: `'ends_on'` em `FIELDS`.
- `_form.phtml`: campo "Data de término" (`type="date"`), cópia do de
  `starts_on`, com `$invalid()` e `$feedback()`.
- `index.phtml`: coluna "Término"; `show.phtml`: par "Término".
- `populate.php`: `ends_on` igual ao início; Semanas acadêmicas
  terminam 4 dias depois (5 dias de evento).
- Testes com a chave nova: `params()` do `EventTest` (+31 dias),
  `EnrollmentTest`, `validParams()` do `EventsControllerTest`
  (+12 dias), `EventsAccessTest`, `BaseEventsCest` (`seedEvents` com
  término no mesmo dia; `validEventData` usa o início como término se
  o teste não informar; `fillEventForm` define `ends_on` por
  `executeJS`, como já fazia com `starts_on`).
- Testes novos:
  - `EventTest`: `ends_on` obrigatório (provider e formulário vazio),
    `test_ends_on_equal_to_starts_on_should_be_valid` (com `save()`),
    `test_ends_on_before_starts_on_should_be_invalid`,
    `test_ends_on_should_be_a_valid_date`,
    `test_ends_on_should_not_be_compared_with_an_invalid_starts_on`.
  - `EventsControllerTest`:
    `test_create_with_ends_on_before_starts_on_shows_error_below_the_field`
    e `test_create_saves_ends_on_and_show_displays_it`.
  - `CreateWithInvalidDataCest::createWithEndsOnBeforeStartsOn` (erro
    sob o campo, datas e título mantidos); o formulário vazio também
    marca `ends_on`. `CreateSuccessfullyCest` confere o `ends_on`.

## Por que assim

- Validação entre campos é regra do model: fica em `validates()`, num
  método privado, como o `validatesStartsOnNotPast()` já existente.
- `parseDate` de `lib/Validations.php` é privado; por isso o model tem
  o próprio, com o mesmo `createFromFormat('!Y-m-d')` e a conferência
  do formato (rejeita `2030-02-31`).
- Só compara com as duas datas válidas: com `starts_on` inválida, o
  usuário vê um erro só (o de `starts_on`), não dois contraditórios.
- A regra "não no passado" não foi copiada para `ends_on` (não foi
  pedida; o término nunca é antes do início, que já é cobrado).
- Mensagem igual à do catálogo M4.

## Como verificar

```
./run up -d
# refaz o schema (o populate roda migrate()) e popula
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
```

Resultado obtido: Unit 164 testes, Integration 47, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

No navegador (admin@example.com / 123456): a listagem tem a coluna
"Término" (a Semana Acadêmica termina 4 dias depois do início). Em
"Novo Evento", término um dia antes do início mostra o erro embaixo do
campo e mantém os outros valores.

## Relação com outros cenários

- M1 (`m1-campo-novo-workload-hours`): mesmo roteiro de campo novo,
  nas mesmas linhas (schema, `$columns`, `FIELDS`, form, views,
  populate, helpers de teste). Aplicando os dois, fique com as duas
  chaves em cada lista.
- M3 (`m3-enum-novo-seminar`) e P11 (`p11-soft-delete-arquivar`):
  também mudam `schema.sql`; conflito só de vizinhança.
- T1 (`t1-populate-campo-eventos-admin`): Eventos novos no populate
  precisam de `ends_on` se este cenário estiver aplicado.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m4-segunda-data-ends-on`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m4-segunda-data-ends-on`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m4 \
    s50/fvp/4.2/exemplos/m4-segunda-data-ends-on/projeto
git fetch fvp-m4 fvp/m4-segunda-data-ends-on
git cherry-pick 97503d2
git remote remove fvp-m4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m4-segunda-data-ends-on/solucao.bundle \
    fvp/m4-segunda-data-ends-on:fvp-m4
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 97503d2
# opcional, limpa a ref
git branch -D fvp-m4
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m4-segunda-data-ends-on/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m4-segunda-data-ends-on/solucao.patch
```

Depois de aplicar: `./run db:populate` (schema mudou), os testes e o
`./run phpcs`.

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

- `schema.sql`, `Event.php` (`@property`, `$columns`, `validates()`),
  `FIELDS`, `_form.phtml`, `index.phtml`, `show.phtml`: todo campo novo
  (M1, P1, P5) mexe nas mesmas linhas. Fique com os dois campos.
- `BaseEventsCest::fillEventForm`: aqui o `executeJS` virou um laço
  sobre `starts_on` e `ends_on`; outro cenário que mexa no bloco de
  `starts_on` precisa ser encaixado no laço.
- `populate.php` e os helpers de teste: mantenha as chaves dos dois.

## Armadilhas

- Comparar sem validar antes: com `starts_on` inválida, `strtotime` ou
  `createFromFormat` devolvem `false`/datas estranhas e aparece um erro
  falso em `ends_on`. Por isso o método só compara datas válidas.
- `createFromFormat` sem `!` herda a hora atual; com `!` compara só o
  dia. E sem conferir `format()` de volta, `2030-02-31` vira março.
- Esquecer `ends_on` no `seedEvents`: nenhum Evento é gravado e os
  Cests falham com listagem vazia, sem mensagem clara.
- Teste de aceitação que muda só o início (ex.: `+90 days` no
  `UpdateSuccessfullyCest`) passa a precisar de término: o
  `validEventData` cuida disso usando o início como término.
- Selenium: `type="date"` não aceita `fillField` em pt-BR; use o
  `executeJS` da base.
- Esquecer `db:populate`: `Unknown column 'ends_on'` só no navegador.
