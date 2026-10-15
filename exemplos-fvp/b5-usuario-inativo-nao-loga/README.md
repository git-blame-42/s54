# B5. Usuário inativo não consegue logar

Pedido, como o professor faria na hora: "Acrescentem um campo `active`
no usuário. Usuário inativo, mesmo com a senha certa, não entra."

Chance: média. Tempo estimado: 25 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B5.

Branch no clone: `fvp/b5-usuario-inativo-nao-loga`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `cee5f93` | `feat: Block login of inactive users` |

## O que mudou

- `database/schema.sql`: coluna `active TINYINT(1) NOT NULL DEFAULT 1`
  logo depois de `role`.
- `app/Models/User.php`: `'active'` em `$columns`; `@property int|string
  $active` no docblock; o construtor põe `active = 1` em usuário novo
  (o `INSERT` do `Model` envia todas as colunas, então o `DEFAULT 1` do
  banco não é usado); método `isActive(): bool` com cast, porque o PDO
  devolve TINYINT como string `"1"`/`"0"`.
- `app/Controllers/AuthenticationsController.php`: o `if` do
  `authenticate` vira três ramos: credencial errada ("E-mail ou senha
  inválidos!"), usuário inativo ("Usuário inativo. Procure a
  administração.") e sucesso. Inativo nunca chega ao `Auth::login`.
- `database/Populate/populate.php`: terceiro usuário
  `inativo@example.com` / `123456` com `active => 0`, para a demo.
- `tests/Unit/Models/Users/UserTest.php`: `isActive` verdadeiro por
  padrão; falso depois de `update(['active' => 0])` e recarregar.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  inativo com senha certa recebe `Location: /login` e `Auth::check()`
  continua falso.
- `tests/Acceptance/authentications/LoginCest.php`: cria o inativo no
  `_before`; cenário `loginWithInactiveUser` vê a mensagem e fica em
  `/login`.

Resultado: 7 arquivos, +74 / -6 linhas.

## Por que assim

- A regra fica no model (`isActive`) e o controller só decide o fluxo:
  controller fino, lógica no model (rubrica 4.2, `grading-path.md`).
- Mensagem distinta para inativo: o professor costuma pedir "por que não
  entrou?" na demo; com a mensagem separada o cenário se explica sozinho.
  Se ele preferir não revelar que a conta existe, junte os dois ramos
  e use só "E-mail ou senha inválidos!".
- Parâmetros continuam vindo só de `$request->getParam('user')`
  (feedback 2025-2).
- Coluna em inglês, `snake_case`, com default no schema (Entrega 3).
- Testes nas três camadas que a rubrica pesa: unit do model, controller
  e aceitação. Nomes descrevem o comportamento.
- PSR-12, PHPStan nível 6 (`@property` novo no docblock), commit em
  inglês `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # schema mudou: obrigatório
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 73 testes, Integration 10, Acceptance 10 (eram
71 / 9 / 9 na base). Testes novos:

- `UserTest::test_is_active_should_return_true_by_default`
- `UserTest::test_is_active_should_return_false_after_deactivation`
- `AuthenticationsControllerTest::test_authenticate_inactive_user_should_not_login`
- `LoginCest::loginWithInactiveUser`

No navegador: `http://localhost/login`, entre com
`inativo@example.com` / `123456`: volta para `/login` com "Usuário
inativo. Procure a administração." Depois `user@example.com` /
`123456`: entra normalmente. No banco (`./run db:console`):
`SELECT email, active FROM users;` mostra `0` só para o inativo.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/b5-usuario-inativo-nao-loga` (relativo ao clone do
s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b5 <caminho>/projeto
git fetch fvp-b5 fvp/b5-usuario-inativo-nao-loga
git cherry-pick cee5f93
git remote remove fvp-b5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b5-usuario-inativo-nao-loga:fvp-b5
git cherry-pick cee5f93
git branch -D fvp-b5          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo; o SHA do seu repositório pode ser
outro.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit com a mesma mensagem
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

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Models/User.php`: B1 e B2 mexem em `validates()` (bloco vizinho
  do construtor); B3 e A7 mexem na mesma linha de `$columns` e no
  docblock. Resolução: manter as duas colunas/regras, uma por linha.
- `database/schema.sql`: B3 (`phone`) e A7 (ENUM com `organizer`)
  acrescentam linhas no mesmo `CREATE TABLE`. Resolução: manter todas.
- `app/Controllers/AuthenticationsController.php`: A4 troca o destino
  do redirect de sucesso. Resolução: manter os três ramos deste cenário
  e, no ramo de sucesso, o destino do A4 (`isAdmin()` ? admin : dashboard).
- `database/Populate/populate.php`, `LoginCest.php`,
  `AuthenticationsControllerTest.php`, `UserTest.php`: outros cenários
  acrescentam usuários ou testes no mesmo ponto. Resolução: manter os
  dois blocos.

Depois de aplicar: `./run db:reset && ./run db:populate` (schema
mudou), rode os testes e o `./run phpcs` de novo.

## Armadilhas

- Esqueceu `./run db:reset`: o navegador dá `Unknown column 'active'`
  e os testes passam (eles recriam o schema a cada `setUp`).
- Coluna nova fora de `$columns`: `Property active not found` ao
  atribuir, e a coluna nunca é gravada.
- O `INSERT` do `Model` envia todas as colunas; sem o `active = 1` no
  construtor, usuário novo vai com `NULL` para uma coluna `NOT NULL`
  e o MySQL recusa. Por isso o default fica no construtor, igual ao
  `role`.
- `TINYINT` chega como string: `$this->active === 1` seria falso; use
  `(bool)` ou compare com `'1'`.
- `update(['active' => 0])` não passa por `validates()`; `save()` sim.
  Para desativar em teste, `update` é o caminho curto.
- A ordem dos ramos importa: primeiro credencial, depois `isActive`.
  Invertendo, um atacante descobre quais e-mails existem sem saber a
  senha.
