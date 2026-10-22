# C8. Rótulos de perfil num só lugar (`User::ROLE_LABELS`)

Pedido, como o professor faria na hora: "O texto 'Administrador' está
no model e de novo na view. Deixe os rótulos de perfil num lugar só: o
controller entrega as opções à view, e a view só faz o laço."

Chance: média (surge assim que existe uma coluna "Perfil" na listagem ou
um `<select>` de perfil). Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção 9 (complemento 2, issues da Entrega 4.1), C8 /
issue reservas-academicas #15 (1º parágrafo).

Este cenário é empilhado sobre o D6, que por sua vez está sobre o D1: a
coluna "Perfil" vem do D1 e o `<select name="user[role]">` com opções
fixas vem do D6. Branch no clone: `fvp/c8-rotulos-perfil-um-lugar`, com
três commits acima de `main`. `main` continua igual ao repositório
público (`86c81ca`).

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `7c8d56c` | `feat: List users with pagination in admin area` | cenário D1 (mesmo commit, mesmo SHA) |
| `14c7193` | `feat: Allow admin to change user role` | cenário D6 (mesmo commit, mesmo SHA) |
| `a80220e` | `refactor: Keep role labels in a single place` | este cenário |

## O que mudou (só o commit `a80220e`)

- `app/Models/User.php`:
  - constantes `ROLE_ADMIN = 'admin'` e `ROLE_PARTICIPANT = 'participant'`
    (as mesmas do H2, que não está nesta pilha);
  - `public const ROLE_LABELS` (`@var array<string, string>`, valor do
    ENUM => rótulo): `self::ROLE_ADMIN => 'Administrador'`,
    `self::ROLE_PARTICIPANT => 'Participante'`;
  - `roleLabel(): string`, que devolve
    `self::ROLE_LABELS[$this->role] ?? $this->role`;
  - `signupRoleOptions(): array` (`@return array<string, string>`):
    `ROLE_LABELS` sem o admin, para o cadastro público (B7);
  - `changeRole()` (do D6) valida com
    `array_key_exists($role, self::ROLE_LABELS)`. A constante `ROLES`
    que o D6 criou saiu: era uma segunda lista dos mesmos valores.
- `app/Controllers/Admin/UsersController.php` (`index`): passa
  `$roles = User::ROLE_LABELS` para a view, junto com `users`,
  `paginator` e `title`.
- `app/views/admin/users/index.phtml`: a linha do próprio admin mostra
  `$user->roleLabel()`; nas outras, o `<select>` virou um
  `foreach ($roles as $value => $label)` com o `selected` calculado.
  Nenhum rótulo nem valor de perfil escrito na view. A regra do D6
  continua: sem select na linha do próprio usuário.
- `tests/Unit/Models/Users/UserTest.php`: quatro testes novos.
- `tests/Integration/Controllers/Admin/UsersControllerTest.php`: um
  teste novo para a página com os rótulos.
- `tests/Acceptance/admin/AdminUsersCest.php`: `promoteUserAsAdmin`
  escolhe e confere a opção pelo texto `Administrador` (antes era o
  texto cru `admin`); `adminCannotChangeOwnRole` também confere que a
  linha do admin mostra `Administrador`.

Confirmação final: `grep -rn "Administrador" app/` devolve só
`app/Models/User.php`.

## Por que assim

- O professor (reservas-academicas #15) apontou os rótulos repetidos em
  dois lugares (`User::LABELS` privado e `$profileLabels` na view):
  uma hora eles divergem. Aqui a fonte de verdade é uma só,
  `User::ROLE_LABELS`. Perfil novo (A7, `organizer`) entra no ENUM, numa
  constante e no mapa; view e controller não mudam.
- O rótulo é apresentação, mas o par valor => rótulo pertence ao
  domínio: as chaves têm de ser exatamente os valores do
  `ENUM('admin', 'participant')`. Por isso mora numa constante pública
  do model. O controller entrega o mapa e a view só percorre, como o
  professor sugeriu. Classe CSS, badge ou ícone continuam na view;
  `roleLabel()` devolve só texto.
- `roleLabel()` devolve o próprio `role` quando o valor não está no mapa.
  Sem o `??`, um valor estranho no banco viraria "Undefined array key",
  e o `ErrorsHandler` transforma warning em 500. Com o fallback a
  página fica de pé e o valor cru denuncia o problema. O PHPStan 6
  aceita, porque `role` é `string`.
- `changeRole()` agora valida contra as chaves do mapa. Assim a lista de
  perfis válidos e a de rótulos não têm como divergir. Em PHP uma
  constante não pode chamar função (`const ROLES = array_keys(...)` não
  compila), então em vez de duas constantes ficou uma.
- `signupRoleOptions()` é a peça para o B7 com escolha de perfil: o
  controller passa `$roles = User::signupRoleOptions()` à view e valida
  o POST com `array_key_exists($role, User::signupRoleOptions())`. Assim
  `admin` não entra pelo cadastro nem com formulário forjado.
- Relação com os outros cenários:
  - D1 trouxe a coluna "Perfil" (antes com o valor cru); agora ela mostra
    o rótulo.
  - D6 trouxe o `<select>` com opções escritas à mão e o `ROLES`; o
    select vira laço e o `ROLES` sai.
  - H2: as constantes `ROLE_ADMIN`/`ROLE_PARTICIPANT` são idênticas às
    do H2. Com o H2 no alvo, elas já existem e o conflito é trivial
    (fique com uma cópia). O H2 também troca `'admin'` por
    `self::ROLE_ADMIN` em `isAdmin()` e no construtor; este commit não
    mexe nesses dois métodos, para não duplicar o H2.
  - B10 (em paralelo, `User::ROLES` como lista fechada validada em
    `validates()`): com os dois, use
    `Validations::inclusion('role', array_keys(self::ROLE_LABELS), $this)`
    e apague o `ROLES`; ou mantenha `ROLES` e acrescente um teste
    `assertSame(User::ROLES, array_keys(User::ROLE_LABELS))`.
- PSR-12 e PHPStan 6 sem erros. Textos em pt-BR; identificadores e
  commit em inglês (`type: Title`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
grep -rn "Administrador" app/   # só app/Models/User.php
```

Resultado obtido: Unit 78 testes, Integration 22, Acceptance 15 (eram
74 / 21 / 15 no D6; o D1 tinha 71 / 14 / 13; o público tem 71 / 9 / 9).
PHPCS e PHPStan sem erros; `composer dump-autoload -o --strict-psr` ok.
O schema não mudou.

Testes novos:

- `UserTest::test_role_labels_should_cover_every_role`
- `UserTest::test_role_label_should_follow_role`
- `UserTest::test_role_label_should_return_raw_role_when_unknown`
- `UserTest::test_signup_role_options_should_not_offer_admin`
- `UsersControllerTest::test_index_should_show_role_labels_from_the_model`
  (a resposta tem `Participante`, `Administrador` e uma
  `<option value="...">` para cada item de `ROLE_LABELS`; nenhum
  `admin`/`participant` cru como texto)

Testes ajustados: `AdminUsersCest::promoteUserAsAdmin` e
`AdminUsersCest::adminCannotChangeOwnRole`.

No navegador: entre como `admin@example.com` / `123456` e abra
`/admin/users`. A sua linha mostra "Administrador"; nas outras, o select
oferece "Administrador" e "Participante" (antes mostrava `admin` e
`participant`). Trocar e salvar funciona como no D6.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público. `<caminho>` abaixo é
`s50/exemplos-fvp/c8-rotulos-perfil-um-lugar` (relativo ao clone do s50).

Qual comando usar depende do que o alvo já tem:

- Alvo sem D1 e D6: aplique os três commits com o intervalo
  `7c8d56c^..a80220e` (de D1 até este commit; o `^` inclui o próprio
  `7c8d56c`). Os commits de baixo precisam vir junto: sem eles não
  existem o `Admin\UsersController`, a view nem os testes que este
  commit altera.
- Alvo que já tem D1 e D6 (os mesmos commits): só `a80220e`.
- Alvo que já tem uma listagem própria com select de perfis: só
  `a80220e`, e vai conflitar (veja "Se der conflito").

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c8 <caminho>/projeto
git fetch fvp-c8 fvp/c8-rotulos-perfil-um-lugar

git cherry-pick 7c8d56c^..a80220e   # alvo sem D1/D6: os três, em ordem
git cherry-pick a80220e             # alvo que já tem D1 e D6

git remote remove fvp-c8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/c8-rotulos-perfil-um-lugar:fvp-c8
git cherry-pick 7c8d56c^..a80220e   # alvo sem D1/D6
git cherry-pick a80220e             # alvo que já tem D1 e D6
git branch -D fvp-c8                # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick 7c8d56c^..a80220e`
aplica os três sem conflito. O cherry-pick cria commits novos; o SHA no
seu repositório vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch               # cria commits novos, mesmas mensagens
git apply --3way <caminho>/solucao.patch     # só o diff, sem commit
```

Se o patch trouxer também D1 e D6 e o alvo já os tiver, use
`git am --skip` nos que falharem com "already exists in index". Outra
opção: gere um patch só deste commit no clone `projeto/` com
`git format-patch -1 a80220e --stdout > c8.patch`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Testado: `git cherry-pick a80220e` sozinho conflita em `main` (os
arquivos do D1/D6 não existem: `DU` no controller, na view e nos testes)
e em alvo só com D1 (`UU` em `User.php`, na view e no Cest). Em alvo com
listagem e select próprios, resolva assim:

- `app/Models/User.php`: fique com as constantes `ROLE_*`, o
  `ROLE_LABELS`, `roleLabel()` e `signupRoleOptions()`. Se o alvo tem
  outra lista de perfis (`ROLES`, `in_array(..., ['admin',
  'participant'])`), troque a checagem por
  `array_key_exists($role, self::ROLE_LABELS)` e apague a lista. Se já
  tem um mapa de rótulos (como o `User::LABELS` privado da issue),
  apague-o e use o `ROLE_LABELS`.
- `app/views/...` com o select: descarte as `<option>` escritas à mão e
  qualquer array de rótulos da view; fique só com o
  `foreach ($roles as $value => $label)`. O nome do campo
  (`user[role]`) e as condições em volta (por exemplo, não mostrar o
  select na linha do próprio usuário) ficam como estão no alvo.
- Controller: garanta que a ação que renderiza essa view passa
  `$roles = User::ROLE_LABELS` (ou `User::signupRoleOptions()` no
  cadastro). Sem isso a view quebra com "Undefined variable $roles".
- Testes que procuram `admin`/`participant` como texto visível: troque
  por `Administrador`/`Participante`. O `value` das options continua
  sendo o valor do ENUM.

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Models/User.php`: H2 acrescenta as mesmas constantes `ROLE_*`
  (fique com uma cópia); B10 acrescenta `ROLES` e
  `Validations::inclusion` (veja "Por que assim"); A7 acrescenta
  `organizer` (entra também no `ROLE_LABELS`).
- `app/views/admin/users/index.phtml`: C5 (escapar saída) mexe nos
  mesmos `<?= ?>`; fique com o laço e aplique o `htmlspecialchars` do
  C5 em `$value` e `$label`. D2 mexe na coluna "Ações" e mescla
  sozinho.
- `app/Controllers/Admin/UsersController.php` (`index`): D4, D7 e E1
  também passam variáveis novas no `compact`; junte todas.
- `tests/Acceptance/admin/AdminUsersCest.php` e
  `UsersControllerTest.php`: testes de outros cenários no fim do
  arquivo; fique com os dois.

Depois de aplicar: não há mudança de schema; rode `./run phpcs`,
`./run phpstan` e os testes.

## Armadilhas

- As chaves do mapa precisam ser exatamente os valores do ENUM em
  `database/schema.sql`. Rótulo errado é só feio; chave errada faz
  `changeRole()` recusar um perfil válido ou aceitar um valor que o
  banco rejeita (`PDOException`, 500).
- `seeOptionIsSelected` do Codeception compara o texto visível da
  opção; depois da mudança o texto é `Administrador`, não `admin`. Os
  Cests usam o texto nos dois passos, para ficar coerente.
- A ordem das options passou a ser a do mapa (`Administrador`
  primeiro). Em teste, escolha a opção por texto ou por valor, nunca
  pela posição.
- Esquecer `$roles` no `compact` do controller derruba a página (o
  `ErrorsHandler` transforma o warning em 500). O teste de integração
  pega isso.
- Não leve o mapa para dentro da view "só para facilitar": é
  exatamente a duplicação que o professor apontou.
