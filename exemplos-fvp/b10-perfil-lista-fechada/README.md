# B10. Perfil validado por lista fechada (`Validations::inclusion`)

Pedido, como o professor faria na hora: "A validação `notEmpty('role')`
não garante que o perfil seja válido. Revise essa regra e a definição do
perfil padrão. Avalie também a necessidade do método `getRole()`, criado
mas não usado."

Chance: alta. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção 9 (complemento 2, issues da Entrega 4.1), B10 / issue
ice-cream-manager #6 (item 7). Relacionado: reservas #15
(`isValidForSignup()`, perfil restrito no cadastro público).

Branch no clone: `fvp/b10-perfil-lista-fechada`. `main` continua igual ao
repositório público (`86c81ca`). Cenário empilhado: parte do commit do H2
(`h2-constantes-perfil`, constantes `User::ROLE_ADMIN` e
`User::ROLE_PARTICIPANT`).

| Commit | Mensagem |
|--------|----------|
| `cb172dd` | `refactor: Replace role magic strings with constants` (H2, base) |
| `a98e9fa` | `feat: Validate user role against a closed list` |

## O que mudou

- `lib/Validations.php`: nova regra
  `inclusion(string $attribute, array $allowed, Model $obj): bool`, no
  estilo das existentes (lê `$obj->$attribute`, registra com
  `addError`). Usa `in_array(..., true)` e grava o erro
  `'não é um valor válido!'`. PHPDoc `@param array<int, string> $allowed`
  e `use Core\Database\ActiveRecord\Model;`.
- `app/Models/User.php`: `public const ROLES = [self::ROLE_ADMIN,
  self::ROLE_PARTICIPANT];` e `validates()` chama
  `Validations::inclusion('role', self::ROLES, $this)` (as demais
  regras ficam). O perfil padrão já vinha do H2 no construtor
  (`self::ROLE_PARTICIPANT` quando `role` é `null`); não foi duplicado.
- `tests/Unit/Lib/Validations/ValidationsTest.php`: três testes de
  `inclusion` com classe anônima `extends Model`.
- `tests/Unit/Models/Users/UserTest.php`: quatro testes de perfil
  (lista `ROLES`, `role` inválido no `save`, `role` vazio no update,
  `admin` e `participant` válidos).

Populate e `AdminDashboardCest` já usam as constantes (H2); nenhum
perfil fora da lista era criado, nada mais precisou mudar.

## Por que assim

- `notEmpty` só diz "tem algum valor"; `'ghost'` passaria. `inclusion`
  diz "o valor está nesta lista". Como a comparação é estrita, `null` e
  `''` também ficam fora, então `notEmpty('role')` vira redundante e não
  é usado.
- Sem a regra, quem segura um perfil inválido é o ENUM do banco: o
  usuário vê erro de PDO (500) em vez de mensagem no formulário. Com a
  regra, `save()` devolve `false` e `errors('role')` traz o texto.
- A lista fechada mora no model (`User::ROLES`): uma fonte só para
  validação, testes, `select` e qualquer `match`. Mudou o conjunto de
  perfis, muda em um lugar (e no ENUM do schema).
- Perfil padrão em um lugar só: o construtor (H2). O `DEFAULT` do ENUM
  continua no banco como última barreira.
- `getRole()` (pedido do item 7): se o projeto tiver um `getRole()`
  nunca chamado, apague. O ActiveRecord do framework já expõe
  `$user->role`; um getter a mais é código morto. A base não tem esse
  método, então não há o que remover aqui.
- PSR-12 e PHPStan 6 sem erros; mensagens em pt-BR; commit em inglês
  `type: Title`.

### Cadastro público (B7)

A base e o H2 não têm cadastro público; o B7 (`b7-cadastro-sign-up`) tem
e não aceita `role` do formulário: monta o `User` com lista explícita de
campos, e o construtor põe o padrão. Com as constantes, o `create` fica:

```php
$user = new User([
    'name' => $params['name'] ?? null,
    'email' => $params['email'] ?? null,
    'password' => $params['password'] ?? null,
    'password_confirmation' => $params['password_confirmation'] ?? null,
]);
$user->role = User::ROLE_PARTICIPANT; // explícito; nunca $params['role']
```

O controller é a primeira barreira (não deixa `user[role]=admin` entrar);
`inclusion` no model é a segunda (nenhum valor fora de `ROLES` chega ao
banco); o ENUM é a terceira. Se pedirem um `select` de perfil no
cadastro, aceite só `ROLE_PARTICIPANT` ali, com a mesma regra em um
método próprio do model:
`Validations::inclusion('role', [self::ROLE_PARTICIPANT], $this)`.

### Relação com outros cenários

- H2 (base): cria as constantes que `ROLES` usa.
- A18 (`a18-redirect-por-perfil-explicito`, em paralelo): `homeRoute()`
  com `match` + `default`. Com B10 o `default` fica para dado antigo do
  banco; perfil novo inválido já não é salvo.
- C8 (`c8-rotulos-perfil-um-lugar`): `ROLE_LABELS` com os rótulos em
  pt-BR. As chaves de `ROLE_LABELS` e os valores de `ROLES` devem ser os
  mesmos; se ambos estiverem no alvo, dá para escrever
  `ROLES = array_keys(self::ROLE_LABELS)` ou testar que coincidem.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 83 testes, Integration 9, Acceptance 9 (eram
76 / 9 / 9 no H2 e 71 / 9 / 9 na base). PHPCS e PHPStan sem erros;
`composer dump-autoload -o --strict-psr` ok. Schema não mudou.

Testes novos:

- `ValidationsTest::test_inclusion_should_accept_allowed_value`
- `ValidationsTest::test_inclusion_should_reject_value_outside_the_list`
- `ValidationsTest::test_inclusion_should_reject_null_and_empty_string`
- `UserTest::test_roles_should_list_admin_and_participant`
- `UserTest::test_save_should_fail_with_invalid_role`
- `UserTest::test_update_should_fail_with_empty_role`
- `UserTest::test_admin_and_participant_roles_should_be_valid`

O teste de perfil padrão (`test_new_user_should_have_participant_role_by_default`)
já vem do H2.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/b10-perfil-lista-fechada`.

Cenário empilhado: o commit do B10 usa `User::ROLE_ADMIN` e
`User::ROLE_PARTICIPANT`, que vêm do H2 (`cb172dd`). Aplique o range
`cb172dd^..a98e9fa` (os dois commits). Se o alvo já tem constantes
equivalentes (H2 aplicado antes, ou o grupo já criou as suas), aplique só
`a98e9fa` e ajuste os nomes se forem outros. Cada cherry-pick cria commit
novo, com SHA diferente dos daqui.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b10 s50/exemplos-fvp/b10-perfil-lista-fechada/projeto
git fetch fvp-b10 fvp/b10-perfil-lista-fechada
git cherry-pick cb172dd^..a98e9fa   # H2 + B10; ou só: git cherry-pick a98e9fa
git remote remove fvp-b10
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/b10-perfil-lista-fechada/solucao.bundle fvp/b10-perfil-lista-fechada:fvp-b10
git cherry-pick cb172dd^..a98e9fa   # o bundle traz os objetos; o cherry-pick cria commits novos
git branch -D fvp-b10               # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/b10-perfil-lista-fechada/solucao.patch       # cria commits novos com as mesmas mensagens
git apply --3way s50/exemplos-fvp/b10-perfil-lista-fechada/solucao.patch   # só o diff, sem commit
```

Se o H2 já estiver no alvo, aplique o patch do B10 sozinho (pule o
primeiro commit com `git am --skip` quando ele falhar por já existir).

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Models/User.php`: A7 (perfil `organizer`: acrescente
  `self::ROLE_ORGANIZER` em `ROLES` e no ENUM), C8 (`ROLE_LABELS` ao lado
  das constantes), A18 (`homeRoute()`), B3, B5, B9, D6 e H1. Mantenha as
  duas mudanças; em `validates()` fique com todas as regras.
- `lib/Validations.php`: outros cenários acrescentam regras no mesmo
  ponto; fique com todas e mantenha o `use ...\Model;`.
- `tests/Unit/Models/Users/UserTest.php` e `ValidationsTest.php`: testes
  novos de outros cenários no mesmo trecho; fique com os dois blocos.
- H2 é a base deste cenário (veja acima).

Depois de aplicar: rode os testes e o `./run phpcs` de novo (não há
mudança de schema).

## Armadilhas

- O terceiro argumento `true` do `in_array`: sem ele a comparação é
  frouxa (`in_array(true, ['admin'])` é `true`).
- Não troque o ENUM por VARCHAR "para facilitar": o banco continua sendo
  a última barreira, e `test_role_constants_should_match_database_enum_values`
  (H2) documenta a ligação.
- Não some `notEmpty('role')` a `inclusion`: daria dois erros
  disputando a mesma chave e o primeiro seria sobrescrito.
- Usuário com `role` fora da lista já salvo no banco (via SQL direto) não
  consegue mais ser atualizado pelo model até corrigir o perfil; é o
  efeito esperado.
- `Validations` não está nos `paths` do PHPStan (só `app`, `core`,
  `tests`); os tipos da assinatura e do PHPDoc valem mesmo assim para
  quem chama.
