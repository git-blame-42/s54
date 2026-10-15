# A4. Admin cai em /admin depois do login

Pedido, como o professor faria na hora: "Quando o administrador faz
login, ele deve ir direto para o painel administrativo, não para o
painel do usuário."

Chance: alta. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A4.

Branch no clone: `fvp/a4-admin-cai-em-admin-apos-login`. `main`
continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `5e1eeb1` | `feat: Redirect admin to admin dashboard after login` |

## O que mudou

- `app/Controllers/AuthenticationsController.php`: em `authenticate()`,
  depois de `Auth::login($user)` e do flash de sucesso, o destino passa a
  depender de `$user->isAdmin()`: `route('admin.dashboard.index')` para
  admin, `route('dashboard.index')` para os demais. Um comentário curto
  em pt-BR explica a decisão.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  novo `test_authenticate_admin_should_redirect_to_admin_dashboard`
  (cria um admin, faz o POST e espera `Location: /admin`, não
  `/dashboard`).
- `tests/Acceptance/admin/AdminDashboardCest.php`: dois cenários novos,
  `loginAsAdminShouldLandOnAdminDashboard` (admin loga e já está em
  `/admin` com "Painel Administrativo" no `<h1>`) e
  `loginAsCommonUserShouldLandOnUserDashboard` (usuário comum continua
  caindo em `/dashboard`).

Resultado: 3 arquivos, +45 / -1 linhas.

## Por que assim

- A decisão de destino usa o método `isAdmin()` que já existe no model,
  em vez de comparar `role === 'admin'` no controller: a regra fica num
  lugar só (`grading-path.md`: controllers finos, lógica no model).
- O helper `route('nome')` resolve a URL pelas rotas nomeadas de
  `config/routes.php`; nenhum caminho fixo no controller.
- A autorização de `/admin` continua no middleware `Admin`; o
  redirecionamento é só conveniência pós-login e não substitui o
  middleware.
- Testes nas duas camadas que a rubrica pesa: teste de controller (fluxo
  da ação) e aceitação (o que o usuário vê). O teste existente
  `test_authenticate_with_valid_credentials_should_login` segue válido
  porque usa usuário comum.
- Texto de interface em pt-BR, identificadores em inglês, commit em
  inglês no formato `type: Title`.

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

Resultado obtido: Unit 71 testes, Integration 10, Acceptance 11 (eram
71 / 9 / 9 na base). Testes novos:

- `test_authenticate_admin_should_redirect_to_admin_dashboard` em
  `tests/Integration/Controllers/AuthenticationsControllerTest.php`.
- `loginAsAdminShouldLandOnAdminDashboard` e
  `loginAsCommonUserShouldLandOnUserDashboard` em
  `tests/Acceptance/admin/AdminDashboardCest.php`.

No navegador: `http://localhost/login`. Entre com `admin@example.com` /
`123456`: a URL vira `/admin` e aparece "Painel Administrativo" com a
mensagem verde de login. Saia e entre com `user@example.com` / `123456`:
cai em `/dashboard` como antes.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a4-admin-cai-em-admin-apos-login` (relativo ao clone
do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a4 <caminho>/projeto
git fetch fvp-a4 fvp/a4-admin-cai-em-admin-apos-login
git cherry-pick 5e1eeb1
git remote remove fvp-a4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a4-admin-cai-em-admin-apos-login:fvp-a4
git cherry-pick 5e1eeb1       # o bundle traz o commit original
git branch -D fvp-a4          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O commit
resultante é novo (SHA próprio, data de hoje); o conteúdo é o mesmo.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo num clone de `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário:

- `app/Controllers/AuthenticationsController.php`, se o alvo já mudou o
  bloco do login (B5, usuário inativo, troca a condição do `if`; C3 troca
  o texto do flash). Resolução: mantenha a condição/texto do alvo e
  acrescente só o trecho do `$target`.
- `tests/Acceptance/admin/AdminDashboardCest.php`, se o alvo acrescentou
  outros cenários no mesmo ponto (A8, D1). Resolução: mantenha todos os
  métodos; a ordem não importa.

Depois de aplicar: rode os testes e o `./run phpcs` de novo. Não há
mudança de schema.

## Armadilhas

- `AdminDashboardCest::accessAdminAsAdmin` faz login e depois
  `amOnPage('/admin')`; continua válido, só fica redundante.
- `LoginCest::loginWithValidCredentials` usa usuário comum e espera
  `/dashboard`; não mexa nele.
- Se o professor pedir que o admin também possa ir para `/dashboard`, o
  link "Painel" da navbar já faz isso; não remova a rota.
- Não compare `$user->role === 'admin'` no controller; use `isAdmin()`.
  Se um dia surgir outro perfil (A7), o método é o único ponto a tocar.
