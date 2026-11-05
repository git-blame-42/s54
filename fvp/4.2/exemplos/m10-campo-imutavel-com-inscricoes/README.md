# M10. Campo que não muda depois de haver inscrições

Pedido, como o professor faria na hora: "Depois que um Evento tem
inscritos, a data e o tipo dele não podem mais mudar, e a capacidade
não pode ficar abaixo do número de inscritos. A regra é no model; no
form, avise o usuário."

Chance: média. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M10.

Branch no clone: `fvp/m10-campo-imutavel-com-inscricoes`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `e83ee0e` | `feat: Lock event start date and kind once it has...`     |

Mensagem completa: `feat: Lock event start date and kind once it has
enrollments`.

## O que mudou

- `app/Models/Event.php`:
  - no topo de `validates()`, o registro gravado é carregado **uma
    vez** (`$original = self::findById($this->id)`, `null` no
    create) e `$locked` é o mesmo `$original` só se o Evento tem
    Inscrições (senão `null`);
  - método privado novo
    `validatesUnchangedWithEnrollments(string $field, ?Event $locked):
    bool`, no molde de `validatesStartsOnNotPast`: com `$locked` e
    `$locked->$field !== $this->$field`, registra "não pode mudar: já
    há inscritos!" no campo e devolve `false`;
  - `validatesStartsOnNotPast` passa a receber o `$original` em vez
    de buscar de novo no banco.

```php
$original = $this->newRecord() ? null : self::findById($this->id);
$locked = ($original !== null && $this->hasEnrollments())
    ? $original : null;
// ...
Validations::notEmpty('kind', $this) &&
    Validations::inclusion('kind', $this, array_keys(self::KINDS)) &&
    $this->validatesUnchangedWithEnrollments('kind', $locked);
// ...
Validations::notEmpty('starts_on', $this) &&
    Validations::validDate('starts_on', $this) &&
    $this->validatesUnchangedWithEnrollments('starts_on', $locked) &&
    $this->validatesStartsOnNotPast($original);
```

- Capacidade: a regra já existe na base (regra P02 do projeto,
  `validatesCapacityAgainstEnrollments`, "não pode ser menor que o
  total de inscrições (N)!"). Nada novo no model; os testes daqui
  conferem as duas regras juntas.
- `app/views/admin/events/_form.phtml`: `$enrolled =
  $event->enrollmentsCount()` (0 no new). Com inscritos:
  - data: `readonly` e a dica "A data não pode mudar: o Evento já
    tem N inscrito(s).";
  - tipo: `<select ... disabled>` mais
    `<input type="hidden" name="event[kind]" value="...">` e a dica
    "O tipo não pode mudar: o Evento já tem inscritos.";
  - capacidade: `min="N"` e "Mínimo: N (número de inscritos)."
- Testes:
  - `EventTest` (8): com inscritos a data e o tipo não mudam e o
    banco fica igual; sem inscritos mudam; com inscritos, mesma data
    e capacidade igual ao nº de inscritos salvam; data inválida
    mantém "não é uma data válida!" e tipo fora do ENUM mantém "não é
    uma opção válida!"; data e capacidade erradas juntas dão os dois
    erros.
  - `EventsControllerTest` (4): update com data nova ou tipo novo num
    Evento com inscrito volta ao form com o erro e não grava nada;
    edit mostra `readonly`, `disabled`, o campo oculto e os avisos com
    inscritos, e nada disso sem inscritos.
  - Cest novo `UpdateEventWithEnrollmentsCest` (2): no Evento 01 da
    base (3/3), os avisos aparecem, forçar a data pelo navegador é
    recusado pelo model, e editar só o título funciona (o tipo segue
    pelo campo oculto).

## Por que assim

- Regra no model, em `validates()`: o form só avisa. O Cest prova
  isso: ele troca o valor do campo `readonly` por JavaScript e o
  `save()` recusa.
- `readonly`, não `disabled`, na data: campo `disabled` não é
  enviado; o `fill` do controller deixaria `starts_on` nulo e o erro
  seria "não pode ser vazio!", confuso para quem só mudou o título.
- `<select>` não tem `readonly`. Por isso o tipo usa `disabled` (só
  visual) mais um campo oculto com o mesmo nome, que leva o valor
  atual. Quem forjar o campo oculto esbarra na regra do model.
- Um método só, com o nome do campo: travar outro campo (`location`,
  `ends_on` do M4) é uma linha a mais na cadeia dele.
- Ordem na cadeia: depois de `validDate`/`inclusion` (valor inválido
  mostra a mensagem de formato) e, na data, antes de
  `validatesStartsOnNotPast` (com inscritos, a data mudar já é o
  erro; não faz sentido também falar de passado). Cada campo guarda
  uma mensagem só e a última vence: por isso o método devolve `bool`
  e corta a cadeia.
- Compara com o banco (`findById`), não com um valor guardado no
  objeto: o model do framework não guarda o "antes". A busca é feita
  uma vez por validação e reaproveitada pelas três regras (`kind`,
  `starts_on` imutável e "não passada"); buscar em cada método daria
  três consultas iguais por `save()`.
- Capacidade: não dupliquei a regra P02 do projeto nem troquei a
  mensagem dela ("total de inscrições (3)!" é mais informativa e já é
  conferida por testes e Cests da base). Se o professor exigir o texto "não pode
  ser menor que o número de inscritos!", troque só a string em
  `validatesCapacityAgainstEnrollments` e os testes que a citam
  (`grep -rn "total de inscrições" tests/`).

## Como verificar

```
./run up -d db php php_test web
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 167 testes, Integration 49, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `EventTest`:
  - `test_kind_cannot_change_when_the_event_has_enrollments`
  - `test_kind_can_change_when_the_event_has_no_enrollments`
  - `test_kind_outside_enum_on_event_with_enrollments_keeps_the_...`
    (`..._enum_message`)
  - `test_starts_on_cannot_change_when_the_event_has_enrollments`
  - `test_starts_on_can_change_when_the_event_has_no_enrollments`
  - `test_event_with_enrollments_can_be_edited_keeping_the_same_...`
    (`..._starts_on`)
  - `test_invalid_starts_on_on_event_with_enrollments_keeps_the_...`
    (`..._date_message`)
  - `test_starts_on_and_capacity_errors_together_on_event_with_...`
    (`..._enrollments`)
- `EventsControllerTest`:
  - `test_update_starts_on_of_event_with_enrollments_is_rejected`
  - `test_update_kind_of_event_with_enrollments_is_rejected`
  - `test_edit_shows_starts_on_locked_when_event_has_enrollments`
  - `test_edit_does_not_lock_starts_on_without_enrollments`
- `UpdateEventWithEnrollmentsCest`:
  - `startsOnIsLockedAndRejectedWithEnrollments`
  - `eventWithEnrollmentsCanBeEditedKeepingTheDate`

No navegador (populate): Admin, "Editar" no primeiro Evento (3/3). A
data aparece travada com o aviso "o Evento já tem 3 inscrito(s)", o
tipo desabilitado e a capacidade com "Mínimo: 3". Por HTTP:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/1/edit \
    | grep -E 'readonly|disabled|inscrito'
```

## Relação com outros cenários

- Q2 (`q2-bloquear-passado-cancelado`): trava o Evento inteiro
  (passado ou cancelado) com `isEditable()` no controller e nas
  views; este cenário trava só um campo, por regra de validação. Com
  os dois, o `isEditable()` barra antes, e esta regra vale para os
  Eventos que ainda podem ser editados.
- M4 (`m4-segunda-data-ends-on`): com `ends_on`, decida com o
  professor se a data de término também fica imutável com inscritos.
  Se sim, encadeie
  `validatesUnchangedWithEnrollments('ends_on', $locked)`
  antes de `validatesEndsOnNotBeforeStartsOn()`.
- M5, M6 e M2 mexem em linhas vizinhas de `validates()`; junte as
  chamadas.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m10-campo-imutavel-com-inscricoes`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh \
    s50/fvp/4.2/exemplos/m10-campo-imutavel-com-inscricoes
cd /caminho/do/seu/repositorio
git remote add fvp-m10 \
    s50/fvp/4.2/exemplos/m10-campo-imutavel-com-inscricoes/projeto
git fetch fvp-m10 fvp/m10-campo-imutavel-com-inscricoes
git cherry-pick e83ee0e
git remote remove fvp-m10
```

### 2. A partir do `solucao.bundle`

```
D=s50/fvp/4.2/exemplos/m10-campo-imutavel-com-inscricoes
git fetch $D/solucao.bundle \
    fvp/m10-campo-imutavel-com-inscricoes:fvp-m10
git cherry-pick e83ee0e
# opcional, limpa a ref
git branch -D fvp-m10
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/m10-campo-imutavel-com-inscricoes
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

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

- `app/Models/Event.php`, cadeias de `kind` e `starts_on` em
  `validates()`: M4 (`ends_on`), M8 (valor novo no ENUM) e qualquer
  regra nova de data mexem no mesmo bloco. Mantenha a ordem:
  `notEmpty`, formato, esta regra, "não passada".
- `_form.phtml`, campos de tipo, data e vagas: M1, M4, M5 acrescentam campos
  vizinhos. Fique com os dois lados; o `$enrolled` vai no topo, junto
  de `$invalid`.
- `EventTest.php`, `EventsControllerTest.php`: testes novos no mesmo
  ponto; fique com os dois.

## Armadilhas

- `disabled` sem campo oculto: o valor não é enviado e o update falha
  com "não pode ser vazio!". Na data, use `readonly`; no `<select>`,
  `disabled` + `<input type="hidden">` com o mesmo `name`.
- Ao travar o tipo no form, o `fillEventForm` do Cest não consegue
  usar `selectOption` num `<select disabled>`; nos Cests que editam
  Evento com inscritos, não mande `kind`.
- Confiar só no `readonly`: qualquer um muda o valor pelo DevTools ou
  por `curl`. A regra tem de estar no model (o Cest testa isso).
- Esquecer o `newRecord()` antes do `findById`: no create não há
  `id`, e `findById(null)` dá `TypeError` (o parâmetro é `int`).
- Chamar `self::findById($this->id)` dentro de cada regra: funciona,
  mas repete a mesma consulta; carregue uma vez em `validates()` e
  passe por parâmetro.
- Usar `Model::update([...])` para testar: ele não valida. Nos testes,
  mude o atributo e chame `save()`.
- O `hasEnrollments()` consulta o banco (uma vez por validação); o
  form também chama
  `enrollmentsCount()`. São consultas a mais por tela de edição,
  aceitáveis aqui.
- Evento já passado e com inscritos: a data fica travada por esta
  regra (antes, a regra "só quando muda" já permitia editar o resto,
  e continua permitindo).
