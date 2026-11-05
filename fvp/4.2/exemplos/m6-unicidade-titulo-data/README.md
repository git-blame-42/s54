# M6. Unicidade (título único na mesma data)

Pedido, como o professor faria na hora: "Não pode haver dois Eventos
com o mesmo título na mesma data. Use a validação do framework e
mostre o erro no formulário."

Chance: média. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M6.

Branch no clone: `fvp/m6-unicidade-titulo-data`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                             |
|-----------|------------------------------------------------------|
| `c7a5b74` | `feat: Require unique event title on the same date`  |

## O que mudou

- `app/Models/Event.php`, fim de `validates()`:

```php
// Título único na mesma data. Só confere se os dois campos já estão
// válidos: cada campo guarda uma mensagem só, e a última vence.
if ($this->errors('title') === null && $this->errors('starts_on') === null) {
    Validations::uniqueness(['title', 'starts_on'], $this);
}
```

- `lib/Validations.php` sem mudança: `uniqueness($fields, $object)` já
  aceita um campo ou um array de campos (monta `title = :title AND
  starts_on = :starts_on`) e, na edição, ignora o próprio registro
  quando os campos não mudaram.
- `tests/Unit/Models/Events/EventTest.php`: 6 testes (mesmo título e
  data inválido; outra data válido; maiúsculas e acentos contam como
  iguais; editar sem mudar título e data funciona; editar para o
  título e data de outro Evento falha e não grava; data inválida não
  é trocada pela mensagem de unicidade).
- `tests/Integration/Controllers/Admin/EventsControllerTest.php`:
  create duplicado volta ao form com o erro sob o título; update sem
  alteração continua redirecionando para o show com o flash.
- Cest novo `tests/Acceptance/admin/events/CreateDuplicateEventCest.php`:
  "Evento 01" na mesma data é recusado; na data do "Evento 02" salva.

## Por que assim

- Reaproveita `Validations::uniqueness`, a mesma do e-mail em `User`
  (padrão do framework; nada de SQL no controller).
- O `if` com `errors(...) === null` existe porque `addError` guarda uma
  mensagem por campo e a última vence. Sem ele, um título repetido
  com data no passado trocaria "não pode estar no passado!" por "já
  existe um registro com esse dado". E com título vazio não faz
  sentido consultar o banco.
- Fica no fim de `validates()`: depende do resultado das outras
  validações de `title` e `starts_on` (inclusive do `maxLength` do M2,
  se aplicado).
- Mensagem: a de `uniqueness` ("já existe um registro com esse dado",
  sem `!`), marcada no título e na data. Ela é compartilhada com o
  e-mail do `User`. Se o professor pedir outra, acrescente um
  parâmetro opcional em vez de mudar o texto fixo:

```php
public static function uniqueness(
    $fields,
    $object,
    string $message = 'já existe um registro com esse dado'
) {
    // ... no lugar do texto fixo:
    $object->addError($field, $message);
}

// Event::validates()
Validations::uniqueness(
    ['title', 'starts_on'],
    $this,
    'já existe um Evento com esse título nessa data!'
);
```

- Só o título ("não pode haver dois com o mesmo título"): troque o
  array por `'title'`. O populate e o `BaseEventsCest` já têm títulos
  únicos. Nos testes, confira quem cria mais de um Evento com o mesmo
  título (`grep -n "createEvent(" tests/`): com só `'title'`, mesmo
  título em datas diferentes também passa a falhar.
- Não há índice `UNIQUE` no schema: a regra é do model, como pedido.
  Se pedirem também no banco, `UNIQUE KEY uq_events_title_date
  (title, starts_on)` em `schema.sql` e trate a `PDOException` como o
  `safeDestroy()` faz.

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

Resultado obtido: Unit 165 testes, Integration 47, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou;
`./run db:populate` continua criando 16 Eventos e 14 Inscrições.

Testes novos:

- `EventTest`:
  - `test_same_title_on_the_same_date_should_be_invalid`
  - `test_same_title_on_another_date_should_be_valid`
  - `test_title_uniqueness_ignores_case_and_accents`
  - `test_event_can_be_updated_keeping_its_own_title_and_date`
  - `test_update_to_title_and_date_of_another_event_should_be_invalid`
  - `test_uniqueness_should_not_replace_a_more_specific_starts_on_error`
- `EventsControllerTest`:
  - `test_create_with_same_title_and_date_renders_form_with_error`
  - `test_update_without_changes_keeps_working_with_uniqueness`
- `CreateDuplicateEventCest`:
  - `createWithSameTitleAndDate`
  - `createWithSameTitleOnAnotherDate`

No navegador: copie o título e a data de um Evento da listagem para
"Novo Evento" e salve. O form volta com "já existe um registro com
esse dado" sob o título e sob a data, com os valores preenchidos.

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`): também mexe no título em
  `validates()`. Com os dois, o `if` daqui já cobre o `maxLength`
  (título longo demais não chega a consultar o banco).
- M5 (`m5-validacao-condicional-modalidade`): linhas vizinhas de
  `validates()`; junte as chamadas.
- S4 (`s4-testes-de-borda`): o item "duplicidade" do S4 só faz
  sentido com este cenário aplicado.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m6-unicidade-titulo-data`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m6-unicidade-titulo-data
cd /caminho/do/seu/repositorio
git remote add fvp-m6 \
    s50/fvp/4.2/exemplos/m6-unicidade-titulo-data/projeto
git fetch fvp-m6 fvp/m6-unicidade-titulo-data
git cherry-pick c7a5b74
git remote remove fvp-m6
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/m6-unicidade-titulo-data/solucao.bundle \
    fvp/m6-unicidade-titulo-data:fvp-m6
git cherry-pick c7a5b74
# opcional, limpa a ref
git branch -D fvp-m6
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/m6-unicidade-titulo-data
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

- `app/Models/Event.php`, fim de `validates()`: M1, M2, M3, M4 e M5 mexem
  no mesmo método. Mantenha o bloco de unicidade por último.
- `EventTest.php` e `EventsControllerTest.php`: testes novos no mesmo
  ponto; fique com os dois.

## Armadilhas

- A comparação é do MySQL (collation `utf8mb4_0900_ai_ci`): "SEMANA
  ACADEMICA" conta como igual a "Semana Acadêmica". O teste
  `test_title_uniqueness_ignores_case_and_accents` documenta isso. Se
  o professor quiser diferenciar, é collation `_as_cs` na coluna.
- `uniqueness` só ignora o próprio registro quando título **e** data
  não mudaram. Editar só a capacidade de um Evento continua
  funcionando (teste `..._keeping_its_own_title_and_date`).
- A mensagem não tem `!` e aparece nos dois campos da lista; um teste
  com `assertCount(1, $this->errorsOf($event))` falha por isso.
- Fixtures que criam dois Eventos com `createEvent()` sem `title`
  passam a falhar (mesmo título padrão e mesma data). Na base não há;
  ao escrever teste novo, passe títulos diferentes.
- `Model::update([...])` não valida: o `update` direto (usado em
  `createPastEvent`) ignora a unicidade. Só `save()` confere.
