# A1. Centralizar o redirecionamento dos middlewares

Pedido, como o professor faria na hora: "Os middlewares `Admin` e
`Authenticate` têm lógica duplicada de redirecionamento. Centralizem."

Chance: alta (pedido por escrito na avaliação da 4.1, issue #9 do
repositório público). Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A1.

Branch no clone: `fvp/a1-centralizar-redirect-middlewares`. `main`
continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `432adce` | `refactor: Centralize redirect logic in BaseMiddleware` |

## O que mudou

- `app/Middleware/BaseMiddleware.php` (novo): classe abstrata que
  implementa `Core\Http\Middleware\Middleware` e oferece dois métodos
  protegidos: `deny(string $message, string $routeName)` (grava
  `FlashMessage::danger` e redireciona para a rota nomeada) e
  `redirectTo(string $location)` (`header('Location: ...')` + `exit`).
- `app/Middleware/Authenticate.php`: passa a estender `BaseMiddleware`; o
  `if (!Auth::check())` vira uma chamada a `$this->deny('Você deve estar logado
  para acessar essa página', 'users.login')`. Saem o `redirectTo()` privado e
  os imports de `Middleware` e `FlashMessage`.
- `app/Middleware/Admin.php`: mesma coisa, com
  `$this->deny('Acesso restrito a administradores', 'dashboard.index')`.
  Mantido o comentário de que, sem usuário logado, quem redireciona é o
  `Authenticate`.

Resultado: 3 arquivos, +36 / -22 linhas. Cada middleware concreto só
decide quando negar; o como negar fica num lugar só.

## Por que assim

- Atende ao feedback escrito do professor na 4.1 (issue #9 do repo
  público: "os middlewares Admin e Authenticate possuem lógica duplicada
  de redirecionamento. Avaliem como centralizar").
- Autorização continua em middleware, não nas ações do controller
  (`grading-path.md`, feedback 2025-2).
- Classe abstrata em `app/Middleware/`, não em `core/`: é código da
  aplicação, não mudança no framework. PSR-4:
  `App\Middleware\BaseMiddleware` em `app/Middleware/BaseMiddleware.php`.
- Classe abstrata que implementa a interface sem definir `handle` é
  válida em PHP; `handle` fica obrigatório nas filhas.
- Mensagens de interface em pt-BR; identificadores em inglês; commit em
  inglês no formato `type: Title` (Code Standards).
- PSR-12 e PHPStan nível 6 sem avisos; comentário curto em pt-BR na
  classe base explicando a intenção.

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

Resultado obtido: Unit 71 testes, Integration 9, Acceptance 9 (iguais à
base: é refatoração, nenhum teste novo). Os caminhos já são cobertos por
`DashboardAccessTest` (GET `/dashboard` e `/admin` sem sessão → 302
`/login`), `DashboardCest::accessDashboardWithoutLogin` e
`AdminDashboardCest::accessAdminAsCommonUser` (usuário comum em `/admin`
→ `/dashboard` com "Acesso restrito a administradores").

No navegador: `http://localhost`. Sem login, abra `/dashboard`: volta
para `/login` com a mensagem vermelha. Logue como `user@example.com` /
`123456` e abra `/admin`: volta para `/dashboard` com "Acesso restrito a
administradores". Logue como `admin@example.com`: `/admin` abre.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a1-centralizar-redirect-middlewares` (relativo ao
clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a1 <caminho>/projeto
git fetch fvp-a1 fvp/a1-centralizar-redirect-middlewares
git cherry-pick 432adce
git remote remove fvp-a1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a1-centralizar-redirect-middlewares:fvp-a1
git cherry-pick 432adce       # mesmo SHA; o bundle preserva o objeto
git branch -D fvp-a1          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito e o commit
resultante tem o mesmo SHA `432adce` (pai igual, conteúdo igual).

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo; o SHA muda (ex.: `ca05176`) porque a
data do commit é outra.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário: `Authenticate.php` e
`Admin.php`, se o repositório alvo já mexeu no texto das mensagens (C3)
ou na forma de negar (A8, que troca o redirect por `HTTPException` 403).
Nesses casos mantenha a mensagem/comportamento do alvo e só troque a
chamada por `$this->deny(...)` ou `$this->redirectTo(...)`.

Depois de aplicar: rode `./run phpcs`, `./run phpstan` e os testes de
novo. Não há mudança de schema.

## Armadilhas

- PSR-4: o arquivo tem que se chamar exatamente `BaseMiddleware.php`
  dentro de `app/Middleware/`; o `--strict-psr` do composer acusa.
- Não deixe o `use Lib\FlashMessage;` nos filhos depois de remover o
  uso: PHPCS não reclama, mas é lixo que o professor nota.
- `Admin` roda antes de `Authenticate` em `/admin`; o `if ($user !== null`
  tem que continuar lá, senão usuário deslogado recebe "Acesso restrito a
  administradores" em vez de "Você deve estar logado".
- Alternativas que o professor pode pedir: trait `RedirectsTo` em vez de
  classe base; ou mover para `core/Http/Middleware/` (aí é mudança no
  framework, combine antes).
