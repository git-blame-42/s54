# D6. Admin altera o perfil de um usuário (promover/rebaixar)

Pedido, como o professor faria na hora: "Na lista de usuários do admin,
deixe o administrador trocar o perfil de um usuário entre admin e
participante. Ele não pode rebaixar a si mesmo."

Chance: média. Tempo estimado: 25 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção D6.

Este cenário é empilhado sobre o D1 (a lista de usuários precisa existir
para ter o formulário na linha). Branch no clone:
`fvp/d6-admin-altera-perfil`, com dois commits acima de `main`. `main`
continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `7c8d56c` | `feat: List users with pagination in admin area` | cenário D1 (mesmo commit, mesmo SHA) |
| `14c7193` | `feat: Allow admin to change user role` | este cenário |

## O que mudou (só o commit `14c7193`)

- `config/routes.php`: `Route::put('/admin/users/{id}', ...)` com o nome
  `admin.users.update`, dentro do grupo `admin`.
- `app/Models/User.php`: constante `ROLES = ['admin', 'participant']` e
  método `changeRole(string $role): bool`, que recusa valor fora do ENUM
  e grava com `update(['role' => ...])`.
- `app/Controllers/Admin/UsersController.php`: ação `update(Request)`.
  Lê `id` e `user[role]` pelo `$request->getParam()`. Usuário inexistente
  ou perfil inválido: `FlashMessage::danger('Não foi possível alterar o
  perfil.')`. Próprio admin logado: `FlashMessage::danger('Você não pode
  alterar o próprio perfil.')`. Senão `changeRole()` e
  `FlashMessage::success('Perfil alterado com sucesso!')`. Sempre
  redireciona para `admin.users.index`.
- `app/views/admin/users/index.phtml`: na coluna "Perfil", cada linha
  (menos a do admin logado) tem um `<form method="post" novalidate>` com
  `_method=PUT`, `<select name="user[role]">` (`participant`/`admin`, o
  atual vem `selected`) e botão "Salvar". A linha do admin logado mostra
  só o texto do perfil.
- `tests/Unit/Models/Users/UserChangeRoleTest.php` (novo): 3 testes do
  `changeRole`.
- `tests/Integration/Controllers/Admin/UsersControllerTest.php`: 6 testes
  novos e `unset($_SESSION['flash'])` no `setUp` (a flash vazava entre
  testes).
- `tests/Integration/Access/AdminUsersAccessTest.php`: `PUT
  /admin/users/1` sem sessão responde 302 para `/login`.
- `tests/Acceptance/admin/AdminUsersCest.php`: `promoteUserAsAdmin` e
  `adminCannotChangeOwnRole`.

8 arquivos, +217 / -1 linhas.

## Por que assim

- Rota RESTful: `PUT /admin/users/{id}` com ação `update`, nomeada, como
  pede a rubrica da 4.2 (`grading-path.md`).
- Formulário com `_method=PUT`: o `Request` do framework lê esse campo e
  o `Router` casa a rota PUT; mesmo mecanismo que o feedback 2025-2
  exigiu para o logout.
- Parâmetros só pelo `Request` (`getParam('id')`, `getParam('user')`),
  nunca `$_POST`.
- Autorização continua nos middlewares `auth` e `admin` do grupo; o
  controller só aplica a regra de negócio (não alterar o próprio perfil).
- Controller fino: a validação do perfil (ENUM) fica no model, em
  `changeRole()`; o controller busca, decide, chama o model, faz a flash
  e redireciona. Desvio pequeno do catálogo, que faz o `in_array` no
  controller.
- `novalidate` + validação no servidor: valor fora do ENUM vira flash de
  erro, não `PDOException` (500).
- Cast `(int)` na comparação de `id` (PDO devolve string em alguns
  caminhos).
- Testes nas quatro camadas (unit, controller, acesso, aceitação), nomes
  descritivos. Os testes de unidade ficam em arquivo próprio para não
  conflitar com outros cenários que mexem no `UserTest`. PSR-12,
  PHPStan 6, textos em pt-BR, identificadores em inglês, commit em
  inglês.

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

Resultado obtido: Unit 74 testes, Integration 21, Acceptance 15 (a base
do D1 é 71 / 14 / 13; o público é 71 / 9 / 9). Testes novos deste
commit:

- `tests/Unit/Models/Users/UserChangeRoleTest.php`:
  `test_change_role_should_promote_participant_to_admin`,
  `test_change_role_should_demote_admin_to_participant`,
  `test_change_role_should_reject_unknown_role`.
- `tests/Integration/Controllers/Admin/UsersControllerTest.php`:
  `test_update_should_promote_participant_to_admin`,
  `test_update_should_demote_admin_to_participant`,
  `test_update_should_not_change_the_own_role`,
  `test_update_should_reject_unknown_role`,
  `test_update_should_ignore_unknown_user`,
  `test_index_should_not_render_role_form_in_own_row`.
- `tests/Integration/Access/AdminUsersAccessTest.php`:
  `test_users_update_should_redirect_to_login_without_session`.
- `tests/Acceptance/admin/AdminUsersCest.php`: `promoteUserAsAdmin`,
  `adminCannotChangeOwnRole`.

No navegador: `http://localhost`, login `admin@example.com` / `123456`,
menu "Usuários". Na linha de `user@example.com`, escolha `admin` no
seletor e clique "Salvar": volta para a lista com a mensagem verde e o
seletor mostra `admin`. A linha do admin logado não tem seletor.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público. `<caminho>` abaixo é `s50/exemplos-fvp/d6-admin-altera-perfil`
(relativo ao clone do s50).

Primeiro veja se o alvo já tem o D1 (`git log --oneline | grep "List
users"`). Se tem, pegue só `14c7193`. Se não tem, pegue os dois, na
ordem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d6 <caminho>/projeto
git fetch fvp-d6 fvp/d6-admin-altera-perfil

git cherry-pick 14c7193             # alvo já tem o D1
git cherry-pick 7c8d56c 14c7193     # alvo sem o D1: os dois, nesta ordem
git cherry-pick main..fvp-d6/fvp/d6-admin-altera-perfil   # mesma coisa, por intervalo

git remote remove fvp-d6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/d6-admin-altera-perfil:fvp-d6
git cherry-pick 14c7193             # alvo já tem o D1
git cherry-pick main..fvp-d6        # alvo sem o D1: aplica os dois commits
git branch -D fvp-d6                # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick main..fvp-d6` e
`git cherry-pick 7c8d56c 14c7193` aplicam os dois sem conflito. O
cherry-pick cria commits novos; o SHA no seu repositório vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

O patch contém os dois commits (formato mbox). Em alvo sem o D1:

```
git am <caminho>/solucao.patch       # cria os dois commits, mesmas mensagens
```

Em alvo que já tem o D1, o primeiro patch falha ("already exists in
index"). Pule-o:

```
git am <caminho>/solucao.patch
git am --skip                        # pula o D1, aplica o D6
```

Ou gere um patch só do D6 no clone `projeto/` com
`git format-patch -1 14c7193 --stdout > d6.patch` e aplique esse.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- Com o D2 (remover usuário, também sobre o D1), testado: conflitam
  `config/routes.php`, `app/Controllers/Admin/UsersController.php`,
  `tests/Integration/Controllers/Admin/UsersControllerTest.php`,
  `tests/Integration/Access/AdminUsersAccessTest.php` e
  `tests/Acceptance/admin/AdminUsersCest.php`. Os dois acrescentam no
  fim do arquivo (rota, ação, testes) e no `use Lib\FlashMessage;`.
  Fique com os dois lados, um `use` só. A view mescla sozinha (D2 mexe
  na coluna "Ações", D6 na coluna "Perfil").
- `app/Models/User.php`: H2 (constantes de perfil) e A7 (perfil
  `organizer`) mexem no mesmo lugar. Com H2, troque `ROLES` pelas
  constantes dele; com A7, inclua `organizer` em `ROLES` e no `<select>`.
- `config/routes.php`: qualquer cenário que acrescente rota no grupo
  `admin` (D4, D7, E1). Mantenha as rotas.

Depois de aplicar: não há mudança de schema; rode `./run phpcs`,
`./run phpstan` e os testes de novo.

## Armadilhas

- `PUT` sem `_method` no formulário: o Router não acha a rota e responde
  404. O `<input type="hidden" name="_method" value="PUT">` é obrigatório.
- `update()` do model não valida: `role` fora do ENUM vira
  `PDOException` e página 500. Por isso o `changeRole()` confere
  `ROLES` antes.
- Admin que rebaixa a si mesmo perde o acesso na hora (o middleware
  `Admin` o manda para `/dashboard`); por isso o bloqueio no controller e
  a linha sem seletor.
- `getParam('user')` pode vir `null` ou string (POST sem `user[...]`);
  ler `['role']` direto gera warning e o `ErrorsHandler` responde 500.
  O controller confere `is_array` antes.
- Nos testes de controller, a flash fica em `$_SESSION['flash']` e o
  `tearDown` da base não a limpa; sem o `unset` no `setUp`, um teste vê
  a mensagem do anterior.
- `selectOption`/`click` com contexto: use XPath único da linha
  (`//tr[contains(., "fulano@example.com")]//select[@name="user[role]"]`);
  `click('Salvar', $contexto)` com dois argumentos falha no PHPStan.
