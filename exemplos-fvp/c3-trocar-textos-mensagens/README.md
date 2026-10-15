# C3. Trocar textos de mensagens

Pedido, como o professor faria na hora: "Troquem três mensagens: a de
login inválido, a de login com sucesso e a de campo vazio".

Chance: alta (trivial). Tempo estimado: 5 min por mensagem. Catálogo:
`fvp-possiveis-modificacoes.md`, seção C3.

Branch no clone: `fvp/c3-trocar-textos-mensagens`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `0ef5708` | `chore: Update login and validation messages` |

## O que mudou

Três textos, cada um com o teste que o confere:

| Antes | Depois | Onde mora | Teste que checa |
|-------|--------|-----------|-----------------|
| `E-mail ou senha inválidos!` | `Credenciais inválidas. Confira e-mail e senha.` | `app/Controllers/AuthenticationsController.php` | `LoginCest::loginWithInvalidCredentials` |
| `Login realizado com sucesso!` | `Bem-vindo(a) de volta!` | `app/Controllers/AuthenticationsController.php` | `LoginCest::loginWithValidCredentials` |
| `não pode ser vazio!` | `é obrigatório!` | `lib/Validations.php` (`notEmpty`) | `UserTest::test_errors_should_return_errors` |

- `app/Controllers/AuthenticationsController.php`: dois `FlashMessage`
  em `authenticate()`.
- `lib/Validations.php`: mensagem de `notEmpty`.
- `tests/Acceptance/authentications/LoginCest.php`: dois `see(...)`.
- `tests/Unit/Models/Users/UserTest.php`: dois `assertEquals`.

Resultado: 4 arquivos, +7 / -7 linhas. Dois arquivos de teste tiveram
asserts alterados; nenhum teste novo.

## Por que assim

- O método é o mesmo para qualquer texto: achar todas as ocorrências
  com `grep`, trocar código e teste juntos, rodar o teste do arquivo e
  depois a suíte inteira. Texto mudado sem teste atualizado é CI
  vermelho na hora.
- Mensagens de interface em pt-BR; nenhum identificador mudou.
- Flash continua sendo gravado pelo controller via `lib/FlashMessage.php`
  (rubrica 4.1: `FlashMessage`), e a validação continua em
  `lib/Validations.php` (rubrica 4.2: `Validations`). Só o texto muda.
- Commit em inglês no formato `type: Title`; `chore` porque não há
  funcionalidade nova nem correção.

Os `grep` usados, um por mensagem (rode antes de cada troca):

```
grep -rn 'E-mail ou senha inválidos!' app lib tests config
grep -rn 'Login realizado com sucesso!' app lib tests config
grep -rn 'não pode ser vazio!' app lib tests config
```

Cada um devolve o ponto no código e o(s) teste(s) que conferem a
string. O `ValidationsTest` não checa o texto de `notEmpty` (só o
retorno booleano), por isso não mudou. "Logout realizado com sucesso!"
aparece no `grep` da segunda mensagem se você buscar só por
`realizado com sucesso`; ficou como está porque não foi pedido.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit/Models/Users/UserTest.php
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 71 testes, Integration 9, Acceptance 9 (iguais à
base; só asserts mudaram).

No navegador: `http://localhost`. Em `/login`, senha errada mostra
"Credenciais inválidas. Confira e-mail e senha."; login correto com
`user@example.com` / `123456` mostra "Bem-vindo(a) de volta!". A
mensagem "é obrigatório!" só aparece em formulário que renderiza
`$user->errors(...)`; na base 4.1 não há esse formulário, então ela é
vista pelo `UserTest` (ou pelo cadastro do cenário B7).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/c3-trocar-textos-mensagens` (relativo ao clone do
s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c3 <caminho>/projeto
git fetch fvp-c3 fvp/c3-trocar-textos-mensagens
git cherry-pick 0ef5708
git remote remove fvp-c3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/c3-trocar-textos-mensagens:fvp-c3
git cherry-pick 0ef5708
git branch -D fvp-c3           # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito e a árvore
fica idêntica à do bundle. O cherry-pick cria um commit novo; o SHA do
seu repositório pode ser outro.

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

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Controllers/AuthenticationsController.php`: A4 (redireciona
  admin para `/admin`) e B5 (usuário inativo) mexem nas mesmas linhas
  de `authenticate()`. Resolva mantendo a lógica do alvo e só trocando
  o texto do `FlashMessage`.
- `lib/Validations.php`: B1 (`minLength`) e B2 (`email`) acrescentam
  métodos perto de `notEmpty`. Conflito de contexto; fique com os dois
  lados.
- `tests/Acceptance/authentications/LoginCest.php`: A3, A5 e C2
  acrescentam ou alteram cenários nesse arquivo. Mantenha os testes do
  alvo e troque só as strings.
- `tests/Unit/Models/Users/UserTest.php`: B1, B2, B3, B5 acrescentam
  testes. Fique com os dois lados; se um teste novo do alvo esperar
  "não pode ser vazio!", troque também.
- A1 e A8 não conflitam (mexem só nos middlewares), mas mudam outras
  mensagens; se o professor pedir para trocar as de middleware, o
  método é o mesmo (`grep` em `app/Middleware` e nos Cest).

Depois de aplicar: rode `./run phpcs` e os testes de novo. Não há
mudança de schema.

## Armadilhas

- Trocar a string no código e esquecer o teste: `LoginCest` e
  `UserTest` quebram na hora. O `grep` acima mostra os dois lados.
- `grep` com `--include=*.php` não funciona em zsh sem aspas (o `*`
  vira glob); use `grep -rn 'texto' app lib tests config` como acima.
- Acentos: `grep` no Linux/macOS compara bytes UTF-8; copie a string
  exatamente como está no arquivo (inclusive `!` e `.`).
- Testes que conferem strings na base 4.1 (seção 4 do catálogo):
  `LoginCest`, `DashboardCest`, `AdminDashboardCest`, `UserTest`,
  `AuthenticationsControllerTest` (`<h1>Login</h1>`, `Entrar`),
  `DashboardAccessTest` (`Login` no corpo). Mudou um texto que aparece
  neles, mude o assert.
- O populate não imprime nenhuma dessas mensagens; não precisa de
  `db:reset`.
