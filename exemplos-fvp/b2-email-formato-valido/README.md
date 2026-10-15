# B2. E-mail em formato válido

Pedido, como o professor faria na hora: "O cadastro aceita qualquer
texto no e-mail. Valide o formato do e-mail no model."

Chance: alta. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B2.

Branch no clone: `fvp/b2-email-formato-valido`. `main` continua igual
ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `b558747` | `feat: Validate email format` |

## O que mudou

- `lib/Validations.php`: novo `Validations::email(string $attribute,
  $obj): bool`. Nulo ou vazio devolve `true` (quem acusa vazio é o
  `notEmpty`); senão usa `filter_var(..., FILTER_VALIDATE_EMAIL)` e, se
  falhar, `addError($attribute, 'deve ser um e-mail válido!')`.
- `app/Models/User.php`: `Validations::email('email', $this)` logo
  depois de `Validations::notEmpty('email', $this)` em `validates()`.
- `tests/Unit/Lib/Validations/ValidationsTest.php`: três testes novos
  (`test_email_should_accept_valid_address`,
  `test_email_should_reject_invalid_address`,
  `test_email_should_ignore_empty_value`).
- `tests/Unit/Models/Users/UserTest.php`:
  `test_errors_should_return_invalid_email_error` (usuário com
  `nao-e-email` não é válido, não salva, e `errors('email')` traz a
  mensagem).

Resultado: 4 arquivos, +69 linhas.

## Por que assim

- Validação no model, pela classe `Lib\Validations`, que é o lugar que a
  rubrica da 4.2 cita (`lib/Validations.php`); nada de validar no
  controller nem só com `type="email"` no HTML (o formulário usa
  `novalidate` na rubrica, a validação tem que ser no servidor).
- Vazio retorna `true` de propósito: `notEmpty` já escreveu "não pode
  ser vazio!" em `errors['email']`; se `email()` também escrevesse, a
  última mensagem sobrescreveria a primeira e
  `UserTest::test_errors_should_return_errors` quebraria.
- `filter_var` com `FILTER_VALIDATE_EMAIL` é a função nativa do PHP para
  isso; sem regex própria.
- Assinatura tipada (`string $attribute`, `: bool`) e docblock curto em
  pt-BR para passar no PHPStan nível 6; `$obj` fica sem tipo como nas
  regras existentes, porque recebe qualquer `Model`.
- Testes unitários nas duas camadas: a regra isolada (`ValidationsTest`,
  com classe anônima como as outras) e o model (`UserTest`).
- Mensagem em pt-BR, identificadores em inglês, commit em inglês
  `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 75 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos: os quatro listados acima. Rode só
eles com `./run test tests/Unit/Lib/Validations` e
`./run test tests/Unit/Models`.

No navegador: `http://localhost` não tem formulário de cadastro na 4.1,
então a demonstração é pelo teste ou pelo console:
`./run php:console` e

```php
require 'config/bootstrap.php';
$u = new App\Models\User(['name' => 'X', 'email' => 'nao-e-email',
    'password' => '123456', 'password_confirmation' => '123456']);
var_dump($u->isValid(), $u->errors('email'));
```

Se o professor pedir junto o cadastro (B7), o erro aparece no
`invalid-feedback` do campo e-mail.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/b2-email-formato-valido` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b2 <caminho>/projeto
git fetch fvp-b2 fvp/b2-email-formato-valido
git cherry-pick b558747
git remote remove fvp-b2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b2-email-formato-valido:fvp-b2
git cherry-pick b558747
git branch -D fvp-b2         # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo (mesmo diff e mensagem; o SHA pode
mudar porque a data de commit é outra).

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo sobre `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário:

- `app/Models/User.php`, bloco `validates()`: B1 (`minLength`) e B5
  (`active`) mexem nas linhas vizinhas. Resolução: mantenha todas as
  chamadas, na ordem `notEmpty('name')`, `notEmpty('email')`,
  `email('email')`, `uniqueness('email')`, e dentro do `if
  ($this->newRecord())` as regras de senha.
- `lib/Validations.php`: B1 acrescenta `minLength` no mesmo arquivo.
  Resolução: fique com os dois métodos; a ordem não importa.
- `tests/Unit/Lib/Validations/ValidationsTest.php` e
  `tests/Unit/Models/Users/UserTest.php`: mesmo motivo; fique com todos
  os testes.

Depois de aplicar: rode `./run phpcs`, `./run phpstan` e `./run test
tests/Unit`. Não há mudança de schema.

## Armadilhas

- Sem o `return true` para vazio, a mensagem "deve ser um e-mail
  válido!" sobrescreve "não pode ser vazio!" e
  `test_errors_should_return_errors` quebra.
- `// @phpstan-ignore-line` só na primeira atribuição de propriedade
  dinâmica em cada classe anônima do teste; uma segunda marca na mesma
  propriedade dá "No error to ignore" no PHPStan nível 6 (lição do B1).
- `FILTER_VALIDATE_EMAIL` rejeita espaços, acentos e domínio sem ponto
  (`fulano@localhost` não passa). É o comportamento esperado; não troque
  por regex caseira.
- O `input type="email"` do navegador já barra texto solto, mas o
  professor pode testar com `curl`/DevTools; é por isso que a regra fica
  no servidor.
