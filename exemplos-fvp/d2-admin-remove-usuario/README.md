# D2. Admin remove um usuário

Pedido, como o professor faria na hora: "Na lista de usuários do admin,
coloque um botão para remover o usuário. O admin não pode remover a si
mesmo."

Chance: média. Tempo estimado: 25 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção D2.

Este cenário é empilhado sobre o D1 (a lista de usuários precisa existir
para ter o botão). Branch no clone: `fvp/d2-admin-remove-usuario`, com
dois commits acima de `main`. `main` continua igual ao repositório
público (`86c81ca`).

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `7c8d56c` | `feat: List users with pagination in admin area` | cenário D1 (mesmo commit, mesmo SHA) |
| `14e924c` | `feat: Allow admin to remove users` | este cenário |

## O que mudou (só o commit `14e924c`)

- `config/routes.php`: `Route::delete('/admin/users/{id}', ...)` com o
  nome `admin.users.destroy`, dentro do grupo `admin`.
- `app/Controllers/Admin/UsersController.php`: ação `destroy(Request)`.
  Lê o `id` pelo `$request->getParam('id')`, busca com `User::findById`.
  Se não existe ou é o próprio admin logado: `FlashMessage::danger('Não
  foi possível remover o usuário.')`. Senão `$user->destroy()` e
  `FlashMessage::success('Usuário removido com sucesso!')`. Nos dois
  casos redireciona para `admin.users.index`.
- `app/views/admin/users/index.phtml`: coluna "Ações" com um `<form
  method="post">` por linha, `_method=DELETE` e botão "Remover". A linha
  do admin logado não tem botão.
- `tests/Integration/Controllers/ControllerTestCase.php`: helper
  `delete()` (a base só tinha `get`, `post`, `put`).
- `tests/Integration/Controllers/Admin/UsersControllerTest.php`: 4 testes
  novos (remove outro usuário; não remove a si mesmo; ignora id
  inexistente; não renderiza o botão na própria linha).
- `tests/Integration/Access/AdminUsersAccessTest.php`: `DELETE
  /admin/users/1` sem sessão responde 302 para `/login`.
- `tests/Acceptance/admin/AdminUsersCest.php`: `removeUserAsAdmin`
  (clica "Remover" na linha do Fulano, vê a flash e o e-mail some) e
  `adminCannotRemoveItself` (não há botão na linha do admin).

7 arquivos, +113 linhas.

## Por que assim

- Rota RESTful: `DELETE /admin/users/{id}` com ação `destroy`, igual ao
  `problem-track` e à rubrica da 4.2 (`grading-path.md`).
- Formulário com `_method=DELETE`: o `Request` do framework lê esse campo
  e o `Router` casa a rota DELETE; é o mesmo mecanismo que o feedback
  2025-2 exigiu para o logout.
- Parâmetro só pelo `Request` (`getParam('id')`), nunca `$_POST`.
- Autorização continua nos middlewares `auth` e `admin` do grupo; o
  controller só decide a regra de negócio (não remover a si mesmo).
- Controller fino: busca, decide, chama `destroy()` do model, flash,
  redireciona. Sem SQL no controller.
- Sem `confirm()` em JavaScript: diálogo nativo trava o Selenium e o
  teste de aceitação. Se o professor pedir confirmação, faça uma página
  ou um modal do Bootstrap.
- Cast `(int)` nas comparações de `id`: o PDO devolve o id como inteiro
  em alguns caminhos e como string em outros; `===` sem cast falha em
  silêncio.
- Testes nas três camadas (controller, acesso, aceitação), nomes
  descritivos. PSR-12, PHPStan 6, textos em pt-BR, commit em inglês.

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

Resultado obtido: Unit 71 testes, Integration 19, Acceptance 15 (a base
do D1 é 71 / 14 / 13; o público é 71 / 9 / 9). Testes novos deste
commit: os 4 do `UsersControllerTest`, 1 do `AdminUsersAccessTest` e 2
do `AdminUsersCest`, listados acima.

No navegador: `http://localhost`, login `admin@example.com` /
`123456`, menu "Usuários". Cada linha, menos a do admin, tem o botão
"Remover". Clique no de `user@example.com`: volta para a lista com a
mensagem verde e a linha sumiu. Não há botão na linha do admin.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público. `<caminho>` abaixo é `s50/exemplos-fvp/d2-admin-remove-usuario`
(relativo ao clone do s50).

Primeiro veja se o alvo já tem o D1 (`git log --oneline | grep "List
users"`). Se tem, pegue só `14e924c`. Se não tem, pegue os dois, na
ordem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d2 <caminho>/projeto
git fetch fvp-d2 fvp/d2-admin-remove-usuario

git cherry-pick 14e924c             # alvo já tem o D1
git cherry-pick 7c8d56c 14e924c     # alvo sem o D1: os dois, nesta ordem
git cherry-pick main..fvp-d2/fvp/d2-admin-remove-usuario   # mesma coisa, por intervalo

git remote remove fvp-d2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/d2-admin-remove-usuario:fvp-d2
git cherry-pick 14e924c             # alvo já tem o D1
git cherry-pick main..fvp-d2        # alvo sem o D1: aplica os dois commits
git branch -D fvp-d2                # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick main..fvp-d2`
aplica os dois sem conflito. Testado em clone que já estava em `7c8d56c`
(o D1): `git cherry-pick 14e924c` aplica limpo. O cherry-pick cria
commits novos; o SHA no seu repositório vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

O patch contém os dois commits (formato mbox). Em alvo sem o D1:

```
git am <caminho>/solucao.patch       # cria os dois commits, mesmas mensagens
```

Em alvo que já tem o D1, o primeiro patch falha ("already exists in
index"). Pule-o:

```
git am <caminho>/solucao.patch
git am --skip                        # pula o D1, aplica o D2
```

Ou gere um patch só do D2 no clone `projeto/` com
`git format-patch -1 14e924c --stdout > d2.patch` e aplique esse.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `config/routes.php`: qualquer outro cenário que acrescente rota no
  grupo `admin` (D1 com nome diferente, E1). Mantenha as duas rotas.
- `tests/Integration/Controllers/ControllerTestCase.php`: o A2 também
  acrescenta o helper `delete()`, idêntico. Fique com uma cópia só.
- `app/views/admin/users/index.phtml`: se o alvo reescreveu a tabela do
  D1 (coluna a mais, outro texto), acrescente só a coluna "Ações".
- `tests/Acceptance/admin/AdminUsersCest.php`: se o `_before` do alvo
  cria outra quantidade de usuários, ajuste o "Mostrando 1 - 10 de 11".

Depois de aplicar: não há mudança de schema; rode `./run phpcs`,
`./run phpstan` e os testes de novo.

## Armadilhas

- `DELETE` sem `_method` no formulário: o Router não acha a rota e
  responde 404. O `<input type="hidden" name="_method" value="DELETE">`
  é obrigatório.
- Comparar `$user->id === $this->currentUser()->id` sem cast pode dar
  falso (string contra int) e deixar o admin se remover.
- `currentUser()` devolve `?User`; o PHPStan nível 6 acusa acesso a
  propriedade de `null`. Use `?->` ou guarde numa variável testada.
- `click('Remover', $contexto)` com dois argumentos é rejeitado pelo
  PHPStan (a assinatura gerada do `AcceptanceTester` tem um só). Use um
  XPath único:
  `//tr[contains(., "fulano@example.com")]//button[text()="Remover"]`.
- `confirm()` nativo trava o Chrome do Selenium. Não use.
- Depois de remover o usuário logado em outra aba, a sessão dele continua
  com um id inexistente; `Auth::check()` devolve `false` e o middleware
  manda para `/login`. Comportamento esperado, não é bug.
