# E1. Resposta JSON para uma listagem

Pedido, como o professor faria na hora: "A listagem de usuários do
admin precisa responder em JSON quando o cliente pedir, para a tela
consumir via Ajax."

Chance: baixa agora, alta na 4.4 (rubrica cita `renderJson`). Tempo
estimado: 20 min. Catálogo: `fvp-possiveis-modificacoes.md`, seção E1.

Branch no clone: `fvp/e1-resposta-json-listagem`. `main` continua
igual ao repositório público (`86c81ca`).

Este cenário é empilhado sobre o D1: sem a listagem não há o que
responder em JSON. A branch tem dois commits:

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `7c8d56c` | `feat: List users with pagination in admin area` | cenário D1, mesmo objeto (mesmo SHA) |
| `3dcef1d` | `feat: Return JSON for admin users listing` | este cenário |

## O que mudou

Só o segundo commit (`3dcef1d`), 4 arquivos, +79 / -2:

- `app/Controllers/Admin/UsersController.php`: a ação `index` continua
  uma só; se `$request->acceptJson()` for verdadeiro, chama
  `$this->renderJson('admin/users/index', compact('users', 'paginator'))`;
  senão, o `render` HTML de antes. Mesma rota, duas representações.
- `app/views/admin/users/index.json.php` (novo): preenche `$json['users']`
  (id, name, email, role, last_login_at) e `$json['pagination']` (page,
  per_page, total_of_registers, total_of_pages). O `renderJson` do
  framework faz o `json_encode` e manda o `Content-Type`.
- `tests/Integration/Controllers/Admin/UsersControllerTest.php`: três
  testes novos. Com `Accept: application/json` vem JSON com 10 usuários
  na página 1 e 3 na página 2, paginação correta e nenhum `<table`;
  sem o header vem HTML.
- `tests/Integration/Access/AdminUsersAccessTest.php`: um teste novo.
  `GET /admin/users` com `Accept: application/json` e sem sessão
  continua 302 para `/login` (o middleware não muda por causa do
  formato).

## Por que assim

- É exatamente a peça do framework que a rubrica da 4.4 pede para
  mostrar: `renderJson` em `core/Http/Controllers/Controller.php`
  (`grading-path.md`, seção 4.4). A view `.json.php` é a convenção do
  framework (`renderJson` monta o caminho `app/views/<view>.json.php`).
- Uma ação, dois formatos, escolhidos pelo header `Accept`: a rota
  continua RESTful (`GET /admin/users`), sem criar `/admin/users.json`
  nem `/api/...`.
- Autorização continua no middleware `admin` dentro de `auth`; o JSON
  não abre brecha. O teste de acesso prova isso.
- Parâmetros só pelo `Request` (`getParam('page', 1)`), como no D1.
- A view JSON não expõe `encrypted_password`: lista os campos um a um
  em vez de serializar o objeto inteiro.
- PSR-12, PHPStan nível 6 (views ficam fora do PHPStan por
  configuração), commit em inglês `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 71 testes, Integration 18, Acceptance 13 (eram
71 / 9 / 9 na base e 71 / 14 / 13 depois do D1). Testes novos:

- `UsersControllerTest::test_index_should_return_json_when_client_accepts_json`
- `UsersControllerTest::test_index_should_return_json_for_the_requested_page`
- `UsersControllerTest::test_index_should_render_html_without_json_accept_header`
- `AdminUsersAccessTest::test_users_index_as_json_should_redirect_to_login_without_session`

No navegador: `http://localhost`, logue como `admin@example.com` /
`123456`, abra `/admin/users` (HTML normal). Depois, no console do
DevTools (aba Console), com a aba Network aberta para mostrar a
requisição na demo:

```js
fetch('/admin/users', { headers: { Accept: 'application/json' } })
  .then(r => r.json())
  .then(console.log)
```

Aparece `{users: [...], pagination: {page: 1, ...}}`. Sem login, o
mesmo `fetch` segue o redirecionamento para `/login` (status 302 na
aba Network). Pela linha de comando, de dentro da rede do compose:

```
docker compose exec php curl -s -c /tmp/c -d "user[email]=admin@example.com&user[password]=123456" http://web/login
docker compose exec php curl -s -b /tmp/c -H "Accept: application/json" http://web/admin/users
```

Atenção: a porta 80 do `web` vai para o PHP de desenvolvimento; a
8080 vai para o `php_test` (banco `eventos_test`, que os testes apagam
no fim). Para testar à mão use a 80.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/e1-resposta-json-listagem` (relativo ao clone do
s50). Cherry-pick sempre cria commits novos no alvo (SHAs diferentes
dos listados aqui); o que se preserva é o conteúdo e a mensagem.

Primeiro decida: o alvo já tem a listagem do D1?

- Não tem: aplique os dois commits, em ordem (`7c8d56c` e depois
  `3dcef1d`), ou o intervalo inteiro.
- Já tem (aplicou o cenário D1 antes): aplique só `3dcef1d`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-e1 <caminho>/projeto
git fetch fvp-e1 fvp/e1-resposta-json-listagem

git cherry-pick 7c8d56c 3dcef1d            # alvo sem D1
git cherry-pick fvp-e1/main..fvp-e1/fvp/e1-resposta-json-listagem   # mesma coisa, por intervalo
git cherry-pick 3dcef1d                    # alvo que já tem D1

git remote remove fvp-e1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/e1-resposta-json-listagem:fvp-e1
git cherry-pick 7c8d56c 3dcef1d   # alvo sem D1
git cherry-pick 3dcef1d           # alvo que já tem D1
git branch -D fvp-e1              # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: os dois commits aplicam sem
conflito e a árvore final é idêntica à da branch do bundle. Testado
também aplicar só `3dcef1d` sobre um alvo que recebeu o D1 antes:
aplica limpo.

### 3. A partir do `solucao.patch` (`git am`)

O patch é uma série com os dois commits.

```
git am <caminho>/solucao.patch                 # alvo sem D1: aplica os dois
git am --skip                                  # se o 1º (D1) já existe e conflitar, pule-o
git apply --3way <caminho>/solucao.patch       # só o diff, sem commit
```

Para aplicar só o E1 pelo patch, extraia o segundo commit da série:
`git am` lê arquivos `mbox`; abra `solucao.patch`, corte a partir da
segunda linha `From 3dcef1d...` até o fim num arquivo novo e aplique
só ele.

Testado: `git am solucao.patch` num clone limpo aplica os dois
commits.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Controllers/Admin/UsersController.php`: se o alvo já mudou a
  ação `index` (D2 acrescenta `destroy`, mas em outro ponto do arquivo;
  D4 pode ter trocado o `paginate`). Mantenha a versão do alvo e
  envolva o `render` existente no `if ($request->acceptJson())`.
- `tests/Integration/Controllers/Admin/UsersControllerTest.php` e
  `tests/Integration/Access/AdminUsersAccessTest.php`: se o alvo
  acrescentou testes no fim desses arquivos (D2 faz isso). Fique com
  os dois blocos de testes.
- `config/routes.php` e `_navbar.phtml`: só se o alvo não tem D1 e já
  mexeu nesses arquivos (A2, A3, A6, A7, C2). O conflito é do commit
  do D1; resolva como no README do D1.

Depois de aplicar: não há mudança de schema; rode `./run test
tests/Integration` e `./run phpcs`.

## Armadilhas

- `acceptJson()` compara o header com `application/json` exato
  (`===`). `Accept: application/json, text/plain` vem como HTML.
  Mande o header exatamente assim no `fetch` e nos testes.
- A view JSON tem que se chamar `index.json.php` (não `.phtml`), no
  mesmo diretório da view HTML; é o `renderJson` que monta o caminho.
- Dentro da view JSON você preenche `$json`; não dê `echo`, não use
  `json_encode` lá. Quem serializa é o `renderJson`.
- O `renderJson` do framework manda `Content-Type: application/json;
  chartset=utf-8` (com o typo `chartset`). O navegador ignora; não
  corrija em `core/` sem combinar, conta como mudança no framework.
- No teste de controller, `$_SERVER['HTTP_ACCEPT']` tem que ser
  definido ANTES do `get(...)`; o `tearDown` do `ControllerTestCase`
  já limpa. Em CLI o `header()` do `renderJson` é ignorado, por isso o
  teste consegue ler o corpo.
- Não serialize `$user` inteiro (`(array) $user` ou `json_encode($user)`):
  vazaria `encrypted_password`. Liste os campos.
- Na demo com DevTools, lembre de abrir a aba Network antes do
  `fetch`; a rubrica da 4.4 pede para mostrar a requisição Ajax.
