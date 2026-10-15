# B3. Nova coluna no usuário (phone)

Pedido, como o professor faria na hora: "Acrescentem um campo de
telefone no usuário, gravem no banco e mostrem no painel."

Chance: alta. Tempo estimado: 20 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B3.

Branch no clone: `fvp/b3-nova-coluna-phone`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `39ddf49` | `feat: Add phone column to users` |

## O que mudou

- `database/schema.sql`: coluna `phone VARCHAR(20) NULL` na tabela
  `users`, logo depois de `role`.
- `app/Models/User.php`: `'phone'` entra em `$columns` (senão o
  ActiveRecord ignora o campo no INSERT/UPDATE) e
  `@property string|null $phone` no docblock (PHPStan nível 6).
- `database/Populate/populate.php`: os dois usuários de demonstração
  ganham telefone (`(46) 99999-0001` e `-0002`).
- `app/views/dashboard/index.phtml`: linha "Telefone: ..." com
  "não informado" quando a coluna está nula.
- `tests/Unit/Models/Users/UserTest.php`: `test_phone_should_be_null_by_default`
  e `test_phone_should_be_persisted`.
- `tests/Integration/Controllers/DashboardControllerTest.php` (novo):
  painel mostra o telefone do usuário logado e o texto "não informado"
  quando não há telefone.
- `tests/Acceptance/dashboard/DashboardCest.php`: usuário do `_before`
  ganha telefone; `accessDashboardAfterLogin` vê "Telefone: ...".

Resultado: 7 arquivos, +65 / -2 linhas.

## Por que assim

- Ordem do método do catálogo: schema -> model -> populate -> view ->
  teste. Nada de lógica no controller: a view lê
  `$this->currentUser()->phone`.
- Coluna em inglês, snake_case, nullable: usuário antigo continua
  válido e nenhum `new User([...])` dos testes precisa mudar
  (`grading-path.md`, seção de nomes do banco).
- Senha e validações não mudam; `phone` não é obrigatório, então
  `validates()` fica como está. Se o professor pedir obrigatório,
  acrescente `Validations::notEmpty('phone', $this)` e ajuste populate
  e testes.
- Testes em três camadas (unit do model, controller, aceitação), como a
  rubrica pede; nomes descrevem o comportamento.
- PSR-12 e PHPStan 6 limpos; texto de interface em pt-BR; commit em
  inglês `type: Title`.

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

Resultado obtido: Unit 73 testes, Integration 11, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos: `test_phone_should_be_null_by_default`
e `test_phone_should_be_persisted` em `UserTest`;
`test_index_should_show_user_phone` e
`test_index_should_show_placeholder_when_phone_is_empty` em
`DashboardControllerTest`. `DashboardCest::accessDashboardAfterLogin`
passou a conferir o telefone.

No navegador: `http://localhost`, logue com `user@example.com` /
`123456`; o painel mostra "Telefone: (46) 99999-0002". No banco:
`./run db:console` e `SELECT email, phone FROM users;`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/b3-nova-coluna-phone` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b3 <caminho>/projeto
git fetch fvp-b3 fvp/b3-nova-coluna-phone
git cherry-pick 39ddf49
git remote remove fvp-b3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b3-nova-coluna-phone:fvp-b3
git cherry-pick 39ddf49
git branch -D fvp-b3           # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O commit
resultante tem SHA novo (cherry-pick sempre cria outro commit; a
árvore fica idêntica).

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

Arquivos que costumam conflitar neste cenário e por quê:

- `database/schema.sql` e `app/Models/User.php`, se o alvo já tem
  outra coluna nova (A7 `organizer` no ENUM, B5 `active`). Resolva
  mantendo as duas colunas em `schema.sql` e as duas entradas em
  `$columns`.
- `database/Populate/populate.php`, se o alvo mexeu nos usuários de
  demonstração (A7, G1, G2). Mantenha os usuários do alvo e acrescente
  só a chave `'phone'`.
- `tests/Acceptance/dashboard/DashboardCest.php` e
  `app/views/dashboard/index.phtml`, se o alvo já mostra outra coisa
  no painel (B4 último acesso). Mantenha as duas linhas.

Depois de aplicar: `./run db:reset && ./run db:populate` (o banco de
desenvolvimento não se atualiza sozinho); rode os testes e o
`./run phpcs` de novo.

## Armadilhas

- Esqueceu `./run db:reset`: os testes passam (migram o schema a cada
  `setUp`), mas o navegador dá `Unknown column 'phone'`.
- Coluna fora de `$columns`: `$user->phone = '...'` dispara
  `Property phone not found` e o valor nunca vai para o banco.
- Sem `@property string|null $phone` no docblock, o PHPStan nível 6
  acusa acesso a propriedade indefinida nos testes.
- Se a coluna for `NOT NULL` sem default, todo `new User([...])` dos
  testes e do populate precisa informar `phone`. Nullable evita isso.
- `??` na view: `$this->currentUser()->phone ?? 'não informado'`
  cobre nulo; string vazia passaria. Se o formulário puder mandar
  vazio, use `?: 'não informado'`.
