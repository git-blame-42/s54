# A6. Nova página restrita (`/profile`)

Pedido, como o professor faria na hora: "Criem uma página `/profile`
que mostre os dados do usuário logado; quem não estiver logado tem que
ser mandado para o login."

Chance: alta. Tempo estimado: 20 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A6.

Branch no clone: `fvp/a6-nova-pagina-restrita-profile`. `main`
continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `00be668` | `feat: Add profile page for authenticated users` |

## O que mudou

- `config/routes.php`: `Route::get('/profile', [ProfileController::class,
  'show'])->name('profile.show')` dentro do grupo `Route::middleware('auth')`,
  logo abaixo de `/dashboard`. Mais o `use App\Controllers\ProfileController`.
- `app/Controllers/ProfileController.php` (novo): uma ação `show()` que define
  `$title = 'Meu Perfil'` e chama `$this->render('profile/show', ...)`. O
  usuário vem de `$this->currentUser()` na view; o controller não busca nada.
- `app/views/profile/show.phtml` (novo): lista com nome, e-mail, perfil
  ("Administrador" ou "Participante", via `isAdmin()`) e último acesso
  formatado com `date('d/m/Y H:i', ...)` ou "primeiro acesso".
- `app/views/layouts/_navbar.phtml`: item "Perfil" no dropdown do usuário,
  acima de "Sair", apontando para `route('profile.show')`.
- `tests/Integration/Access/ProfileAccessTest.php` (novo): `GET /profile`
  sem sessão responde 302 para `/login`.
- `tests/Integration/Controllers/ProfileControllerTest.php` (novo): com
  `Auth::login` no `setUp`, `show` renderiza `<h1>Meu Perfil</h1>`, nome,
  e-mail, perfil e "Último acesso:" (não "primeiro acesso", porque
  `Auth::login` grava `last_login_at`).
- `tests/Acceptance/profile/ProfileCest.php` (novo): sem login cai em
  `/login` com a mensagem do middleware; logado vê a página; o link
  "Perfil" da navbar leva até ela.

7 arquivos, +147 linhas.

## Por que assim

- Autorização por middleware, não dentro da ação: a rota entra no grupo
  `auth` e o `Authenticate` já faz o redirecionamento
  (`grading-path.md`, feedback 2025-2: "autorização em middleware").
- Controller fino: só define título e renderiza; dado vem do
  `currentUser()` herdado de `Core\Http\Controllers\Controller`.
- Rota nomeada (`profile.show`) e usada via `route()` na navbar; nada de
  caminho fixo no HTML.
- Nome RESTful: recurso singular `profile`, ação `show`, como no
  problem-track. PSR-4: `App\Controllers\ProfileController` em
  `app/Controllers/ProfileController.php`.
- Teste em cada camada da rubrica 4.x: acesso (rota autenticada bloqueada
  sem sessão), controller e aceitação (cenário completo no navegador).
- Textos em pt-BR, identificadores em inglês, commit em inglês
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

Resultado obtido: Unit 71 testes, Integration 12, Acceptance 12 (eram
71 / 9 / 9 na base). Testes novos:

- `ProfileAccessTest::test_profile_should_redirect_to_login_without_session`
- `ProfileControllerTest::test_show_should_render_current_user_data`
- `ProfileControllerTest::test_show_should_render_last_login`
- `ProfileCest::accessProfileWithoutLogin`
- `ProfileCest::accessProfileAfterLogin`
- `ProfileCest::navbarLinksToProfile`

No navegador: `http://localhost/profile` sem login volta para `/login`
com "Você deve estar logado para acessar essa página". Logue com
`user@example.com` / `123456`, abra o menu com o e-mail no canto direito,
clique em "Perfil": aparece "Meu Perfil" com nome, e-mail, "Participante"
e a data do acesso.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a6-nova-pagina-restrita-profile` (relativo ao clone
do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a6 <caminho>/projeto
git fetch fvp-a6 fvp/a6-nova-pagina-restrita-profile
git cherry-pick 00be668
git remote remove fvp-a6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a6-nova-pagina-restrita-profile:fvp-a6
git cherry-pick 00be668
git branch -D fvp-a6           # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O commit
criado pelo cherry-pick é novo (SHA diferente, mesma mensagem e mesmo
diff).

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

- `config/routes.php`: qualquer outro cenário que acrescente rota no grupo
  `auth` (B6, B7, D1, D3) ou mexa no logout (A2, A3). Resolva mantendo as
  duas rotas; a ordem dentro do grupo não importa, exceto que `/x/new`
  precisa vir antes de `/x/{id}`.
- `app/views/layouts/_navbar.phtml`: A2 troca o link "Sair" por um
  formulário DELETE; C2 troca o e-mail pelo nome; D1 acrescenta
  "Usuários". Mantenha a versão do alvo e só insira o `<li>` de "Perfil"
  antes de "Sair".

Depois de aplicar: não há mudança de schema; rode `./run phpcs`,
`./run phpstan` e os testes de novo. Se o alvo já tem C2 (nome na
navbar), o `ProfileCest::navbarLinksToProfile` precisa clicar no nome
(`Fulano`) em vez do e-mail.

## Armadilhas

- Rota fora do grupo `auth`: a página abre sem login e
  `$this->currentUser()` é `null`, estourando "Attempt to read property
  on null" na view. O teste de acesso pega isso.
- `render` sem `$title` no `compact`: o layout usa `$title` no `<h1>` e a
  página dá 500.
- `ProfileCest::navbarLinksToProfile` clica no texto do e-mail para abrir
  o dropdown (igual a `LoginHelper::logout()`); se a navbar mostrar outra
  coisa (C2), ajuste o `click`.
- `last_login_at` chega como string `Y-m-d H:i:s` do MySQL; use
  `strtotime` antes de `date`.
- A página mostra o próprio usuário, não um `{id}` na URL: `/profile`
  singular, sem parâmetro, é o padrão RESTful para "o recurso do usuário
  atual". Se o professor pedir `/users/{id}`, aí é outra história (D-series).
