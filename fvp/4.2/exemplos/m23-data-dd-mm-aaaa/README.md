# M23. Data digitada dd/mm/aaaa

Pedido, como o professor faria na hora: "Quero digitar a data do Evento
como dd/mm/aaaa, num campo de texto. O banco continua com DATE. Na
edição, o formulário tem que mostrar a data em dd/mm/aaaa."

Chance: baixa. Tempo estimado: 20-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M23.

Branch no clone: `fvp/m23-data-dd-mm-aaaa`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                       |
|-----------|------------------------------------------------|
| `c76e391` | `feat: Accept event date typed as dd/mm/yyyy`  |

## O que mudou

- `app/Models/Event.php`:
  - constante `STARTS_ON_INPUT_FORMAT = 'd/m/Y'` e propriedade privada
    `$startsOnFromInput` (true enquanto `starts_on` guarda o texto
    digitado, ainda não convertido);
  - `setStartsOnInput(string $value)`: põe o texto em `starts_on` e
    liga a marca;
  - `validates()`: no lugar de `validDate('starts_on', $this)` chama
    `validatesStartsOnFormat()`. Se a data veio do formulário, ele usa
    `validDate('starts_on', $this, 'd/m/Y')` e, se passar, converte
    para `Y-m-d` ali mesmo (antes do bind do `save()`) e desliga a
    marca. Data vinda do banco ou do código continua `Y-m-d`;
  - `startsOnInput(): string`: valor do campo no formulário. Converte
    o `Y-m-d` para `dd/mm/aaaa`; texto que não passou na validação
    volta exatamente como foi digitado.
- `app/Controllers/Admin/EventsController.php`: `starts_on` sai de
  `FIELDS`; o `fill` chama `$event->setStartsOnInput(...)` com o valor
  do formulário (`''` se não for escalar).
- `app/views/admin/events/_form.phtml`: `type="date"` vira
  `type="text"`, com `placeholder="dd/mm/aaaa"`, `maxlength="10"`,
  `inputmode="numeric"` e `value` de `$event->startsOnInput()`.
- Listagem e `show` sem mudança (já mostram `d/m/Y`). Schema e
  populate sem mudança.
- Testes ajustados:
  - `BaseEventsCest::fillEventForm` digita a data com `fillField` em
    `dd/mm/aaaa` (helper novo `typedDate()`); os Cests continuam
    guardando a data em `Y-m-d` e comparando com o banco. Sai o
    `executeJS` que só servia para o `input[type=date]`.
  - `UpdateSuccessfullyCest`: o campo abre com `typedDate(...)`.
  - `EventsControllerTest`: `validParams()` (POST) usa `d/m/Y`;
    `createEvent()` (pelo model) usa `Y-m-d`; `new` confere o
    placeholder e que não há mais `type="date"`; o update inválido
    confere a data de volta em `dd/mm/aaaa`.
  - `EventsAccessTest`: o POST do participante usa `d/m/Y`. Com
    `Y-m-d` o teste passaria mesmo sem o bloqueio de acesso, porque a
    data seria recusada.

## Por que assim

- Formato do banco num lugar, formato da tela em outro: `starts_on`
  em memória é `Y-m-d` sempre que veio do banco ou do código. Por
  isso populate, testes que criam Evento pelo model e as views de
  listagem e `show` não mudam.
- Validação e conversão no model (padrão cobrado), com a função do
  `lib/Validations.php` que já aceita formato: `validDate(..., 'd/m/Y')`.
  O controller só repassa o texto. Converter no controller
  (`DateTime::createFromFormat` no `fill`) deixaria a regra fora do
  model e não daria o erro "não é uma data válida!" sob o campo.
- A marca `$startsOnFromInput` diz qual formato validar. Sem ela, ou
  o model aceitaria os dois formatos (o campo deixaria passar
  `2030-12-25`), ou exigiria `d/m/Y` também do populate e dos testes.
- A conversão acontece dentro do `validates()`, antes de
  `validatesStartsOnNotPast()`. Assim a comparação da linha 93
  (`$original->starts_on === $this->starts_on`) compara `Y-m-d` com
  `Y-m-d`. Ver Armadilhas.
- A conversão também tem que acontecer antes do bind do
  `Model::save()` (linha 153 do core), que grava `$this->starts_on`
  como está. O MySQL recusa `25/12/2030` numa coluna DATE.
- `validDate` é estrito (`format()` precisa devolver o mesmo texto):
  `1/2/2030` e `25/12/30` são recusados. É o "dd/mm/aaaa" do pedido.
- `maxlength` e `placeholder` só ajudam quem digita; a regra é a do
  model (o form tem `novalidate`).

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

Resultado obtido: Unit 166 testes, Integration 49, Acceptance 25
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou;
`./run db:populate` roda sem erro.

Testes novos:

- `EventTest`:
  - `test_typed_starts_on_should_be_saved_as_database_date`
  - `test_typed_starts_on_should_be_in_dd_mm_yyyy`
  - `test_typed_starts_on_should_not_be_in_the_past`
  - `test_empty_typed_starts_on_should_be_required`
  - `test_starts_on_input_should_show_stored_date_as_dd_mm_yyyy`
  - `test_past_event_can_be_edited_when_typed_starts_on_does_not_change`
  - `test_past_event_should_reject_a_new_typed_past_starts_on`
- `EventsControllerTest`:
  - `test_create_converts_typed_date_to_database_format`
  - `test_create_with_date_in_another_format_renders_form_with_typed_value`
  - `test_edit_shows_date_as_dd_mm_yyyy`
  - `test_update_of_past_event_keeping_the_typed_date_is_accepted`
- `CreateWithInvalidDataCest::createWithDateInWrongFormat`
  (`31/02/2030`: erro sob o campo e o texto de volta).
- `UpdateSuccessfullyCest::updatePastEventWithoutChangingTheDate`
  (Evento movido para o passado, edita só o título e salva).

No navegador: Admin, Editar um Evento. O campo Data mostra, por
exemplo, `20/11/2026`. Troque por `2099-01-01` e salve: o form volta
com "não é uma data válida!" e o texto digitado. Com `01/01/2099`,
salva e o `show` mostra `01/01/2099`.

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`): mesmo tipo de pedido ("regra no
  model com `lib/Validations.php`"). Aqui não há função nova: o
  `validDate` já recebe o formato. Os dois mexem em `validates()`.
- R3 (`r3-helper-formatacao`): cria `formattedStartsOn()` (Y-m-d para
  dd/mm/aaaa) para listagem e `show`. Com os dois aplicados,
  `startsOnInput()` pode usar `formattedStartsOn()` no caso do banco;
  só o caso "texto inválido volta como veio" é deste cenário.
- M4 (`m4-segunda-data-ends-on`) e M7 (`m7-horario-do-evento`): uma
  segunda data ou um horário no form precisam da mesma ideia (texto
  digitado, validar com formato, converter antes do bind), e a
  comparação "término não antes do início" tem que usar datas já
  convertidas.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m23-data-dd-mm-aaaa`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m23-data-dd-mm-aaaa
cd /caminho/do/seu/repositorio
git remote add fvp-m23 \
    s50/fvp/4.2/exemplos/m23-data-dd-mm-aaaa/projeto
git fetch fvp-m23 fvp/m23-data-dd-mm-aaaa
git cherry-pick c76e391
git remote remove fvp-m23
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/m23-data-dd-mm-aaaa/solucao.bundle \
    fvp/m23-data-dd-mm-aaaa:fvp-m23
git cherry-pick c76e391
# opcional, limpa a ref
git branch -D fvp-m23
```

### 3. A partir do `solucao.patch`

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m23-data-dd-mm-aaaa/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m23-data-dd-mm-aaaa/solucao.patch
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

- `app/Models/Event.php`, `validates()`: M1-M7 mexem no mesmo bloco.
  Fique com as linhas dos dois lados; a linha da data tem que chamar
  `validatesStartsOnFormat()` (não `validDate` direto).
- `EventsController::fill` e `FIELDS`: cenários com campo novo
  acrescentam itens em `FIELDS`. Junte as listas, sem `starts_on`.
- `_form.phtml`, campo Data: M4/M7 acrescentam campos vizinhos.
  Mantenha `type="text"` e `startsOnInput()` neste campo.
- `BaseEventsCest::fillEventForm`: outro cenário que ainda use
  `executeJS` para a data volta a quebrar com campo de texto; use
  `fillField` + `typedDate()`.
- Arquivos de teste: testes novos no mesmo ponto; fique com os dois.

## Armadilhas

- A comparação da linha 93 (`validatesStartsOnNotPast`): se o model
  guardasse `dd/mm/aaaa` até o `save()`, o original lido do banco
  (`Y-m-d`) nunca seria igual ao valor do form. Um Evento que já
  aconteceu deixaria de poder ser editado ("não pode estar no
  passado!" mesmo sem mudar a data). Converta antes de comparar; o
  teste `..._when_typed_starts_on_does_not_change` cobre isso.
- Sem converter antes do bind, o MySQL recusa `25/12/2030` na coluna
  DATE (exceção, 500) ou grava lixo, conforme o `sql_mode`.
- `notPastDate` também recebe formato: se validar `d/m/Y` sem
  converter, passe o formato nos dois (`validDate` e `notPastDate`).
- Edição sem conversão na view: o campo abre com `2026-11-20`, que o
  próprio form recusaria ao salvar.
- `strtotime('25/12/2030')` não entende o formato brasileiro (com `/`
  ele lê mês/dia); use `DateTimeImmutable::createFromFormat('!d/m/Y')`.
- O `!` no formato zera hora e minuto; sem ele, a data vem com a hora
  atual e a comparação com "hoje" fica errada.
- `input[type=date]` envia sempre `Y-m-d`, qualquer que seja o idioma
  do navegador. Não adianta só mudar o `validDate`: troque o tipo do
  campo para `text`.
- Pergunte antes se basta o `type="date"`: com o navegador em pt-BR
  o campo da base já **mostra** dd/mm/aaaa (e tem calendário), só
  **envia** `Y-m-d`. Se o pedido for "o usuário vê dd/mm/aaaa", nada
  precisa mudar. Este exemplo vale para "campo de texto digitado".
- Com `type="text"` o seletor de calendário do navegador some. Se
  pedirem máscara (barras automáticas), o JavaScript vai num arquivo
  em `public/assets/js/` (ao lado do `application.js`), nunca inline
  na view; a regra continua no model.
- Aqui a conversão para `Y-m-d` acontece dentro de `validates()`, que
  então altera o objeto. Funciona porque o `save()` valida antes do
  bind; o lugar mais limpo é o gancho `beforeValidation` do M16
  (catálogo, M16), se ele já estiver aplicado. Formato: `'d/m/Y'` com
  a comparação estrita do `validDate` recusa `5/3/2031`. Se o
  professor quiser aceitar sem zero à esquerda, use `'j/n/Y'` (e
  `'!j/n/Y'` no `createFromFormat`); aí é `05/03/2031` que passa a
  ser recusado, então para aceitar os dois tente um formato e depois
  o outro.
- Testes que fazem POST precisam mandar `d/m/Y`; testes que criam
  Evento pelo model continuam com `Y-m-d`. Misturar os dois faz o
  `save()` falhar em silêncio no helper.
