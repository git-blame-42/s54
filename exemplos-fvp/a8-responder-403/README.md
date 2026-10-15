# A8. Responder 403 em vez de redirecionar

Pedido, como o professor faria na hora: "Quando um usuário comum
tenta abrir `/admin`, não redirecione para o painel; responda 403
Forbidden."

Chance: baixa. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A8.

Branch no clone: `fvp/a8-responder-403`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `b44267e` | `feat: Respond 403 to non-admin users in admin area` |

## O que mudou

- `app/Middleware/Admin.php`: usuário logado sem perfil admin recebe
  `throw new HTTPException('Acesso restrito a administradores', 403)`.
  Saem o `redirectTo()` privado e os imports de `FlashMessage`; entra
  `Core\Exceptions\HTTPException`. Sem usuário na sessão continua não
  fazendo nada (quem redireciona para `/login` é o `Authenticate`).
- `tests/Unit/App/Middleware/AdminTest.php` (novo, 3 testes): usuário
  comum → exceção com `getStatusCode() === 403` e a mensagem;
  admin → passa; sem sessão → passa.
- `tests/Integration/Access/AdminAccessTest.php` (novo, 2 testes):
  Guzzle com `cookies => true` faz o `POST /login` de verdade e depois
  `GET /admin`: comum recebe 403 com a mensagem no corpo; admin recebe
  200 com "Painel Administrativo". É o único teste de acesso do projeto
  que usa sessão; os outros só provam o 302 sem login.
- `tests/Acceptance/admin/AdminDashboardCest.php`:
  `accessAdminAsCommonUser` agora espera ficar em `/admin`, ver a
  mensagem no `<h1>` e não ver "Painel Administrativo".

Resultado: 4 arquivos, +148 / -11 linhas.

Como o 403 chega ao cliente: `Router::dispatch` chama
`$route->runMiddlewares($request)` antes do controller; a exceção sobe
até o `ErrorsHandler` (registrado em `config/bootstrap.php`), que faz
`header('HTTP/1.1 403 Acesso restrito a administradores')` e imprime
uma página com `<h1>mensagem</h1>` e o stack trace. É o mesmo caminho
do 404 de rota não encontrada.

## Por que assim

- Autorização continua no middleware, não no controller
  (`grading-path.md`, feedback 2025-2).
- Usa a peça que o framework já tem para isso: `HTTPException` +
  `ErrorsHandler`, sem criar handler novo nem mexer em `core/`.
- Semântica HTTP correta: 403 é "autenticado, mas sem permissão";
  302 para `/dashboard` escondia isso. Vale citar na demo a diferença
  entre autenticação (401/redirect para login) e autorização (403),
  que é conceito da rubrica da 4.1.
- Testes nas três camadas (unit do middleware, acesso com sessão,
  aceitação). Nomes descrevem o comportamento.
- PSR-12, PHPStan nível 6, PSR-4 (`Tests\Unit\App\Middleware\AdminTest`
  em `tests/Unit/App/Middleware/AdminTest.php`), mensagem em pt-BR,
  commit em inglês `type: Title`.

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

Resultado obtido: Unit 74 testes, Integration 11, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos: `AdminTest` (3, unit),
`AdminAccessTest` (2, integration); alterado:
`AdminDashboardCest::accessAdminAsCommonUser`.

No navegador: `http://localhost`, logue como `user@example.com` /
`123456` e abra `/admin`: a URL fica em `/admin`, a página mostra
"Acesso restrito a administradores" e o DevTools (aba Network) mostra
status 403. Logue como `admin@example.com`: `/admin` abre normalmente.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a8-responder-403` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a8 <caminho>/projeto
git fetch fvp-a8 fvp/a8-responder-403
git cherry-pick b44267e
git remote remove fvp-a8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a8-responder-403:fvp-a8
git cherry-pick b44267e
git branch -D fvp-a8          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O commit
criado pelo cherry-pick é novo (o SHA normalmente muda, porque a data
de commit é outra); o conteúdo é o mesmo.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário:

- `app/Middleware/Admin.php`, se o alvo já aplicou A1
  (`BaseMiddleware`): fique com `extends BaseMiddleware` e troque o
  `$this->deny(...)` pelo `throw new HTTPException(...)`; o `deny`
  continua servindo ao `Authenticate`. Se o alvo aplicou C3 (textos),
  mantenha o texto do alvo na exceção e nos três testes.
- `tests/Acceptance/admin/AdminDashboardCest.php`, se o alvo mexeu no
  mesmo método (A4 acrescenta métodos ao lado; costuma mesclar sozinho).

Depois de aplicar: rode `./run phpcs`, `./run phpstan` e os testes de
novo. Não há mudança de schema.

## Armadilhas

- `HTTPException` recebe `(mensagem, status)`; o status vem de
  `getStatusCode()`, não de `getCode()` (que fica 0).
- A mensagem vira a "reason phrase" da linha de status HTTP; use só
  ASCII simples nela ("Acesso restrito a administradores" serve).
- A página de erro do `ErrorsHandler` mostra stack trace em qualquer
  `APP_ENV`. Se o professor pedir página amigável, aí sim é mudança
  em `core/Errors/ErrorsHandler.php` (combine antes).
- `accessAdminAsCommonUser` tinha `seeCurrentUrlEquals('/dashboard')`;
  sem ajustar, o Selenium falha porque não há mais redirecionamento.
- No teste de acesso com sessão, `http_errors => false` é obrigatório;
  sem isso o Guzzle lança exceção no 403 antes do `assertEquals`.
- Alternativa que o professor pode pedir: manter o redirect e só
  trocar a mensagem. Aí é o cenário A1/C3, não este.
