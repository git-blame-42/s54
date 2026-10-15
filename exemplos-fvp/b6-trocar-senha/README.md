# B6. Trocar a senha de um usuário existente

Pedido, como o professor faria na hora: "O usuário logado precisa
conseguir trocar a própria senha."

Chance: média. Tempo estimado: 25 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B6.

Branch no clone: `fvp/b6-trocar-senha`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `8fac90f` | `feat: Allow users to change their password` |

## O que mudou

- `app/Models/User.php`: `changePassword(string $password, string
  $confirmation): bool`. Limpa os erros, atribui `password` e
  `password_confirmation`, valida `notEmpty` e `passwordConfirmation`
  e grava `encrypted_password` com `password_hash` via `update()`.
- `config/routes.php`: dentro do grupo `auth`, `GET /password/edit`
  (`passwords.edit`) e `PUT /password` (`passwords.update`).
- `app/Controllers/PasswordsController.php` (novo): `edit()` renderiza o
  formulário; `update(Request)` lê `getParam('user')`, chama
  `changePassword`, flash de sucesso + redirect para `dashboard.index`
  ou flash de erro + re-render com `is-invalid`/`invalid-feedback`.
- `app/views/passwords/edit.phtml` (novo): form `POST` com
  `_method=PUT`, `novalidate`, campos `user[password]` e
  `user[password_confirmation]`.
- `app/views/layouts/_navbar.phtml`: item "Trocar senha" no dropdown do
  usuário, acima de "Sair".
- Testes: `tests/Unit/Models/Users/UserTest.php` (+3),
  `tests/Integration/Controllers/PasswordsControllerTest.php` (novo, 4),
  `tests/Integration/Access/PasswordAccessTest.php` (novo, 2),
  `tests/Acceptance/passwords/ChangePasswordCest.php` (novo, 3).

Resultado: 9 arquivos, +279 linhas.

## Por que assim

- A regra de negócio (hash, validação) fica no model; o controller só
  orquestra: controllers finos, lógica em models (`grading-path.md`,
  rubrica 4.2).
- Parâmetros lidos só por `$request->getParam('user')`, nunca `$_POST`
  (feedback 2025-2).
- Rota `PUT` com nome RESTful (`passwords.update`) e `_method` no form,
  como o framework espera (`core/Http/Request.php` lê `_method`).
- Autorização pelo grupo `auth` em `config/routes.php`, não dentro da
  ação.
- Validação no servidor com `novalidate` no form e erro ao lado do campo
  (rubrica 4.2, "qualidade do formulário").
- Testes nas quatro camadas: unit do model, controller, acesso (Guzzle
  sem sessão) e aceitação (Codeception), com nomes que descrevem o
  comportamento.
- Textos em pt-BR, identificadores em inglês, commit `type: Title` em
  inglês, PSR-12 e PHPStan 6 sem avisos.

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

Resultado obtido: Unit 74 testes, Integration 15, Acceptance 12 (eram
71 / 9 / 9 na base). Testes novos:

- `UserTest::test_change_password_should_replace_the_password`,
  `..._should_fail_when_confirmation_differs`, `..._should_fail_when_empty`.
- `PasswordsControllerTest`: `test_edit_should_render_the_change_password_form`,
  `test_update_with_valid_password_should_redirect_to_dashboard`,
  `test_update_with_mismatched_confirmation_should_render_the_error`,
  `test_update_with_empty_password_should_render_the_error`.
- `PasswordAccessTest`:
  `test_edit_password_should_redirect_to_login_without_session`,
  `test_update_password_should_redirect_to_login_without_session`.
- `ChangePasswordCest`: `accessChangePasswordWithoutLogin`,
  `changePasswordAndLoginWithTheNewOne`,
  `changePasswordWithMismatchedConfirmation`.

No navegador: `http://localhost`. Logue com `user@example.com` /
`123456`, abra o menu do e-mail, "Trocar senha", informe `654321` duas
vezes, "Salvar": volta ao painel com "Senha alterada com sucesso!".
Saia e logue com `654321`. Repita com confirmação diferente: fica em
`/password` com a mensagem vermelha e o erro abaixo do campo.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/b6-trocar-senha` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b6 <caminho>/projeto
git fetch fvp-b6 fvp/b6-trocar-senha
git cherry-pick 8fac90f
git remote remove fvp-b6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b6-trocar-senha:fvp-b6
git cherry-pick 8fac90f
git branch -D fvp-b6          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O commit
criado tem SHA novo (`cherry-pick` recria o commit com outra data); a
árvore é idêntica à do bundle.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo e gera a mesma árvore.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário:

- `config/routes.php`: qualquer cenário que adiciona rota no grupo
  `auth` (A3, A6, A7, D1). Resolva mantendo todas as rotas; a ordem não
  importa aqui.
- `app/views/layouts/_navbar.phtml`: A2 (link "Sair" vira form), A6
  (item "Perfil"), C2 (nome no lugar do e-mail), D1 (link "Usuários").
  Mantenha os dois itens no dropdown.
- `app/Models/User.php`: B1/B2/B5 mexem em `validates()` e `$columns`;
  `changePassword` entra depois de `registerLogin()` e não colide com a
  lógica deles.
- `tests/Unit/Models/Users/UserTest.php`: outros cenários acrescentam
  testes no fim do arquivo; aceite os dois blocos.

Depois de aplicar: `./run phpcs`, `./run phpstan` e os testes de novo.
Não há mudança de schema.

## Armadilhas

- `User::__set` só gera o hash quando `newRecord()`. Em usuário salvo,
  atribuir `password` não muda nada (há até o teste
  `test_update_should_not_change_the_password` provando isso). Por isso
  o hash é feito dentro de `changePassword` e gravado por `update()`.
- `update()` não valida; a validação tem que ser chamada antes, à mão.
- `$this->errors` precisa ser zerado no início de `changePassword`,
  senão um erro antigo de `isValid()` sobrevive.
- A ação `update` roda só com usuário logado (grupo `auth`), mas o
  PHPStan vê `current_user` como `?User`; o `$user !== null &&` evita o
  aviso e um 500 se alguém chamar a ação fora do grupo.
- Form sem `_method=PUT`: 404, porque a rota é `PUT /password`.
- No teste de controller, `Auth::login` tem que vir no `setUp`, antes de
  `put(...)`: o construtor do controller lê `Auth::user()`.
- Se o professor pedir a senha atual como confirmação extra, acrescente
  um campo `user[current_password]` e `$user->authenticate(...)` antes
  de `changePassword`.
