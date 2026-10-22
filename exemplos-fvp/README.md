# Exemplos de FVP: uma solução pronta por cenário

Cada subdiretório é um cenário do catálogo
`fvp-possiveis-modificacoes.md`, resolvido de verdade sobre o código da
Entrega 4.1 (`main` do repositório público, commit `86c81ca`), com
testes, PHPCS, PHPStan e PSR-4 verdes. Serve para dois usos:

- Estudar antes do laboratório: ler o README de cada cenário, o diff
  (`solucao.patch`) e o código em `projeto/`.
- Aplicar na hora: trazer a solução para o repositório da equipe com
  `git cherry-pick` (abaixo).

## O que há em cada cenário

```
<id>-<slug>/
  README.md        pedido, o que mudou, por quê, como verificar,
                   como fazer cherry-pick, conflitos prováveis
  solucao.patch    git format-patch dos commits do cenário (git am)
  solucao.bundle   bundle completo: main do público + branch fvp/<id>-<slug>
  projeto/         clone do público com a solução aplicada (sem .git,
                   sem vendor; ver "Recriar o clone")
```

`h3-justificar-escolha-a1/` só tem README: é uma resposta oral.

## Cherry-pick em três formas

Pré-requisito: o repositório alvo descende de `86c81ca` (ou de algo
compatível). `<caminho>` é `s50/exemplos-fvp/<cenario>`.

1. Pelo bundle (recomendado; funciona direto do s50, offline):

   ```
   cd /caminho/do/repositorio/da/equipe
   git fetch <caminho>/solucao.bundle fvp/<cenario>:fvp-tmp
   git log --oneline main..fvp-tmp        # veja os SHAs
   git cherry-pick <sha>                  # um commit
   git cherry-pick <sha1>^..<shaN>        # intervalo, na ordem
   git branch -D fvp-tmp
   ```

2. Pelo patch (`git am` cria commits novos com as mesmas mensagens):

   ```
   git am <caminho>/solucao.patch
   git apply --3way <caminho>/solucao.patch   # só o diff, sem commit
   ```

3. Pelo clone `projeto/` (depois de `recriar.sh`, abaixo):

   ```
   git remote add fvp <caminho>/projeto
   git fetch fvp fvp/<cenario>
   git cherry-pick <sha>
   git remote remove fvp
   ```

Cherry-pick e `git am` criam commits novos (SHA diferente); o bundle
traz os objetos originais, então os SHAs citados nos READMEs valem para
o `git cherry-pick`.

Cenários empilhados (D2, D6, D7 e E1 sobre D1; C8 sobre D6; A10 sobre A9;
A12 e F2 sobre A1; D8 sobre D3; A18 e B10 sobre H2): o bundle traz a base
junto. Se o alvo já tem a base, pegue só o SHA do cenário; se não tem,
pegue o intervalo.

Conflito: `git status`, edite os marcadores, `git add`, `git
cherry-pick --continue` (ou `git am --continue`); para desistir,
`--abort`. Cada README lista os arquivos que costumam conflitar com os
outros cenários.

## Recriar o clone

```
cd s50/exemplos-fvp
./recriar.sh a1-centralizar-redirect-middlewares   # ou --todos
cd a1-centralizar-redirect-middlewares/projeto
cp .env.example .env
./run composer install && ./run up -d
./run db:reset && ./run db:populate
./run test tests/Unit && ./run test tests/Integration && ./run test:browser
./run phpcs && ./run phpstan
```

`recriar.sh` devolve o `.git` a partir do bundle (offline); `vendor/`
vem do composer. Em cada clone, `main` é o público e `fvp/<cenario>` é
a solução.

## Como foram verificados

Cada cenário rodou, antes do commit: `composer dump-autoload
--strict-psr`, `./run phpcs`, `./run phpstan`, `./run test tests/Unit`,
`./run test tests/Integration`, `./run test:browser` (Selenium),
e `db:reset` + `db:populate` quando mexe em schema ou populate. Depois,
o bundle de cada cenário foi aplicado por cherry-pick num clone limpo
de `86c81ca`. A contagem de testes está no README de cada um (base:
Unit 71, Integration 9, Acceptance 9).

## Índice

<!-- indice -->
| Cenário | Diretório | Commits (SHA e mensagem) |
|---|---|---|
| A1. Centralizar o redirecionamento dos middlewares | `a1-centralizar-redirect-middlewares/` | `432adce` refactor: Centralize redirect logic in BaseMiddleware |
| A10. Manter o e-mail digitado depois de uma falha | `a10-manter-email-apos-falha/` | `1819996` fix: Handle empty login form without HTML5 validation<br>`0d13c9e` feat: Keep typed email after failed login |
| A11. Voltar para a página pedida depois do login | `a11-voltar-pagina-pedida/` | `aec5213` feat: Redirect to the requested page after login |
| A12. Middleware Admin autossuficiente (ordem dos middlewares) | `a12-admin-autossuficiente/` | `432adce` refactor: Centralize redirect logic in BaseMiddleware<br>`2cdd9cd` refactor: Make Admin middleware self-sufficient |
| A13. Regenerar o id da sessão no login (session hijacking) | `a13-regenerar-sessao-login/` | `3a11792` fix: Regenerate session id on login and destroy it on logout |
| A14. Expirar a sessão por inatividade | `a14-expirar-sessao-inatividade/` | `d67688a` feat: Expire session after inactivity |
| A15. Token CSRF no formulário de login | `a15-token-csrf-login/` | `862b31d` feat: Protect login form with CSRF token |
| A16. Validar os parâmetros do login sem `?? ''` | `a16-validar-parametros-login/` | `906f688` fix: Validate login params before querying the user |
| A17. Corrigir a ordem dos middlewares no core | `a17-ordem-middlewares-core/` | `9a4778e` fix: Run outer middleware group before inner groups |
| A18. Destino pós-login por perfil, com perfil desconhecido explícito | `a18-redirect-por-perfil-explicito/` | `cb172dd` refactor: Replace role magic strings with constants<br>`2af0b9f` feat: Redirect each role to its home route after login |
| A2. Logout via DELETE | `a2-logout-via-delete/` | `508079e` feat: Logout via DELETE request |
| A3. Logout só para quem está logado | `a3-logout-so-logado/` | `ce82a9a` feat: Restrict logout route to authenticated users |
| A4. Admin cai em /admin depois do login | `a4-admin-cai-em-admin-apos-login/` | `5e1eeb1` feat: Redirect admin to admin dashboard after login |
| A5. Middleware guest: logado não vê /login | `a5-middleware-guest/` | `96c8446` feat: Add guest middleware to hide login from authenticated users |
| A6. Nova página restrita (ex.: /profile) | `a6-nova-pagina-restrita-profile/` | `00be668` feat: Add profile page for authenticated users |
| A7. Novo perfil (ex.: organizer) com área própria | `a7-novo-perfil-organizer/` | `df21dc9` feat: Add organizer role with its own dashboard |
| A8. Responder 403 em vez de redirecionar | `a8-responder-403/` | `b44267e` feat: Respond 403 to non-admin users in admin area |
| A9. Login com campos vazios: novalidate e controller robusto | `a9-login-campos-vazios/` | `1819996` fix: Handle empty login form without HTML5 validation |
| B1. Senha com no mínimo N caracteres | `b1-senha-minimo-caracteres/` | `19021f1` feat: Validate minimum password length |
| B2. E-mail em formato válido | `b2-email-formato-valido/` | `b558747` feat: Validate email format |
| B3. Nova coluna no usuário (ex.: phone) | `b3-nova-coluna-phone/` | `39ddf49` feat: Add phone column to users |
| B4. Mostrar o último acesso no painel | `b4-ultimo-acesso-no-painel/` | `ad66fa0` feat: Show previous login on user dashboard |
| B5. Usuário inativo não consegue logar | `b5-usuario-inativo-nao-loga/` | `cee5f93` feat: Block login of inactive users |
| B6. Trocar a senha de um usuário existente | `b6-trocar-senha/` | `8fac90f` feat: Allow users to change their password |
| B7. Cadastro de usuário (sign up) | `b7-cadastro-sign-up/` | `627f95b` feat: Add user sign up |
| B8. Editar o próprio perfil (nome e e-mail) com save() | `b8-editar-proprio-perfil/` | `048eec9` feat: Allow users to edit their own profile |
| B9. Bloquear a conta depois de N tentativas erradas | `b9-bloquear-conta-tentativas/` | `7bc6f62` feat: Lock account after repeated failed logins |
| B10. Perfil validado por lista fechada no model | `b10-perfil-lista-fechada/` | `cb172dd` refactor: Replace role magic strings with constants<br>`a98e9fa` feat: Validate user role against a closed list |
| C1. Novo tipo de flash (warning, info) | `c1-novo-tipo-flash/` | `0dd5970` feat: Add warning and info flash message types |
| C2. Nome em vez de e-mail na navbar; link Perfil | `c2-nome-na-navbar/` | `0b7ef7c` feat: Show user name instead of email in navbar |
| C3. Trocar textos de mensagens | `c3-trocar-textos-mensagens/` | `0ef5708` chore: Update login and validation messages |
| C4. Layout próprio para a tela de login | `c4-layout-login/` | `137b6c0` feat: Use dedicated layout for login page |
| C5. Escapar saída nas views (XSS) | `c5-escapar-saida-xss/` | `7365dfd` fix: Escape user data in views |
| C6. Miudezas que o professor pede como "alteração pontual" | `c6-miudezas/` | `ed929f8` feat: Redirect to home after logout<br>`d6b53ab` feat: Distinguish unknown email from wrong password on login<br>`6b5b91e` feat: Use page title in the document title<br>`05bc3fb` feat: Show admin-only content on user dashboard<br>`1e6ea64` feat: Redirect authenticated users from home to dashboard<br>`4aa27c0` refactor: Rename participant role to user<br>`ee55056` feat: Show member since date on profile |
| C7. Flash: escapar a mensagem e diferenciar os tipos | `c7-flash-escape-tipos/` | `94e69f5` fix: Escape flash messages and style them by type |
| C8. Rótulos de perfil num só lugar | `c8-rotulos-perfil-um-lugar/` | `7c8d56c` feat: List users with pagination in admin area<br>`14c7193` feat: Allow admin to change user role<br>`a80220e` refactor: Keep role labels in a single place |
| D1. Listar usuários no admin com paginação | `d1-listar-usuarios-admin-paginacao/` | `7c8d56c` feat: List users with pagination in admin area |
| D2. Admin remove um usuário | `d2-admin-remove-usuario/` | `7c8d56c` feat: List users with pagination in admin area<br>`14e924c` feat: Allow admin to remove users |
| D3. CRUD mínimo de um recurso novo (ex.: events) | `d3-crud-events/` | `5293195` feat: Add events table and Event model<br>`34a4191` feat: Add events CRUD for authenticated users<br>`8f658a5` test: Cover events CRUD with controller, access and acceptance tests |
| D4. Busca por nome ou e-mail | `d4-busca-por-nome-email/` | `783330d` feat: Search users by name or email |
| D5. Contadores com all() e where() no painel administrativo | `d5-contadores-painel-admin/` | `dc2f0b7` feat: Show user counters on admin dashboard |
| D6. Admin altera o perfil de um usuário (promover/rebaixar) | `d6-admin-altera-perfil/` | `7c8d56c` feat: List users with pagination in admin area<br>`14c7193` feat: Allow admin to change user role |
| D7. Ordenar a listagem | `d7-ordenar-listagem/` | `7c8d56c` feat: List users with pagination in admin area<br>`7584d77` feat: Order users listing by name |
| D8. Só o dono vê e edita (prévia 4.2) | `d8-so-dono-ve-edita/` | `5293195` feat: Add events table and Event model<br>`34a4191` feat: Add events CRUD for authenticated users<br>`8f658a5` test: Cover events CRUD with controller, access and acceptance tests<br>`30a59e0` test: Prove event ownership rules |
| D9. SQL só no model, nunca no controller | `d9-sql-so-no-model/` | `630c5a6` refactor: Move user queries into named model methods |
| E1. Resposta JSON para uma listagem | `e1-resposta-json-listagem/` | `7c8d56c` feat: List users with pagination in admin area<br>`3dcef1d` feat: Return JSON for admin users listing |
| F1. Escrever o teste que falta para um cenário | `f1-testes-que-faltam/` | `f573233` test: Add missing controller, access and middleware tests for authentication |
| F2. Teste unitário de middleware | `f2-teste-unitario-middleware/` | `432adce` refactor: Centralize redirect logic in BaseMiddleware<br>`42bc22a` test: Add unit tests for Authenticate and Admin middlewares |
| F3. LoginHelper::logout() sem e-mail fixo | `f3-loginhelper-sem-email-fixo/` | `7ef4f43` test: Make LoginHelper logout independent of the user email |
| F4. Testes que faltam no UserTest | `f4-testes-usertest/` | `bca2f64` test: Cover User validations and password hashing |
| G1. Novo usuário de demonstração ou outras credenciais | `g1-novo-usuario-demo/` | `54da0e0` chore: Add demo users to populate script |
| G2. Gerar vários usuários (para ver paginação) | `g2-gerar-varios-usuarios/` | `7982012` chore: Generate sample participants in populate script |
| G3. Nova tarefa no script run | `g3-nova-tarefa-run/` | `de8a469` chore: Add db:seed and ci tasks to run script |
| G4. Populate em classes, como no problem-track | `g4-populate-em-classes/` | `180d2a6` refactor: Move populate logic into classes |
| H1. Auth::attempt(): autenticação encapsulada em Auth | `h1-auth-attempt/` | `9c78829` refactor: Encapsulate login attempt in Auth::attempt |
| H2. Constantes para o perfil (sem strings mágicas) | `h2-constantes-perfil/` | `cb172dd` refactor: Replace role magic strings with constants |
| H3. Como justificar a escolha em A1 | `h3-justificar-escolha-a1/` | (sem código; só README) |
<!-- /indice -->
