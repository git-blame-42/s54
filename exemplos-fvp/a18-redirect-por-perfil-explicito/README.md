# A18. Destino pós-login por perfil, com perfil desconhecido explícito

Pedido, como o professor faria na hora: "Revise o redirecionamento após
a autenticação: use um nome mais representativo, simplifique as
condicionais e trate explicitamente os perfis desconhecidos. Evite
assumir que um usuário que não é administrador necessariamente é
cliente."

Chance: média. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção 9 (complemento 2, issues da Entrega 4.1), A18 / issue
ice-cream-manager #6 (itens 2 e 6).

Branch no clone: `fvp/a18-redirect-por-perfil-explicito`. `main`
continua igual ao repositório público (`86c81ca`). Cenário empilhado:
parte do commit do H2 (`h2-constantes-perfil`, constantes
`User::ROLE_ADMIN` e `User::ROLE_PARTICIPANT`).

| Commit | Mensagem |
|--------|----------|
| `cb172dd` | `refactor: Replace role magic strings with constants` (H2, base) |
| `2af0b9f` | `feat: Redirect each role to its home route after login` |

## O que mudou

- `app/Models/User.php`: método novo `homeRoute(): string`, com `match`
  sobre `$this->role`: `ROLE_ADMIN` → `'admin.dashboard.index'`,
  `ROLE_PARTICIPANT` → `'dashboard.index'` e
  `default => throw new \DomainException("Perfil desconhecido: {$this->role}")`.
- `app/Controllers/AuthenticationsController.php`: no ramo de sucesso de
  `authenticate`, `$home = route($user->homeRoute())` é calculado antes
  de `Auth::login($user)`; depois vêm o flash de sucesso e
  `redirectTo($home)`. Nada de `isAdmin() ? ... : ...`.
- `tests/Unit/Models/Users/UserTest.php`: dois testes de `homeRoute`
  (em memória, sem `save()`).
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  admin vai para `/admin`; perfil desconhecido lança `DomainException`
  e não abre sessão. O participante indo para `/dashboard` já era
  coberto por `test_authenticate_with_valid_credentials_should_login`.
- `tests/Acceptance/admin/AdminDashboardCest.php`: login de admin cai em
  `/admin` e login de participante cai em `/dashboard`.

## Por que assim

- Item 2 da issue: "nome mais representativo, condicionais simples".
  `homeRoute()` diz o que devolve, e o `match` é uma tabela de
  perfil → rota, sem `if/else` aninhado.
- Item 6: "não assumir que quem não é admin é cliente". O ternário do A4
  (`isAdmin() ? admin : dashboard`) manda qualquer perfil não-admin para
  `/dashboard`, inclusive um perfil que ainda nem existe. Com `match`,
  cada perfil conhecido aparece escrito, e o `default` lança exceção.
- Por que `match` com `default` que lança, e não `if/else`: o `match`
  compara com `===` (sem conversão de tipo), é uma expressão (devolve
  direto) e, se nenhum braço casar, ou cai no `default` explícito ou
  lança `UnhandledMatchError`. Em um `if/else` o último `else` vira o
  "resto" sem que ninguém perceba; quando o A7 criar `organizer`, ele
  cairia calado na área do participante. Aqui o perfil novo sem rota
  quebra na hora, em teste.
- Por que exceção, e não flash: `role` é `ENUM('admin', 'participant')`
  no banco. Um valor fora disso é erro de programação (perfil novo sem
  rota), não erro de quem está digitando. O `ErrorsHandler` mostra a
  página 500, e o teste captura a `DomainException`. Foi usada a
  `\DomainException` do PHP (valor fora do domínio válido) em vez de uma
  classe própria: não é preciso arquivo novo e nenhum código trata essa
  exceção de forma diferente das outras.
- O destino é calculado antes de `Auth::login`. Assim, se o perfil for
  desconhecido, a exceção sai antes de criar a sessão, e ninguém fica
  logado com um perfil que nenhum middleware conhece. A exceção não é
  capturada no controller.
- Alternativa defensiva, se o professor pedir mensagem em vez de 500:
  envolver a linha do `$home` em `try { ... } catch (\DomainException)`
  com `FlashMessage::danger('Perfil de usuário desconhecido. Procure o
  administrador.')` e `redirectTo(route('users.login'))`, sem chamar
  `Auth::login`. Não foi adotada porque esconderia um erro de
  programação.
- "Não autenticado" (também no item 2) não passa por `homeRoute`: quem
  erra a senha continua no `else` com "E-mail ou senha inválidos!", e as
  rotas protegidas continuam com o middleware `auth`.
- Relação com outros cenários:
  - A4 (`a4-admin-cai-em-admin-apos-login`) resolve só "admin cai em
    `/admin`", com o ternário. Este cenário substitui o ternário. Dele
    foram aproveitados o nome e a estrutura do teste de controller do
    admin e o Cest de login do admin. Não aplique os dois juntos.
  - H2 é a base: o `match` usa `self::ROLE_ADMIN`/`self::ROLE_PARTICIPANT`.
  - B10 (`b10-perfil-lista-fechada`) valida o perfil por lista fechada
    na gravação. A18 trata o perfil na leitura (destino). Juntos, os
    dois fecham o assunto: o perfil inválido não entra e, se um perfil
    novo entrar sem rota, a falha aparece em vez de ser tratado como
    participante.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 78 testes, Integration 11, Acceptance 11 (eram
76 / 9 / 9 na base H2). PHPCS e PHPStan sem erros; `composer
dump-autoload -o --strict-psr` ok. O schema não mudou.

Testes novos:

- `UserTest::test_home_route_should_depend_on_role`
- `UserTest::test_home_route_should_fail_with_unknown_role`
- `AuthenticationsControllerTest::test_authenticate_admin_should_redirect_to_admin`
- `AuthenticationsControllerTest::test_authenticate_with_unknown_role_should_fail_without_login`
- `AdminDashboardCest::loginAsAdminShouldLandOnAdminDashboard`
- `AdminDashboardCest::loginAsParticipantShouldLandOnUserDashboard`

No navegador (`./run db:populate` antes): `http://localhost/login` com
`admin@example.com` / `123456` cai em `/admin` ("Painel Administrativo"); com
`user@example.com` / `123456` cai em `/dashboard`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/a18-redirect-por-perfil-explicito`.

Cenário empilhado: o `match` usa as constantes do H2. Se o alvo ainda
não tem `User::ROLE_ADMIN`/`ROLE_PARTICIPANT`, aplique o range
`cb172dd^..2af0b9f` (os dois commits, H2 e A18). Se o alvo já tem
constantes equivalentes (H2 aplicado antes ou feito à mão), aplique só
`2af0b9f`. Em qualquer caso, o cherry-pick cria commits novos, com SHAs
diferentes dos daqui.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a18 s50/exemplos-fvp/a18-redirect-por-perfil-explicito/projeto
git fetch fvp-a18 fvp/a18-redirect-por-perfil-explicito
git cherry-pick cb172dd^..2af0b9f   # H2 + A18; ou só: git cherry-pick 2af0b9f
git remote remove fvp-a18
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/a18-redirect-por-perfil-explicito/solucao.bundle fvp/a18-redirect-por-perfil-explicito:fvp-a18
git cherry-pick cb172dd^..2af0b9f   # o bundle traz os objetos; o cherry-pick cria commits novos
git branch -D fvp-a18               # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/a18-redirect-por-perfil-explicito/solucao.patch       # cria commits novos com as mesmas mensagens
git apply --3way s50/exemplos-fvp/a18-redirect-por-perfil-explicito/solucao.patch   # só o diff, sem commit
```

Se o alvo já tiver o H2, o primeiro patch do `git am` vai falhar ou
ficar vazio: use `git am --skip` nele e siga com o do A18.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `AuthenticationsController::authenticate`: também é mexido por A4, A9,
  A11, A13, A16, B5, B9, C3, C4 e H1. Com A4, fique com a versão deste
  cenário (troque o ternário por `route($user->homeRoute())`). Com os
  outros, mantenha o que eles acrescentaram e deixe o `$home` calculado
  logo antes de `Auth::login` (ou do `Auth::attempt` de H1). Com A11
  (voltar à página pedida), o `$home` vira o destino padrão quando não
  há página guardada.
- `app/Models/User.php`: A7, B3, B5, B9, B10, D6 e H1 mexem no model.
  Mantenha os dois lados. Com A7 (`organizer`), acrescente um braço
  `self::ROLE_ORGANIZER => '<rota do organizador>'` no `match`. Sem
  esse braço, o login do organizador lança `DomainException`, e é
  justamente isso que este cenário quer.
- `UserTest.php`, `AuthenticationsControllerTest.php` e
  `AdminDashboardCest.php`: outros cenários (A4, F4, D*) acrescentam
  testes no mesmo trecho. Fique com os dois blocos. Se A4 já estiver
  aplicado, o teste do admin e o Cest de login do admin ficam
  duplicados: mantenha só uma cópia.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (não há
mudança de schema).

## Armadilhas

- O unitário do perfil inválido não pode chamar `save()`: o ENUM
  rejeita o valor antes e `role` nem chega ao `match`. Atribua e teste
  em memória.
- No teste de integração do perfil desconhecido, a coluna é trocada
  para `VARCHAR` com `Database::exec("ALTER TABLE ...")`, só para
  gravar `'ghost'`. O banco de teste é recriado a cada teste
  (`TestCase::setUp`/`tearDown`), então a mudança não vaza.
- Se `Auth::login` vier antes do `homeRoute()`, a exceção sai com a
  sessão já criada: o usuário vê o 500 e fica logado. Mantenha a ordem.
- `match` sem `default` também lança (`UnhandledMatchError`), mas com
  mensagem genérica. O `default` explícito deixa a intenção escrita e
  mostra o perfil na mensagem.
- `homeRoute()` devolve o nome da rota, não a URL. Quem chama usa
  `route(...)`. Assim o model não depende do roteador.
