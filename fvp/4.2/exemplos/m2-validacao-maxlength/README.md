# M2. Validação nova em `lib/Validations.php` (`maxLength`)

Pedido, como o professor faria na hora: "O título do Evento não pode
passar de 100 caracteres. Crie a validação no `Validations` do
framework, use no model e escreva os testes."

Chance: alta. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M2.

Branch no clone: `fvp/m2-validacao-maxlength`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `5b408d1` | `feat: Add maxLength validation and limit event title...` |

Mensagem completa: `feat: Add maxLength validation and limit event
title to 100 characters`.

## O que mudou

- `lib/Validations.php`: método novo
  `maxLength($attribute, $obj, int $max)`, no mesmo formato de
  `integerMin`. Lê `$obj->$attribute`; se for escalar e
  `mb_strlen((string) $value) > $max`, registra
  "deve ter no máximo {$max} caracteres!" e devolve `false`. Senão
  devolve `true`.
- `app/Models/Event.php`, `validates()`: o título passa a ser
  `notEmpty('title') && maxLength('title', $this, 100)`.
- `tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php`: coluna
  `name` no model anônimo e três testes (aceita até o máximo, rejeita
  acima, conta caracteres e não bytes).
- `tests/Unit/Models/Events/EventTest.php`: título com 100 caracteres
  é válido; com 101 é inválido, só `title` tem erro e nada é salvo.
- `tests/Integration/Controllers/Admin/EventsControllerTest.php`:
  create com título de 101 caracteres volta ao form "Novo Evento" com
  o erro sob o campo e o valor digitado preenchido.
- View, controller e schema sem mudança.

## Por que assim

- Regra de validação mora no model, com função do `lib/Validations.php`
  (padrão cobrado; validar no controller é recusado).
- `mb_strlen` e não `strlen`: `strlen('ção')` é 5 (bytes UTF-8);
  `mb_strlen` é 3. Com `strlen`, um título com acentos seria barrado
  antes dos 100 caracteres.
- `&&` depois de `notEmpty`: título vazio mostra só "não pode ser
  vazio!", nunca duas mensagens. É o mesmo encadeamento de `kind`,
  `modality` e `starts_on` no `validates()`.
- `is_scalar`: array (`event[title][]=x`) não estoura `Array to string
  conversion` (que o `ErrorsHandler` transforma em 500).
- Só `maxLength` foi implementada: é o que o pedido exige. `minLength`
  e `integerMax` ficam como receita abaixo (5 min cada), para não
  deixar código sem uso no framework.
- 100 fica literal no `validates()`, como o 1 de `integerMin`. O
  `VARCHAR(255)` do schema continua; a regra de negócio é mais estrita.

### Receita: `minLength` ("descrição com no mínimo 10")

```php
// lib/Validations.php
public static function minLength($attribute, $obj, int $min)
{
    $value = $obj->$attribute;

    if (!is_scalar($value) || mb_strlen((string) $value) < $min) {
        $obj->addError($attribute, "deve ter no mínimo {$min} caracteres!");
        return false;
    }

    return true;
}

// app/Models/Event.php, validates()
Validations::notEmpty('description', $this) &&
    Validations::minLength('description', $this, 10);
```

Teste: copie `test_max_length_should_reject_texts_above_the_maximum`
com `str_repeat('a', 9)`; no `EventTest`, descrição de 9 caracteres.
Cuidado: o `params()` do `EventTest` usa "Palestras e minicursos."
(23), e outros testes de outras classes usam descrições curtas; confira
com `grep -rn "'description' =>" tests/` se algum fica abaixo de 10.

### Receita: `integerMax` ("vagas no máximo 500")

```php
// lib/Validations.php (cópia de integerMin, com > no lugar de <)
public static function integerMax($attribute, $obj, int $max)
{
    $value = $obj->$attribute;
    $number = is_int($value) || is_string($value)
        ? filter_var($value, FILTER_VALIDATE_INT)
        : false;

    if ($number === false) {
        $obj->addError($attribute, 'deve ser um número inteiro!');
        return false;
    }

    if ($number > $max) {
        $obj->addError($attribute, "deve ser menor ou igual a {$max}!");
        return false;
    }

    return true;
}

// app/Models/Event.php, validates(): dentro do if da capacidade
if (
    Validations::notEmpty('capacity', $this) &&
    Validations::integerMin('capacity', $this, 1) &&
    Validations::integerMax('capacity', $this, 500)
) {
    $this->validatesCapacityAgainstEnrollments();
}
```

Teste: 500 válido, 501 inválido com "deve ser menor ou igual a 500!".
O populate não passa de 500 vagas; confira se mudar o limite.

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

Resultado obtido: Unit 164 testes, Integration 46, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `NumberAndDateValidationsTest`:
  - `test_max_length_should_accept_texts_up_to_the_maximum`
  - `test_max_length_should_reject_texts_above_the_maximum`
  - `test_max_length_should_count_characters_not_bytes`
- `EventTest`:
  - `test_title_with_100_characters_should_be_valid`
  - `test_title_with_more_than_100_characters_should_be_invalid`
- `EventsControllerTest`:
  - `test_create_with_title_above_100_characters_renders_form_with_error`

No navegador: Admin, "Novo Evento", título com 101 letras, Salvar. O
form volta com "deve ter no máximo 100 caracteres!" sob o título e os
outros campos preenchidos.

## Relação com outros cenários

- M5 (`m5-validacao-condicional-modalidade`) e M6
  (`m6-unicidade-titulo-data`) também mexem em `Event::validates()`,
  nas linhas vizinhas. Ao juntar, mantenha todas as chamadas.
- M6 encadeia `uniqueness` no título; a ordem natural é
  `notEmpty && maxLength`, e a unicidade em linha separada.
- S4 (`s4-testes-de-borda`) cobre os outros campos; o título longo
  fica neste cenário.
- T2 (`t2-phpcs-phpstan-verdes`): `lib/` não passa no phpstan, então
  erro de tipo no `maxLength` só aparece nos testes.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m2-validacao-maxlength`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m2-validacao-maxlength
cd /caminho/do/seu/repositorio
git remote add fvp-m2 \
    s50/fvp/4.2/exemplos/m2-validacao-maxlength/projeto
git fetch fvp-m2 fvp/m2-validacao-maxlength
git cherry-pick 5b408d1
git remote remove fvp-m2
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/m2-validacao-maxlength/solucao.bundle \
    fvp/m2-validacao-maxlength:fvp-m2
git cherry-pick 5b408d1
# opcional, limpa a ref
git branch -D fvp-m2
```

### 3. A partir do `solucao.patch`

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m2-validacao-maxlength/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m2-validacao-maxlength/solucao.patch
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

- `app/Models/Event.php`, `validates()`: M1, M3, M4, M5 e M6 mexem no
  mesmo bloco. Fique com as linhas dos dois lados.
- `lib/Validations.php`: outra validação nova no mesmo ponto (antes
  de `validDate`). Mantenha os dois métodos.
- Arquivos de teste: testes novos no mesmo ponto; fique com os dois.
- O model anônimo do `NumberAndDateValidationsTest`: se outro cenário
  também acrescentou coluna, junte as colunas na mesma lista.

## Armadilhas

- `strlen` conta bytes; use `mb_strlen`.
- Sem a coluna `name` no `$columns` do model anônimo,
  `$this->model->name = ...` lança `Property name not found`.
- Mantenha o `// @phpstan-ignore-line` nas atribuições do model
  anônimo: o phpstan não conhece a propriedade mágica.
- Mensagem em pt-BR e terminada em `!`, como as outras.
- Sem o `&&`, título vazio continua com uma só mensagem (o
  `maxLength` aceita `''`), mas o padrão do model é encadear.
- O `errorsOf()` do `EventTest` é privado e lista os campos à mão; ele
  serve para garantir que só `title` tem erro.
- `lib/` não entra no phpstan: tipe `int $max` mesmo assim.
