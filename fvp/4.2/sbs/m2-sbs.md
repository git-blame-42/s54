# M2 passo a passo: validação nova em `lib/Validations.php`

Pedido típico: "o título do Evento não pode passar de 100 caracteres;
crie a validação no `Validations` do framework, use no model e teste"
(`maxLength`). Chance: alta. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m2-validacao-maxlength/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: parte da `main`.

Ordem = fluxo da validação: framework (`lib/`) -> model -> testes.
Schema, controller e views não mudam. Na prova, prioridade: funcionar
no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `lib/Validations.php`
2. `app/Models/Event.php`
3. `tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php`
4. `tests/Unit/Models/Events/EventTest.php`
5. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois: suíte, `./run phpcs`. Sem `db:populate` (schema igual).

## 1. `lib/Validations.php`

Método novo logo depois de `integerMin` (linhas ~78-94), antes do
docblock de `validDate`:

```php
        return true;
    }

    /**
     * O texto deve ter no máximo $max caracteres (conta caracteres, não bytes: "ç" vale 1).
     */
    public static function maxLength($attribute, $obj, int $max)
    {
        $value = $obj->$attribute;

        if (is_scalar($value) && mb_strlen((string) $value) > $max) {
            $obj->addError($attribute, "deve ter no máximo {$max} caracteres!");
            return false;
        }

        return true;
    }
```

Por quê:

- `lib/Validations.php` é a caixa de regras reutilizáveis do framework.
  Formato fixo: `estático ($attribute, $obj, ...)`, lê `$obj->$attribute`,
  chama `addError` e devolve `bool`. Copie `integerMin`.
- Devolver `bool` é o que permite encadear com `&&` no model.
- `mb_strlen`, não `strlen`: `strlen('ção')` = 5 bytes; `mb_strlen` = 3.
  Com `strlen`, título acentuado seria barrado antes dos 100.
- `is_scalar`: array atribuído direto ao model não estoura
  `Array to string conversion` (o `ErrorsHandler` vira 500). Pelo form
  (`event[title][]=x`) o array nem chega: o `fill()` do controller já
  troca valor não escalar por `null`.
- Mensagem pt-BR terminada em `!`, como as demais.
- `int $max` tipado: `lib/` fica fora do `phpstan`, mas o `phpcs` roda.

## 2. `app/Models/Event.php`

`validates()`, primeira linha (linha ~62). Trocar:

```php
        Validations::notEmpty('title', $this);
```

por:

```php
        Validations::notEmpty('title', $this) &&
            Validations::maxLength('title', $this, 100);
        Validations::notEmpty('description', $this);
```

Por quê:

- Validação mora no model, nunca no controller (recusado na prova).
  `save()` chama `validates()` e só grava sem erros.
- `&&` = curto-circuito: vazio mostra só "não pode ser vazio!". Mesmo
  encadeamento de `kind`, `modality`, `starts_on` logo abaixo.
- `100` literal, como o `1` de `integerMin`. O `VARCHAR(255)` do schema
  fica; a regra de negócio é mais estrita.
- Controller/view não mudam: o form já mostra `$event->errors('title')`
  via `$feedback('title')`.

## 3. `tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php`

a) `setUp` (linha ~16), model anônimo ganha coluna de texto:

```php
            protected static array $columns = ['quantity', 'day', 'name'];
```

b) Testes novos depois de `test_integer_min_should_reject_non_integers`
(antes de `test_valid_date_should_accept_real_dates`):

```php
    public function test_max_length_should_accept_texts_up_to_the_maximum(): void
    {
        foreach (['', 'abc', str_repeat('a', 10), str_repeat('ç', 10)] as $value) {
            $this->model->name = $value; // @phpstan-ignore-line

            $this->assertTrue(Validations::maxLength('name', $this->model, 10), $value);
        }
        $this->assertFalse($this->model->hasErrors());
    }

    public function test_max_length_should_reject_texts_above_the_maximum(): void
    {
        foreach ([str_repeat('a', 11), str_repeat('ã', 11), 'Semana Acadêmica'] as $value) {
            $this->model->name = $value; // @phpstan-ignore-line

            $this->assertFalse(Validations::maxLength('name', $this->model, 10), $value);
            $this->assertEquals('deve ter no máximo 10 caracteres!', $this->model->errors('name'));
        }
    }

    public function test_max_length_should_count_characters_not_bytes(): void
    {
        // "ção" tem 3 caracteres e 5 bytes: strlen() daria 5 e rejeitaria.
        $this->model->name = 'ção'; // @phpstan-ignore-line

        $this->assertTrue(Validations::maxLength('name', $this->model, 3));
        $this->assertFalse(Validations::maxLength('name', $this->model, 2));
    }
```

Por quê:

- Testa a função do framework isolada, sem banco, num model anônimo.
- Model só aceita atributo listado em `$columns` (`__set`). Sem `name`:
  `Property name not found`.
- `// @phpstan-ignore-line`: propriedade mágica, o `phpstan` não
  conhece. Mantenha como nas linhas de `quantity`.
- Três casos: até o limite, acima, caracteres vs bytes.

## 4. `tests/Unit/Models/Events/EventTest.php`

Depois do método privado `errorsOf()` (linha ~128-138), antes de
`test_kind_should_accept_every_enum_value`:

```php
    public function test_title_with_100_characters_should_be_valid(): void
    {
        $event = new Event($this->params(['title' => str_repeat('é', 100)]));

        $this->assertTrue($event->isValid());
    }

    public function test_title_with_more_than_100_characters_should_be_invalid(): void
    {
        $event = new Event($this->params(['title' => str_repeat('a', 101)]));

        $this->assertFalse($event->isValid());
        $this->assertFalse($event->save());
        $this->assertEquals('deve ter no máximo 100 caracteres!', $event->errors('title'));
        $this->assertCount(1, $this->errorsOf($event));
        $this->assertCount(0, Event::all());
    }
```

Por quê:

- Prova que o model usa a regra: 100 (acentuado) passa, 101 falha.
- `errorsOf()` lista campos à mão; `assertCount(1, ...)` garante que
  só `title` tem erro.
- `Event::all()` vazio: `save()` não gravou.

## 5. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois de `test_create_with_invalid_data_renders_form_with_errors_and_values`
(linha ~114-138), antes de `test_create_without_audience_marks_audience_field`:

```php
    public function test_create_with_title_above_100_characters_renders_form_with_error(): void
    {
        $title = str_repeat('a', 101);

        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['title' => $title])]
        );

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString(
            '<div class="invalid-feedback">deve ter no máximo 100 caracteres!</div>',
            $response
        );
        $this->assertStringContainsString("value=\"{$title}\"", $response);
    }
```

Por quê:

- Caminho completo: POST -> `FIELDS` -> model -> `validates()` falha ->
  controller renderiza `new` de novo (sem redirect) com o erro.
- `value="..."`: o form devolve o que o usuário digitou.

## Fechamento

```sh
./run test tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # Unit + Integration (o PHPUnit não roda os Cests)
docker compose up -d selenium && sleep 5
./run test:browser      # Acceptance
docker compose stop selenium
./run phpcs
./run phpstan
```

No navegador: Admin, "Novo Evento", título com 101 letras, Salvar. Form
volta com "deve ter no máximo 100 caracteres!" sob o título e os outros
campos preenchidos.

Commit (inglês):
`feat: Add maxLength validation and limit event title to 100 characters`.

Variantes (receita no README do exemplo): `minLength` (`<`, mensagem
"deve ter no mínimo {$min} caracteres!"), `integerMax` (cópia de
`integerMin` com `>`, "deve ser menor ou igual a {$max}!"), `format`
com `preg_match('/^[\p{L}\p{N} ]+$/u', $value) !== 1`.

## Armadilhas (resumo)

- `strlen` conta bytes: use `mb_strlen` (acentos).
- `null`: `notEmpty` primeiro, por isso o `&&`.
- Mensagem pt-BR terminada em `!`.
- Validação no controller: recusada (regra de negócio no model).
- Model anônimo do teste sem a coluna `name`: `Property name not found`.
  Mantenha o `// @phpstan-ignore-line`.
- `lib/` fora do `phpstan`: tipe `int $max` mesmo assim.
- `integerMax` tem caso real: `capacity` > 2147483647 (`INT`) passa no
  `integerMin` e estoura no `INSERT` (`PDOException`, 500).
- Caso real na base: título/local > 255 caracteres passa no `notEmpty`
  e estoura o `VARCHAR(255)` (MySQL estrito, 500). `VARCHAR` conta
  caracteres (`mb_strlen`); `description` é `TEXT`, limite em bytes
  (`strlen`).
