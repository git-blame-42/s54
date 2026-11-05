# T2. `phpcs`, `phpstan` e CI verdes

Pedido, como o professor faria na hora: nenhum pedido explícito. Ele
abre a aba Actions da PR e vê se o job "Code Quality Check" passou.
Vale para toda modificação da prova.

Chance: alta (a CI é item da rubrica da PR). Tempo estimado: 5 min.
Catálogo: `s50/fvp/4.2/catalogo.md`, T2.

Cenário só de README: não há branch nem commit. Os erros abaixo foram
reproduzidos de verdade num clone de `main` (`f31006b`), com as
mensagens copiadas da saída, e depois descartados (`git checkout .`).

## O que a CI confere

Job "Code Quality Check" de `.github/workflows/ci.yml`, nesta ordem:

```
./run composer dump-autoload --optimize --strict-psr
./run codecept build
./run phpcs
./run phpstan
```

- `phpcs.xml`: PSR-12, linha até 130 colunas (`lineLimit`), nomes de
  método em snake_case liberados só em `*Test.php`, `_before` só em
  `*Cest.php`. `database/` fica de fora.
- `phpstan.neon`: nível 6, `paths` `app`, `core` e `tests`. `lib/`
  (onde está o `Validations.php`) **não** é analisado.
- PSR-4 (`composer.json`): `App\` = `app/`, `Core\` = `core/`,
  `Tests\` = `tests/`, `Database\` = `database/`, `Lib\` = `lib/`,
  `Config\` = `config/`. Classe nova em qualquer um deles segue a
  regra do item 6.

Os três falham a CI com código de saída 1, inclusive o `phpcs` com só
um WARNING (conferido: `./run phpcs` com uma linha longa e nada mais
sai com 1).

## Receita (antes de cada commit)

```
# 1. arruma o que é automático (espaços, chaves, linhas em branco)
./run phpcbf
# 2. o que sobrar é manual (linha longa, nomes)
./run phpcs
# 3. tipos (só app, core e tests)
./run phpstan
# 4. só se criou classe nova
./run composer dump-autoload -o --strict-psr
# 5. testes
./run test tests/Unit
./run test tests/Integration
# 6. só se mexeu em view ou fluxo (precisa do selenium)
docker compose up -d selenium && sleep 5
./run test:browser
```

## Erros típicos reproduzidos, com a correção

### 1. Linha acima de 130 colunas (phpcs, manual)

Provocado acrescentando uma terceira condição no `if` da capacidade em
`Event::validates()`:

```
FILE: /var/www/app/Models/Event.php
FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE
 79 | WARNING | Line exceeds 130 characters; contains 139 characters
    |         | (Generic.Files.LineLength.TooLong)
```

O `phpcbf` não resolve (não aparece com `[x]`). Quebre a condição, um
operando por linha, como o PSR-12 pede:

```php
if (
    Validations::notEmpty('capacity', $this) &&
    Validations::integerMin('capacity', $this, 1) &&
    $this->capacity !== 'ilimitado'
) {
```

Chamadas longas: um argumento por linha, ou guarde a lista numa
variável (`$fields = [...]; foreach ($fields as $field)`).

### 2. Espaçamento PSR-12 (phpcs, automático)

Provocado com `if(count($conditions)==0){`:

```
 124 | ERROR | [x] Expected 1 space after IF keyword; 0 found
     |       |     (Squiz.ControlStructures.ControlSignature
     |       |     .SpaceAfterKeyword)
 124 | ERROR | [x] Expected at least 1 space before "=="; 0 found
     |       |     (PSR12.Operators.OperatorSpacing.NoSpaceBefore)
 124 | ERROR | [x] Expected at least 1 space after "=="; 0 found
     |       |     (PSR12.Operators.OperatorSpacing.NoSpaceAfter)
 124 | ERROR | [x] Expected 1 space after closing parenthesis;
     |       |     found 0 (Squiz.ControlStructures.ControlSignature
     |       |     .SpaceAfterCloseParenthesis)
PHPCBF CAN FIX THE 4 MARKED SNIFF VIOLATIONS AUTOMATICALLY
```

(Nomes de sniff quebrados aqui para caber em 80 colunas.) Correção:

```
./run phpcbf
# FILE                             FIXED  REMAINING
# /var/www/app/Models/Event.php    4      1
```

Vira `if (count($conditions) == 0) {`. O "REMAINING 1" era a linha
longa do item 1. Rode `git diff` depois do `phpcbf`: ele mexe em todos
os arquivos do projeto que estiverem fora do padrão.

### 3. `array` sem tipo do valor (phpstan, `missingType.iterableValue`)

Provocado com um método novo no model sem PHPDoc:

```php
public static function upcoming(array $conditions): array
```

```
 Line   app/Models/Event.php
 122    Method App\Models\Event::upcoming() has parameter $conditions
        with no value type specified in iterable type array.
        🪪  missingType.iterableValue
 122    Method App\Models\Event::upcoming() return type has no value
        type specified in iterable type array.
        🪪  missingType.iterableValue
```

Correção: PHPDoc com o tipo dos itens, no padrão do `Model.php`:

```php
/**
 * @param array<string, mixed> $conditions
 * @return array<static>
 */
public static function upcoming(array $conditions): array
```

Outros formatos que o projeto já usa: `array<int, string>` (lista),
`array<string, array{string, string}>` (data provider de teste),
`array<int, Event>` (lista de Eventos fora de método estático).

### 4. `?Model` onde se espera `?Event` (phpstan, `return.type`)

Provocado em `User` com o `HasMany::findById`, que devolve `?Model`:

```php
public function findEvent(int $id): ?Event
{
    return $this->events()->findById($id);
}
```

```
 Line   app/Models/User.php
 96     Method App\Models\User::findEvent() should return
        App\Models\Event|null but returns
        Core\Database\ActiveRecord\Model|null.
        🪪  return.type
```

Correção: converter com `instanceof` (não com `@var` nem cast):

```php
$event = $this->events()->findById($id);

return $event instanceof Event ? $event : null;
```

`Event::findById` e `Event::findBy` não têm esse problema: devolvem
`static|null`. O erro aparece com `HasMany`/`BelongsTo`, que só
conhecem `Model`.

### 5. Propriedade mágica no model anônimo de teste (phpstan)

Provocado tirando o `// @phpstan-ignore-line` de uma atribuição em
`tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php`:

```
 Line   tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php
 23     Access to an undefined property
        Core\Database\ActiveRecord\Model::$quantity.
        🪪  property.notFound
```

Correção: no model anônimo de teste, mantenha o comentário na linha da
atribuição (padrão de todo o arquivo):

```php
$this->model->quantity = $value; // @phpstan-ignore-line
```

Em model de verdade (`app/Models`), declare a coluna no PHPDoc da
classe em vez de ignorar: `@property string|null $link`.

### 6. Classe fora do PSR-4 (composer `--strict-psr`)

Provocado com `app/Services/EventStats.php` e `namespace
App\Service;` (falta o "s"):

```
Class App\Service\EventStats located in
./app/Services/EventStats.php does not comply with psr-4
autoloading standard. Skipping.
```

Sai com código 1 e o passo "PSR-4 Checker" da CI fica vermelho.
Correção: namespace igual ao caminho (`App\Services` em
`app/Services/`), nome da classe igual ao do arquivo, e rode de novo
`./run composer dump-autoload -o --strict-psr`. Sem o `dump-autoload`
a classe nova pode nem ser encontrada (`Class ... not found`).

## Por que assim

- `phpcbf` primeiro: resolve sozinho a maior parte (espaços, chaves,
  linhas em branco) e deixa só o que exige decisão.
- PHPDoc com tipo e `instanceof` em vez de `ignoreErrors` no
  `phpstan.neon` ou `@phpstan-ignore-line` em código de `app/`: o
  ignore amplo é penalizado (issue do grupo 6). A única exceção
  aceita no projeto é o model anônimo de teste.
- `--strict-psr` localmente porque a CI roda exatamente esse comando.

## Como verificar

Depois de uma modificação, os quatro comandos limpos:

```
./run phpcs
# ... "Time: ...; Memory: ..." sem nenhum FILE listado
./run phpstan
#  [OK] No errors
./run composer dump-autoload -o --strict-psr
# Generated optimized autoload files containing ... classes
# (nenhuma linha "does not comply")
```

Conferido no clone: com os erros provocados, `phpcs` saiu com 4
ERRORS e 1 WARNING, `phpstan` com `[ERROR] Found 3 errors` (itens 3 e
5; o `return.type` do item 4 entrou numa segunda execução) e o composer
com o aviso de PSR-4 e código de saída 1. Depois das
correções: `phpcs` limpo, `[OK] No errors` e nenhum aviso de PSR-4.
Unit 159 testes na base, sem mudança.

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`): `Validations.php` fica em `lib/`, fora
  do phpstan; tipe `int $max` mesmo assim e confie nos testes.
- M5 (`m5-validacao-condicional-modalidade`): a lista de campos do
  `errorsOf()` no `EventTest` passou de 130 colunas com `link`; virou
  `$fields = [...]` (item 1).
- S4: data providers precisam de
  `@return array<string, array{...}>` (item 3).
- Todo cenário que cria classe (service, controller novo): item 6.

## Como usar esta receita

1. Termine a modificação e rode a receita acima, na ordem.
2. Para cada linha da saída, procure a mensagem nos itens 1 a 6 (o
   identificador, como `missingType.iterableValue` ou
   `Generic.Files.LineLength.TooLong`, é o que muda menos).
3. Corrija, rode o comando de novo até ficar limpo. Rode os testes;
   `./run test:browser` também, se mexeu em view ou fluxo.
4. Faça o commit na branch com o nome pedido pelo professor
   (`type: Title`, em inglês).
5. Na PR, confira na aba Actions que os jobs "Tests", "Acceptance
   Tests" e "Code Quality Check" ficaram verdes.

## Armadilhas

- WARNING do `phpcs` também reprova: a saída não diz "ERROR", mas o
  código de saída é 1.
- `lib/` fora do phpstan: erro de tipo em `Validations.php` só aparece
  quando um teste chama o método.
- `./run phpstan` roda `codecept build` antes (gera as classes de
  `tests/Support/_generated`, que o phpstan exclui); por isso demora
  mais que o `phpcs`.
- `phpcbf` sem caminho mexe no projeto inteiro: revise o `git diff`
  antes do commit para não levar mudança de outro arquivo junto.
- `use` sem uso e código comentado não são pegos pelo `phpcs` deste
  projeto; revise à mão.
- Nome de teste em snake_case só é liberado em arquivo `*Test.php`;
  num `*Cest.php`, use camelCase (como nos Cests existentes).
