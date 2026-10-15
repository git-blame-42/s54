# B8. Editar o próprio perfil (nome e e-mail) com save()

Pedido, como o professor faria na hora: "O usuário logado precisa conseguir
alterar o próprio nome e e-mail, com validação e mensagens de erro no
formulário."

Chance: média-alta. Tempo estimado: 25 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B8.

Branch no clone: `fvp/b8-editar-proprio-perfil`. `main` continua igual ao
repositório público (`86c81ca`). Cenário independente: não precisa do A6
(`/profile`).

| Commit | Mensagem |
|--------|----------|
| `048eec9` | `feat: Allow users to edit their own profile` |

## O que mudou

- `config/routes.php`: `GET /profile/edit` (`profile.edit`) e `PUT /profile`
  (`profile.update`) dentro do grupo `auth`.
- `app/Controllers/ProfileController.php`: `edit` mostra o formulário com o
  usuário logado; `update` lê `getParam('user')`, atribui só `name` e
  `email`, chama `save()`; sucesso: flash e redirect para `/dashboard`; falha:
  flash de erro e re-render com os valores digitados.
- `app/views/profile/edit.phtml`: formulário com `novalidate`, `_method=PUT`,
  `was-validated` quando há erros, `invalid-feedback` por campo, `value`
  escapado com `htmlspecialchars`.
- `app/views/layouts/_navbar.phtml`: item "Editar perfil" no menu do usuário
  (antes de "Sair").
- `tests/Integration/Controllers/ProfileControllerTest.php`: 4 testes de
  controller.
- `tests/Integration/Access/ProfileAccessTest.php`: 2 testes de acesso sem
  sessão.
- `tests/Acceptance/profile/ProfileCest.php`: 2 testes de aceitação.

## Por que assim

- Rotas RESTful nomeadas (`edit` em GET, `update` em PUT via `_method`) e
  geradas com `route()` (`grading-path.md`).
- Autorização no middleware `auth` do grupo; o controller não confere
  sessão.
- Parâmetros só por `$request->getParam('user', [])`; só `name` e `email`
  são atribuídos (nada de `role` vindo do formulário).
- `save()` (não `update()`) para rodar `validates()`: `notEmpty` em nome e
  e-mail, `uniqueness('email')`, que ignora o próprio registro.
- O `update` edita uma cópia lida do banco (`User::findById`), não
  `$this->current_user`: se a validação falhar, a navbar continua mostrando o
  e-mail salvo, e não o valor inválido.
- `novalidate` + validação no servidor, com re-render mantendo os valores
  digitados.
- Controller fino: validação no model.
- Testes em controller, acesso e aceitação; PSR-12 e PHPStan nível 6 limpos;
  textos em pt-BR; identificadores e commit em inglês.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Sem mudança de schema: não precisa de `db:reset`.

Resultado obtido: Unit 71 testes, Integration 15, Acceptance 11 (eram
71 / 9 / 9 na base). Testes novos:

- `ProfileControllerTest`
  (`tests/Integration/Controllers/ProfileControllerTest.php`):
  `test_edit_should_render_form_with_current_values`,
  `test_update_with_valid_data_should_save_and_redirect_to_dashboard`,
  `test_update_with_email_of_another_user_should_render_uniqueness_error`,
  `test_update_with_empty_name_should_render_errors_keeping_values`.
- `ProfileAccessTest` (`tests/Integration/Access/ProfileAccessTest.php`):
  `test_edit_profile_should_redirect_to_login_without_session`,
  `test_update_profile_should_redirect_to_login_without_session`.
- `ProfileCest` (`tests/Acceptance/profile/ProfileCest.php`):
  `editOwnProfileFromNavbar`, `editProfileWithEmptyNameShowsError`.

No navegador: `http://localhost`, entrar com `fulano@example.com` / `123456`,
abrir o menu com o e-mail na navbar, "Editar perfil", trocar nome e e-mail,
"Salvar". Volta para `/dashboard` com "Perfil atualizado com sucesso!",
"Bem-vindo(a), <novo nome>!" e o e-mail novo na navbar. Com nome vazio aparece
"Existem dados incorretos!" e "não pode ser vazio!" embaixo do campo.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/b8-editar-proprio-perfil`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b8 s50/exemplos-fvp/b8-editar-proprio-perfil/projeto
git fetch fvp-b8 fvp/b8-editar-proprio-perfil
git cherry-pick 048eec9
git remote remove fvp-b8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/b8-editar-proprio-perfil/solucao.bundle fvp/b8-editar-proprio-perfil:fvp-b8
git cherry-pick 048eec9        # o bundle traz o objeto; o cherry-pick cria um commit novo
git branch -D fvp-b8           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/b8-editar-proprio-perfil/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/b8-editar-proprio-perfil/solucao.patch   # só o diff, sem commit
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

- `config/routes.php`: outros cenários também incluem `use` e rotas no grupo
  `auth` (A6 `/profile`, B6 senha, D2/D3). Mantenha os dois lados: todos os
  `use` e todas as rotas. Com A6 aplicado, dá para trocar o redirect do
  `update` para `route('profile.show')` e o teste para `Location: /profile`.
- `app/views/layouts/_navbar.phtml`: A6/C2 mexem no mesmo menu (link "Perfil",
  nome no lugar do e-mail). Mantenha os dois itens. Se C2 trocar o e-mail pelo
  nome no botão do menu, o `click('fulano@example.com')` do `ProfileCest` passa
  a ser `click('Fulano')` (e o mesmo vale para `LoginHelper::logout()`).
- `app/Controllers/ProfileController.php`: se A6 já criou o controller com
  `show`, junte os métodos na mesma classe.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem mudança de
schema).

## Armadilhas

- `save()` valida; `update([...])` não. Use `save()`.
- Em registro existente, `save()` grava todas as colunas de `$columns`; funciona
  porque o objeto veio inteiro do banco.
- `passwordConfirmation` só roda em `newRecord()`; senha fica de fora (B6).
- Formulário sem `<input type="hidden" name="_method" value="PUT">` cai em 404.
- `render` sem `$title` no `compact` dá 500.
- Editar `$this->current_user` direto faz a navbar mostrar o valor inválido no
  re-render; edite uma cópia.
- Sem `novalidate`, o navegador barra o campo vazio e a validação do servidor
  nem aparece.
- O link "Editar perfil" fica no dropdown: na aceitação, clique no e-mail
  antes.
