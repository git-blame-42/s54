# A7. Novo perfil (organizer) com área própria

Pedido, como o professor faria na hora: "Criem um terceiro perfil,
`organizer`, com uma área só dele, que nem a do admin."

Chance: média. Tempo estimado: 30 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A7.

Branch no clone: `fvp/a7-novo-perfil-organizer`. `main` continua igual
ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `df21dc9` | `feat: Add organizer role with its own dashboard` |

## O que mudou

Ordem de construção: schema -> model -> middleware -> alias -> rota ->
controller -> view -> navbar -> populate -> testes.

- `database/schema.sql`: o ENUM de `role` ganha `'organizer'`
  (`'admin', 'participant', 'organizer'`). Exige `./run db:reset`.
- `app/Models/User.php`: método `isOrganizer(): bool`, irmão de
  `isAdmin()`.
- `app/Middleware/Organizer.php` (novo): cópia de `Admin` com
  `isOrganizer()` e a mensagem "Acesso restrito a organizadores";
  redireciona para `dashboard.index`. Tolera usuário nulo (quem trata
  deslogado é o `Authenticate`).
- `config/App.php`: alias `'organizer' => \App\Middleware\Organizer::class`.
- `config/routes.php`: grupo `Route::middleware('organizer')` dentro de
  `auth`, irmão do grupo `admin`, com `GET /organizer`
  (`organizer.dashboard.index`). Import com alias
  `OrganizerDashboardController` para não colidir com os outros
  `DashboardController`.
- `app/Controllers/Organizer/DashboardController.php` (novo): `index()`
  renderiza `organizer/dashboard/index` com título "Painel do
  Organizador".
- `app/views/organizer/dashboard/index.phtml` (novo): "Olá, Nome! Esta
  área só aparece para organizadores."
- `app/views/layouts/_navbar.phtml`: link "Organização" só para
  `isOrganizer()`.
- `database/Populate/populate.php`: usuário `organizer@example.com`,
  senha `123456`, `role => 'organizer'`; mensagem final lista os três.
- Testes (4 arquivos): ver "Como verificar".

Resultado: 13 arquivos, +220 / -2 linhas.

## Por que assim

- Autorização em middleware, não dentro das ações (`grading-path.md`,
  feedback 2025-2 e rubrica 4.1 "Route::middleware('auth')->group").
- Grupo `organizer` irmão do `admin`, ambos dentro de `auth`: cada
  perfil passa só pelo `Authenticate` e pelo seu próprio middleware.
- Perfil novo é valor do ENUM, não coluna booleana: segue o modelo
  existente (`role`) e o padrão de nomes em inglês do banco.
- Middleware tolera `Auth::user() === null`: em `/organizer` a ordem de
  execução é `Organizer` e depois `Authenticate` (o grupo interno roda
  antes), igual à armadilha documentada para `/admin`.
- Controller e view em `app/Controllers/Organizer/` e
  `app/views/organizer/dashboard/`, espelhando o que já existe para
  `Admin` (PSR-4: `App\Controllers\Organizer\DashboardController`).
- Textos de interface em pt-BR; identificadores, tabela e valor do
  ENUM em inglês; commit em inglês no formato `type: Title`.
- Teste em cada camada: unit no model, acesso com Guzzle, controller e
  aceitação (a rubrica pesa testes mais que em 2025-2).
- PSR-12, PHPStan nível 6 e `--strict-psr` sem avisos.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # obrigatório: o schema mudou
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 73 testes, Integration 11, Acceptance 13 (eram
71 / 9 / 9 na base). Testes novos:

- `tests/Unit/Models/Users/UserTest.php`:
  `test_is_organizer_should_return_false_by_default`,
  `test_is_organizer_should_return_true_for_organizer_role`.
- `tests/Integration/Access/OrganizerAccessTest.php` (novo):
  `test_organizer_route_should_redirect_to_login_without_session`.
- `tests/Integration/Controllers/Organizer/DashboardControllerTest.php`
  (novo): `test_index_should_render_organizer_dashboard`.
- `tests/Acceptance/organizer/OrganizerDashboardCest.php` (novo):
  `accessOrganizerWithoutLogin`, `accessOrganizerAsCommonUser`,
  `accessOrganizerAsOrganizer`, `organizerCannotAccessAdmin`.

No navegador: `http://localhost`. Sem login, `/organizer` volta para
`/login`. Logue como `user@example.com` / `123456` e abra `/organizer`:
volta para `/dashboard` com "Acesso restrito a organizadores". Logue
como `organizer@example.com` / `123456`: a navbar mostra "Organização",
`/organizer` abre o "Painel do Organizador" e `/admin` devolve para
`/dashboard` com "Acesso restrito a administradores".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a7-novo-perfil-organizer` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a7 <caminho>/projeto
git fetch fvp-a7 fvp/a7-novo-perfil-organizer
git cherry-pick df21dc9
git remote remove fvp-a7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a7-novo-perfil-organizer:fvp-a7
git cherry-pick df21dc9
git branch -D fvp-a7           # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo; o SHA pode mudar.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo e gera outro SHA (ex.: `a7d4e14`).

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `config/routes.php`: qualquer outro cenário que mexe nas rotas (A2,
  A3, A5, A6, D1, D2, D3, B7). Resolução: manter as duas mudanças; o
  grupo `organizer` continua dentro de `auth` e irmão de `admin`.
- `config/App.php`: A5 (alias `guest`). Manter os dois aliases.
- `app/views/layouts/_navbar.phtml`: A2 (form de logout), A6 (link
  Perfil), C2 (nome na navbar), D1 (link Usuários). Manter todos os
  itens.
- `database/schema.sql`: B3 (`phone`) e B5 (`active`) alteram a mesma
  tabela. Manter as colunas e o ENUM com os três valores.
- `database/Populate/populate.php`: G1, G2, B3, B5. Manter todos os
  usuários; o bloco do organizer entra antes do `echo`.
- `app/Models/User.php` e `tests/Unit/Models/Users/UserTest.php`:
  B1–B6 acrescentam métodos/testes perto dos mesmos pontos; aceitar os
  dois lados.
- Se A1 já estiver aplicado, faça `Organizer` estender `BaseMiddleware`
  e trocar o bloco por `$this->deny('Acesso restrito a organizadores',
  'dashboard.index')`, removendo o `redirectTo` privado.

Depois de aplicar: `./run db:reset && ./run db:populate` (o schema
mudou); rode os testes, `./run phpcs` e `./run phpstan` de novo.

## Armadilhas

- Grupo do organizer dentro do grupo `admin` em vez de irmão: o
  middleware `Admin` roda antes e manda o organizer para `/dashboard`.
- Esquecer `./run db:reset`: os testes passam (migram o schema a cada
  `setUp`), mas no navegador o `save()` do organizer falha com
  "Data truncated for column 'role'" porque o ENUM do banco de
  desenvolvimento ainda não tem o valor.
- Middleware sem o `$user !== null`: deslogado em `/organizer` veria
  "Acesso restrito a organizadores" em vez de "Você deve estar logado".
- Dois `DashboardController` no mesmo `routes.php` exigem `use ... as`
  com alias diferente para cada um.
- `LoginHelper::logout()` clica em `fulano@example.com`; os Cests novos
  não usam `logout()`, então o organizer não precisa desse e-mail.
- Linha de 130 colunas: a rota do organizer com o nome
  `organizer.dashboard.index` chega perto; PHPCS acusa se passar.
