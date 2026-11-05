# T2 passo a passo: `phpcs`, `phpstan` e CI verdes

Pedido típico: implícito em todo pedido; o professor abre a aba Actions
da PR e vê se o job "Code Quality Check" passou. Chance: alta (CI é
item da rubrica da PR). Tempo: 5 min.

Exemplo pronto: `fvp/4.2/exemplos/t2-phpcs-phpstan-verdes/` (só
README: sem patch nem commit). Os erros abaixo foram reproduzidos num
clone de `main` e descartados. Pré-requisito: a modificação do cenário
já feita.

Ordem = a da CI: autoload (PSR-4) -> `phpcs` -> `phpstan` -> testes.
Na prova: `phpcbf` primeiro (automático), depois o que sobrar à mão.

## Arquivos, em ordem

Não se editam; leia para saber o que a CI cobra.

1. `.github/workflows/ci.yml`
2. `phpcs.xml`
3. `phpstan.neon`
4. `composer.json` (PSR-4)

Onde as correções caem: no arquivo que o comando apontar (seções 5 a
10, uma por erro típico).

## 1. `.github/workflows/ci.yml`

Job "Code Quality Check", nesta ordem:

```sh
./run composer dump-autoload --optimize --strict-psr
./run codecept build
./run phpcs
./run phpstan
```

Por quê:

- Os três saem com código 1 e deixam o job vermelho, inclusive o
  `phpcs` com só um WARNING.
- Jobs "Tests" (Unit, Integration) e "Acceptance Tests" também têm
  que ficar verdes.

## 2. `phpcs.xml`

- PSR-12, `lineLimit` 130.
- snake_case em nome de método só liberado em `*Test.php`; `_before`
  só em `*Cest.php`.
- `database/` fica fora (populate não passa pelo `phpcs`).

## 3. `phpstan.neon`

- Nível 6, `paths`: `app`, `core`, `tests`.
- `lib/` (onde está `Validations.php`) **não** é analisado: erro de
  tipo lá só aparece em teste.
- `./run phpstan` roda `codecept build` antes (gera
  `tests/Support/_generated`, excluído); por isso demora.

## 4. `composer.json`

PSR-4: `App\` = `app/`, `Core\` = `core/`, `Tests\` = `tests/`,
`Database\` = `database/`, `Lib\` = `lib/`, `Config\` = `config/`.
Classe nova segue a seção 10.

## Receita (antes de cada commit)

```sh
./run phpcbf                                  # automático
./run phpcs                                   # o que sobrou: manual
./run phpstan                                 # tipos
./run composer dump-autoload -o --strict-psr  # só se criou classe
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5      # só se mexeu em view/fluxo
./run test:browser
```

Para cada linha da saída, procure o identificador (ex.
`missingType.iterableValue`, `Generic.Files.LineLength.TooLong`) nas
seções abaixo.

## 5. Linha > 130 colunas (`phpcs`, manual)

Exemplo: terceira condição no `if` da capacidade em
`app/Models/Event.php`, `validates()`:

```sh
 79 | WARNING | Line exceeds 130 characters; contains 139 characters
    |         | (Generic.Files.LineLength.TooLong)
```

Correção: um operando por linha (PSR-12):

```php
if (
    Validations::notEmpty('capacity', $this) &&
    Validations::integerMin('capacity', $this, 1) &&
    $this->capacity !== 'ilimitado'
) {
```

Por quê:

- `phpcbf` não resolve (não aparece com `[x]`).
- Chamada longa: um argumento por linha, ou lista numa variável
  (`$fields = [...]; foreach ($fields as $field)`), como no
  `errorsOf()` do `tests/Unit/Models/Events/EventTest.php` (M5).

## 6. Espaçamento PSR-12 (`phpcs`, automático)

Exemplo: `if(count($conditions)==0){`:

```sh
 124 | ERROR | [x] Expected 1 space after IF keyword; 0 found
 124 | ERROR | [x] Expected at least 1 space before "=="; 0 found
 124 | ERROR | [x] Expected at least 1 space after "=="; 0 found
 124 | ERROR | [x] Expected 1 space after closing parenthesis; found 0
PHPCBF CAN FIX THE 4 MARKED SNIFF VIOLATIONS AUTOMATICALLY
```

Correção:

```sh
./run phpcbf
git diff     # phpcbf sem caminho mexe no projeto inteiro
```

Vira `if (count($conditions) == 0) {`.

Por quê: `[x]` = corrigível sozinho. Revise o `git diff` para não
levar mudança de outro arquivo no commit.

## 7. `array` sem tipo do valor (`phpstan`, `missingType.iterableValue`)

Exemplo: método novo em `app/Models/Event.php` sem PHPDoc:

```php
public static function upcoming(array $conditions): array
```

Correção: PHPDoc no padrão de `core/Database/ActiveRecord/Model.php`:

```php
/**
 * @param array<string, mixed> $conditions
 * @return array<static>
 */
public static function upcoming(array $conditions): array
```

Por quê:

- Nível 6 exige o tipo dos itens de todo `array`.
- Outros formatos já usados: `array<int, string>` (lista),
  `array<string, array{string, string}>` (data provider, S4),
  `array<int, Event>` (lista de Eventos fora de método estático).

## 8. `?Model` onde se espera `?Event` (`phpstan`, `return.type`)

Exemplo em `app/Models/User.php`:

```php
public function findEvent(int $id): ?Event
{
    return $this->events()->findById($id);
}
```

```sh
 96     Method App\Models\User::findEvent() should return
        App\Models\Event|null but returns
        Core\Database\ActiveRecord\Model|null.
```

Correção com `instanceof` (não `@var`, não cast):

```php
$event = $this->events()->findById($id);

return $event instanceof Event ? $event : null;
```

Por quê: `HasMany`/`BelongsTo` só conhecem `Model`. `Event::findById` e
`Event::findBy` devolvem `static|null`, sem esse problema.

## 9. Propriedade mágica desconhecida (`phpstan`, `property.notFound`)

Model de verdade (`app/Models/...`): declare a coluna no docblock da
classe, em vez de ignorar:

```php
 * @property string|null $link
```

Model anônimo de teste
(`tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php`):
mantenha o comentário na linha, padrão do arquivo:

```php
$this->model->quantity = $value; // @phpstan-ignore-line
```

Por quê: atributos são dinâmicos (`__get`/`__set`); o `phpstan` só os
conhece pelo `@property`. Ignore genérico em `app/` é penalizado; a
única exceção aceita é o model anônimo de teste.

## 10. Classe fora do PSR-4 (`composer --strict-psr`)

Exemplo: `app/Services/EventStats.php` com `namespace App\Service;`:

```sh
Class App\Service\EventStats located in
./app/Services/EventStats.php does not comply with psr-4
autoloading standard. Skipping.
```

Correção: namespace = caminho (`App\Services` em `app/Services/`),
classe = nome do arquivo, e de novo:

```sh
./run composer dump-autoload -o --strict-psr
```

Por quê: a CI roda esse comando; sai com 1. Sem `dump-autoload` a
classe nova pode nem ser achada (`Class ... not found`).

## Fechamento

```sh
./run phpcs      # nenhum FILE listado
./run phpstan    # [OK] No errors
./run composer dump-autoload -o --strict-psr   # nenhum "does not comply"
./run test tests/Unit
./run test tests/Integration
./run test:browser   # só se mexeu em view ou fluxo
```

Na PR: aba Actions, jobs "Tests", "Acceptance Tests" e "Code Quality
Check" verdes.

Commit: sem commit próprio; vai junto do cenário, em inglês
`type: Title`, na branch com o nome pedido.

## Armadilhas (resumo)

- WARNING do `phpcs` também reprova (código de saída 1).
- `lib/` fora do `phpstan`: erro de tipo em `Validations.php` só
  aparece quando um teste chama o método.
- `// @phpstan-ignore-line` genérico ou `ignoreErrors` amplo no
  `phpstan.neon`: penalizado (issue do grupo 6).
- Linha > 130 colunas: `phpcs` falha; quebre.
- `phpcbf` sem caminho mexe no projeto todo: revise o `git diff`.
- `use` sem uso e código comentado: `phpcs` não pega; revise à mão.
- snake_case em nome de teste só em `*Test.php`; em `*Cest.php`,
  camelCase.
