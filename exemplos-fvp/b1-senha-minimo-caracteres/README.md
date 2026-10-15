# B1. Senha com no mínimo 6 caracteres

Pedido, como o professor faria na hora: "Não deixe cadastrar usuário
com senha menor que 6 caracteres; mostre uma mensagem de validação."

Chance: alta. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B1.

Branch no clone: `fvp/b1-senha-minimo-caracteres`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `19021f1` | `feat: Validate minimum password length` |

## O que mudou

- `lib/Validations.php`: novo método
  `minLength(string $attribute, int $min, $obj): bool`. Ignora valor nulo (vazio
  é assunto de `notEmpty`); se `mb_strlen` for menor que `$min`, grava
  `deve ter pelo menos {$min} caracteres!` em `errors[$attribute]` e devolve
  `false`.
- `app/Models/User.php`: em `validates()`, dentro do `if ($this->newRecord())`,
  chama `Validations::minLength('password', 6, $this)` antes de
  `passwordConfirmation`.
- `tests/Unit/Lib/Validations/ValidationsTest.php`: três testes novos
  (curta rejeita com a mensagem; 6 e 7 caracteres aceitam; nulo é ignorado).
- `tests/Unit/Models/Users/UserTest.php`:
  `test_errors_should_return_short_password_error` (senha `123` com
  confirmação igual; `isValid()` falso e mensagem certa em
  `errors('password')`).

## Por que assim

- A regra fica em `lib/Validations.php`, ao lado de `notEmpty`,
  `uniqueness` e `passwordConfirmation`, que é onde a rubrica da 4.2
  procura validações (`grading-path.md`, "Framework: `lib/Validations.php`").
- O model chama a validação; o controller não muda (controller fino,
  lógica no model).
- Só valida em `newRecord()`, como `passwordConfirmation`: em usuário
  existente, `password` não altera o hash (ver `User::__set`), então
  validar lá seria ruído.
- Mensagem em pt-BR, no mesmo tom das existentes (`não pode ser vazio!`).
- Identificadores em inglês, tipos declarados e docblock para o PHPStan
  nível 6, PSR-12 limpo.
- Teste em cada camada que a mudança toca: `Validations` isolada e
  `User` integrada. Nomes de teste descrevem o comportamento (feedback
  2025-2).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 75 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos:

- `ValidationsTest::test_min_length_should_reject_short_value`
- `ValidationsTest::test_min_length_should_accept_value_with_min_or_more_chars`
- `ValidationsTest::test_min_length_should_ignore_null_value`
- `UserTest::test_errors_should_return_short_password_error`

Não há tela de cadastro nesta entrega, então não há caminho no
navegador para mostrar; demonstre pelo console:
`./run php:console` e

```php
require 'config/bootstrap.php';
$u = new App\Models\User(['name' => 'X', 'email' => 'x@example.com',
    'password' => '123', 'password_confirmation' => '123']);
var_dump($u->isValid(), $u->errors('password'));
```

O populate continua funcionando (senha `123456` tem 6 caracteres).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/b1-senha-minimo-caracteres`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b1 <caminho>/projeto
git fetch fvp-b1 fvp/b1-senha-minimo-caracteres
git cherry-pick 19021f1
git remote remove fvp-b1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b1-senha-minimo-caracteres:fvp-b1
git cherry-pick 19021f1          # mesmo SHA; o bundle preserva o objeto
git branch -D fvp-b1             # opcional, limpa a ref
```

Testado: clone limpo do público + `git fetch` do bundle +
`git cherry-pick 19021f1` aplica sem conflito (4 arquivos, 68 linhas).

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Models/User.php`, bloco `validates()`: qualquer outro cenário
  que acrescente validação ali (B2 e-mail, B5 ativo). Resolva mantendo
  as duas chamadas; a ordem decide qual mensagem fica em `password`.
- `lib/Validations.php`: B2 adiciona `email()` logo acima de
  `passwordConfirmation`. Mantenha os dois métodos.
- `tests/Unit/Models/Users/UserTest.php` e
  `tests/Unit/Lib/Validations/ValidationsTest.php`: testes novos
  inseridos no mesmo ponto por outro cenário. Mantenha todos.

Depois de aplicar: `./run phpcs`, `./run phpstan`, `./run test tests/Unit`.

## Armadilhas

- `passwordConfirmation` também escreve em `errors['password']`; a
  última regra que falha sobrescreve. Com `minLength` antes, uma senha
  curta e diferente da confirmação mostra "as senhas devem ser idênticas!".
  Se o professor quiser a mensagem de tamanho nessa situação, inverta a
  ordem das chamadas.
- `password` é propriedade protegida de `User`; o `__get` do Model
  devolve o valor e o `phpstan.neon` já ignora o aviso de acesso.
- Em testes com classe anônima estendendo `Model`, declare a coluna em
  `$columns` e marque a primeira atribuição com `// @phpstan-ignore-line`;
  marcar a segunda gera erro "No error to ignore" no PHPStan.
- Use `mb_strlen`, não `strlen`: "çãoé" tem 4 caracteres, não 8 bytes.
