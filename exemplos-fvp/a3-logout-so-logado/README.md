# A3. Logout só para quem está logado

Pedido, como o professor faria na hora: "Hoje qualquer um pode chamar
`/logout` sem estar logado. Faça essa rota exigir sessão, como o
painel."

Chance: alta (junto com A2). Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A3.

Branch no clone: `fvp/a3-logout-so-logado`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `ce82a9a` | `feat: Restrict logout route to authenticated users` |

## O que mudou

- `config/routes.php`: a rota `GET /logout` (`users.logout`) saiu do
  bloco público e entrou no grupo `Route::middleware('auth')`, antes de
  `/dashboard`. O verbo continua GET; trocar para DELETE é o cenário A2.
- `tests/Integration/Access/LogoutAccessTest.php` (novo): `GET /logout`
  sem sessão responde 302 com `Location: /login`.
- `tests/Acceptance/authentications/LoginCest.php`: novo cenário
  `logoutWithoutLoginRedirectsToLogin` (abre `/logout` sem login, cai em
  `/login` e vê a mensagem do middleware). O cenário `logout` existente
  não mudou e continua passando.

Nenhum controller mudou: `AuthenticationsController::destroy` já só
faz `Auth::logout()` + flash + redirecionar. Quem barra o acesso é o
middleware `Authenticate`, que já existia.

## Por que assim

- Autorização em middleware, não dentro da ação (feedback 2025-2 em
  `grading-path.md`): bastou mover a rota para o grupo `auth`.
- Rota mantém o nome `users.logout`; a navbar usa `route('users.logout')`
  e não precisou mudar.
- Teste de acesso (Guzzle, sem sessão) e teste de aceitação
  (Codeception) cobrem o comportamento novo; rubrica da 4.1 pede
  "rotas autenticadas bloqueadas quando deslogado".
- Comentário na rota em pt-BR; commit em inglês `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 71 testes, Integration 10, Acceptance 10 (eram
71 / 9 / 9 na base). Testes novos:

- `LogoutAccessTest::test_logout_should_redirect_to_login_without_session`
  (`tests/Integration/Access/LogoutAccessTest.php`).
- `LoginCest::logoutWithoutLoginRedirectsToLogin`
  (`tests/Acceptance/authentications/LoginCest.php`).

No navegador: `http://localhost/logout` sem estar logado leva a
`/login` com o aviso "Você deve estar logado para acessar essa
página". Logado (`user@example.com` / `123456`), o menu "Sair"
continua funcionando e mostra "Logout realizado com sucesso!".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a3-logout-so-logado` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a3 <caminho>/projeto
git fetch fvp-a3 fvp/a3-logout-so-logado
git cherry-pick ce82a9a
git remote remove fvp-a3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a3-logout-so-logado:fvp-a3
git cherry-pick ce82a9a          # mesmo SHA; o bundle preserva o objeto
git branch -D fvp-a3             # opcional, limpa a ref
```

Testado: num clone limpo de `86c81ca`, o cherry-pick aplica sem
conflito e reproduz o SHA `ce82a9a`.

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

- `config/routes.php`: qualquer cenário que também mexe nas rotas
  (A2, A5, A6, A7, B7, D1...). O conflito é só de linhas vizinhas;
  mantenha as duas mudanças.
- `tests/Acceptance/authentications/LoginCest.php`: cenários que
  acrescentam métodos no fim do mesmo Cest (C2 altera asserts).

Combinando com A2 (logout via DELETE): aplique A2 e depois A3 (ou o
contrário). O conflito fica em `config/routes.php`: A2 troca
`Route::get('/logout', ...)` por `Route::delete(...)` fora do grupo;
A3 move a rota para dentro de `auth`. Resolução: deixar uma única
linha, `Route::delete('/logout', [AuthenticationsController::class,
'destroy'])->name('users.logout');`, dentro do grupo `auth`. No
`LogoutAccessTest`, troque `$this->client->get('/logout')` por
`$this->client->delete('/logout')`; na aceitação, `amOnPage('/logout')`
passa a dar 404 em vez de redirecionar, então remova esse cenário ou
troque por um teste de acesso com DELETE.

Depois de aplicar: rode os testes e o `./run phpcs` de novo.

## Armadilhas

- A rota precisa ficar dentro do `group` do `auth`; colocar só depois do
  bloco não aplica o middleware.
- Em `/admin`, o middleware `Admin` roda antes do `Authenticate`; para
  `/logout` isso não importa porque a rota está só no grupo `auth`.
- O teste de acesso usa Guzzle contra `http://web:8080` e exige
  `./run up -d` antes (o CI faz isso).
- Mudou o texto do middleware? Atualize `LoginCest`, `DashboardCest` e
  `AdminDashboardCest`, que conferem a mesma string.
