# D1. Listar usuários no admin com paginação

Pedido, como o professor faria na hora: "Na área administrativa, mostre
uma lista de todos os usuários cadastrados, com paginação de 10 por
página".

Chance: alta. Tempo estimado: 30 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção D1.

Branch no clone: `fvp/d1-listar-usuarios-admin-paginacao`. `main`
continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `7c8d56c` | `feat: List users with pagination in admin area` |

## O que mudou

- `config/routes.php`: duas rotas novas dentro do grupo `admin`:
  `GET /admin/users` (`admin.users.index`) e
  `GET /admin/users/page/{page}` (`admin.users.paginate`), as duas na
  mesma ação `index`.
- `app/Controllers/Admin/UsersController.php` (novo): ação `index`
  chama `User::paginate(page:, per_page: 10, route:
  'admin.users.paginate')`, pega `registers()` e renderiza.
- `app/views/admin/users/index.phtml` (novo): tabela com id, nome,
  e-mail, perfil e último acesso, seguida de
  `$paginator->renderPagesNavigation()`.
- `app/views/layouts/_navbar.phtml`: link "Usuários" ao lado de
  "Administração", só para admin.
- `tests/Integration/Access/AdminUsersAccessTest.php` (novo): as duas
  rotas redirecionam para `/login` sem sessão.
- `tests/Integration/Controllers/Admin/UsersControllerTest.php` (novo):
  primeira página, página pedida via `page`, links da paginação com a
  rota do admin.
- `tests/Acceptance/admin/AdminUsersCest.php` (novo): sem login,
  usuário comum, admin vê a tabela, segunda página.

Não mexe no `populate.php`: gerar 15 usuários para ver a paginação no
navegador é o cenário G2.

## Por que assim

- Rota RESTful: `index` em `GET /admin/users`; a paginação segue o
  padrão do problem-track, `GET /x/page/{page}` (`grading-path.md`,
  4.2).
- `route: 'admin.users.paginate'` é obrigatório: o padrão do
  `Paginator` seria `users.paginate`, que não existe, e o `route()` do
  helper lançaria erro ao montar os links.
- Autorização fica nos middlewares `auth` e `admin` do grupo; o
  controller não testa perfil (feedback 2025-2: autorização em
  middleware, não por ação).
- Parâmetro `page` lido só por `$request->getParam('page', 1)`, com
  cast para `int` (feedback 2025-2: nunca `$_GET`).
- Controller fino: consulta no `Paginator`, HTML na view.
- Testes nas três camadas que a rubrica da 4.2 cobra: acesso
  (Guzzle), controller e aceitação (Codeception). Nomes descrevem o
  comportamento.
- `Paginator`, `paginate` e `renderPagesNavigation` são peças do
  framework que a rubrica 4.2 manda explicar; a tabela de usuários é
  uma listagem pronta para demonstrar.
- Textos pt-BR na interface; identificadores em inglês; commit em
  inglês `type: Title`; PSR-12 e PHPStan 6 limpos.

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

Resultado obtido: Unit 71 testes, Integration 14, Acceptance 13 (eram
71 / 9 / 9 na base). Testes novos:

- `AdminUsersAccessTest::test_users_index_should_redirect_to_login_without_session`
- `AdminUsersAccessTest::test_users_paginate_should_redirect_to_login_without_session`
- `Admin\UsersControllerTest::test_index_should_list_the_first_page_of_users`
- `Admin\UsersControllerTest::test_index_should_list_the_requested_page`
- `Admin\UsersControllerTest::test_index_should_render_pagination_links_with_the_admin_route`
- `AdminUsersCest::accessUsersListWithoutLogin`
- `AdminUsersCest::accessUsersListAsCommonUser`
- `AdminUsersCest::listUsersAsAdmin`
- `AdminUsersCest::navigateToTheSecondPage`

No navegador: `./run db:reset && ./run db:populate`, abra
`http://localhost`, logue como `admin@example.com` / `123456`, clique
em "Usuários" na navbar. Com só dois usuários aparece uma página;
aplique o G2 para ver "Anterior / 1 2 / Próxima".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/d1-listar-usuarios-admin-paginacao`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d1 <caminho>/projeto
git fetch fvp-d1 fvp/d1-listar-usuarios-admin-paginacao
git cherry-pick 7c8d56c
git remote remove fvp-d1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/d1-listar-usuarios-admin-paginacao:fvp-d1
git cherry-pick 7c8d56c       # mesmo SHA; o bundle preserva o objeto
git branch -D fvp-d1          # opcional, limpa a ref
```

Testado: num clone limpo do público, o `cherry-pick` aplica sem
conflito e, por ter o mesmo pai, autor e data, gera o mesmo
`7c8d56c`.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch            # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch  # só o diff, sem commit
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

- `config/routes.php`: qualquer outro cenário que mexe no grupo
  `admin` (D2, A7) ou nos `use` do topo. Resolva mantendo as duas
  rotas novas dentro do grupo `admin` e o `use ... as
  AdminUsersController`.
- `app/views/layouts/_navbar.phtml`: cenários que acrescentam links
  (A6 Perfil, A7 organizer) ou trocam o e-mail pelo nome (C2). Mantenha
  todos os `<li>`.

Os cenários D2 (remover usuário) e E1 (resposta JSON) partem deste
commit: aplique o D1 primeiro, depois o commit deles.

Depois de aplicar: rode os testes e o `./run phpcs` de novo.

## Armadilhas

- `User::paginate` sem `route:` monta links para `users.paginate`, que
  não existe: página 500 ao renderizar a paginação.
- A rota `/admin/users/page/{page}` precisa estar dentro do grupo
  `admin`; fora dele, a segunda página fica aberta a qualquer logado.
- No teste de controller, o e-mail do admin logado aparece na navbar
  em toda página. Para provar que a página 2 não repete a 1, confira
  um participante da página 1, não o admin.
- No Codeception, o link "Próxima" fica na borda inferior da janela
  de 800x600 do Selenium e o Chrome headless responde "element click
  intercepted". O Cest confere o `href` com `seeLink` e navega com
  `amOnPage`.
- `renderPagesNavigation()` imprime direto (`require` da parcial); não
  faça `echo` do retorno.
