# B7. Cadastro de usuário (sign up)

Pedido, como o professor faria na hora: "Hoje só o populate cria
usuários. Façam uma tela de cadastro público, com validação, que depois
manda a pessoa para o login."

Chance: média. Tempo estimado: 35 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B7.

Branch no clone: `fvp/b7-cadastro-sign-up`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `627f95b` | `feat: Add user sign up` |

## O que mudou

- `config/routes.php`: rotas públicas `GET /users/new` (`users.new`) e
  `POST /users` (`users.create`), fora do grupo `auth`. O nome
  `users.login` já existe; por isso `users.new`/`users.create`.
- `app/Controllers/UsersController.php` (novo): `new()` renderiza o
  formulário com um `User` vazio; `create(Request)` lê
  `$request->getParam('user')`, monta o `User` com lista explícita de
  campos (`name`, `email`, `password`, `password_confirmation`), salva
  e redireciona para `/login` com flash de sucesso; se inválido,
  re-renderiza o formulário com os erros e flash de erro.
- `app/views/users/new.phtml` (novo): formulário `novalidate`, campos
  `user[...]`, classe `is-invalid` + `invalid-feedback` com
  `$user->errors('campo')`, nome e e-mail digitados preservados no
  re-render; link "Já tenho conta".
- `app/views/authentications/new.phtml`: link "Cadastre-se" ao lado do
  botão Entrar.
- `tests/Integration/Controllers/UsersControllerTest.php` (novo, 4
  testes): `new` renderiza; `create` válido salva e manda para `/login`;
  inválido mostra "não pode ser vazio!" e "as senhas devem ser
  idênticas!" mantendo o e-mail; `role=admin` no POST é ignorado.
- `tests/Integration/Access/SignUpAccessTest.php` (novo, 2 testes):
  `GET /users/new` responde 200 sem sessão; `/login` tem o link.
- `tests/Acceptance/users/SignUpCest.php` (novo, 2 cenários): cadastro
  válido seguido de login; cadastro inválido mostra os erros.

Resultado: 7 arquivos, +255 linhas.

## Por que assim

- Rotas RESTful com as ações `new`/`create` e nomes `users.new`,
  `users.create`, como no `problem-track` (rubrica 4.2, `grading-path.md`).
- Parâmetros só pelo `Request` (`getParam('user')`), nunca `$_POST`
  (feedback 2025-2).
- Lista explícita de campos no controller: `Model::__set` aceita
  qualquer coluna de `$columns`, então `new User($params)` deixaria um
  `user[role]=admin` enviado à mão criar um administrador. O teste
  `test_create_should_ignore_role_sent_in_the_request` prova a defesa.
- Validação no servidor com `novalidate` no form, re-render mantendo os
  valores válidos e erros por campo com `invalid-feedback` (linhas da
  rubrica 4.2 sobre qualidade de formulário).
- O hash da senha vem de graça: `User::__set('password')` em registro
  novo já faz `password_hash`.
- Controller fino: a regra está no `User::validates()`.
- Testes nas três camadas (controller, acesso, aceitação); nomes
  descritivos. Textos em pt-BR, identificadores em inglês, commit em
  inglês `type: Title`.

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

Resultado obtido: Unit 71 testes, Integration 15, Acceptance 11 (eram
71 / 9 / 9 na base). Testes novos:

- `UsersControllerTest::test_new_should_render_sign_up_form`
- `UsersControllerTest::test_create_with_valid_data_should_save_and_redirect_to_login`
- `UsersControllerTest::test_create_with_invalid_data_should_render_form_with_errors`
- `UsersControllerTest::test_create_should_ignore_role_sent_in_the_request`
- `SignUpAccessTest::test_sign_up_form_should_be_accessible_without_session`
- `SignUpAccessTest::test_login_page_should_link_to_sign_up`
- `SignUpCest::signUpWithValidDataAndLogin`
- `SignUpCest::signUpWithInvalidDataShowsErrors`

No navegador: `http://localhost/login`, clique em "Cadastre-se".
Envie vazio: os campos ficam vermelhos com a mensagem embaixo e o flash
"Existem dados incorretos!" aparece. Preencha nome, e-mail e a mesma
senha duas vezes: volta para `/login` com "Cadastro realizado! Faça
login."; entre com a conta nova e veja o painel.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/b7-cadastro-sign-up` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b7 <caminho>/projeto
git fetch fvp-b7 fvp/b7-cadastro-sign-up
git cherry-pick 627f95b
git remote remove fvp-b7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b7-cadastro-sign-up:fvp-b7
git cherry-pick 627f95b
git branch -D fvp-b7         # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito e a árvore
fica idêntica à da branch. O cherry-pick cria um commit novo; o SHA só
coincide se o commit for gravado no mesmo segundo, então não conte com
isso.

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

- `config/routes.php`: qualquer cenário que mexe nas rotas públicas
  (A2 troca o `/logout` para DELETE; A3 move o `/logout`; A5 agrupa
  `/login` em `guest`). Resolva mantendo as duas mudanças; as rotas de
  cadastro ficam fora de `auth` e, se houver grupo `guest`, dentro dele.
- `app/views/authentications/new.phtml`: C4 (layout próprio do login)
  ou qualquer mudança no formulário. Mantenha o link "Cadastre-se".
- `tests/Integration/Controllers/`: se o alvo já tem um
  `UsersControllerTest.php` para outro propósito (D1 cria
  `Admin/UsersControllerTest.php`, em outra pasta: sem conflito).

Depois de aplicar: rode `./run phpcs`, `./run phpstan` e os testes de
novo. Não há mudança de schema.

## Armadilhas

- `new User($params)` direto é a armadilha principal: cria admin via
  POST. Liste os campos.
- `$request->getParam('user')` devolve `null` se o POST veio sem o
  array; use `getParam('user', [])` e `?? null` em cada campo, senão
  `Warning: Trying to access array offset on null`.
- O `<h1>` vem do layout a partir de `$title`; esqueça o `title` no
  `compact` e a página dá 500.
- No `create` inválido o re-render responde na URL `/users` (POST), não
  em `/users/new`; o teste de aceitação confere `/users`.
- Se B1 (senha mínima) ou B2 (e-mail válido) já estiverem aplicados, os
  erros novos aparecem automaticamente no formulário, porque ele imprime
  `$user->errors('campo')` para cada campo.
- O populate continua sendo o jeito de criar admin; o cadastro público
  só cria `participant`.
