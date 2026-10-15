# H2. Constantes para o perfil (sem strings mágicas)

Pedido, como o professor faria na hora: "Tirem as strings `'admin'` e
`'participant'` espalhadas pelo código; usem constantes no model."

Chance: média-baixa. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção H2.

Branch no clone: `fvp/h2-constantes-perfil`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `cb172dd` | `refactor: Replace role magic strings with constants` |

## O que mudou

- `app/Models/User.php`: constantes `User::ROLE_ADMIN = 'admin'` e
  `User::ROLE_PARTICIPANT = 'participant'`; o construtor usa
  `self::ROLE_PARTICIPANT` como padrão; `isAdmin()` compara com
  `self::ROLE_ADMIN`; método novo `isParticipant()`.
- `database/Populate/populate.php`: admin criado com
  `'role' => User::ROLE_ADMIN`; a mensagem final imprime `$admin->role`
  e `$user->role` em vez de texto fixo.
- `tests/Acceptance/admin/AdminDashboardCest.php`: admin do `_before`
  criado com `User::ROLE_ADMIN`.
- `tests/Unit/Models/Users/UserTest.php`: os dois testes de `isAdmin`
  passam a usar as constantes; 5 testes novos (lista abaixo).

Nenhuma view imprime nem compara `role` (a navbar usa `isAdmin()`), por
isso nenhuma view mudou e não foi preciso rótulo pt-BR. O ENUM de
`database/schema.sql` não muda.

## Por que assim

- OO (issue #9 avalia "OO"): o conhecimento sobre os perfis fica no
  model; quem usa chama `isAdmin()`/`isParticipant()` ou a constante.
- Refatoração sem mudança de comportamento: os mesmos testes de
  controller, acesso e aceitação passam sem alteração de asserção.
- Teste que fixa o valor das constantes contra o ENUM do banco: se
  alguém renomear a constante, o teste quebra antes do `INSERT` falhar.
- PSR-12 e PHPStan nível 6 sem erros; identificadores em inglês; commit
  em inglês `type: Title` (`grading-path.md`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # só se o schema mudou
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

O schema não mudou; o `db:populate` foi rodado mesmo assim para provar
o populate com a constante (imprime `admin@example.com (admin) e
user@example.com (participant)`).

Resultado obtido: Unit 76 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos, todos em
`tests/Unit/Models/Users/UserTest.php`:

- `test_role_constants_should_match_database_enum_values`
- `test_new_user_should_have_participant_role_by_default`
- `test_is_participant_should_return_true_by_default`
- `test_is_participant_should_return_false_for_admin_role`
- `test_role_given_in_constructor_should_be_kept`

No navegador: `http://localhost` — entre com `admin@example.com` /
`123456` e veja o link do painel admin na navbar; saia e entre com
`user@example.com` / `123456`: o link não aparece e `/admin` recusa.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `s50/exemplos-fvp/h2-constantes-perfil`
abaixo é o diretório deste cenário no s50.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-h2 s50/exemplos-fvp/h2-constantes-perfil/projeto
git fetch fvp-h2 fvp/h2-constantes-perfil
git cherry-pick cb172dd
git remote remove fvp-h2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/h2-constantes-perfil/solucao.bundle fvp/h2-constantes-perfil:fvp-h2
git cherry-pick cb172dd        # o bundle traz o objeto; o cherry-pick cria commit novo
git branch -D fvp-h2           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/h2-constantes-perfil/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/h2-constantes-perfil/solucao.patch   # só o diff, sem commit
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

- `app/Models/User.php`: A7 (perfil `organizer`), B3, B5, B9, D6 e H1
  mexem no model. Mantenha as duas mudanças; com A7, acrescente
  `ROLE_ORGANIZER` ao lado das outras constantes; com D6, troque o
  `in_array(..., ['admin', 'participant'])` pela lista de constantes.
- `database/Populate/populate.php`: G1, G2 e G4 reescrevem o populate.
  Fique com a versão deles e troque `'admin'` por `User::ROLE_ADMIN`.
- `tests/Unit/Models/Users/UserTest.php`: F4 e outros cenários
  acrescentam testes no mesmo trecho; mantenha os dois blocos.
- `tests/Acceptance/admin/AdminDashboardCest.php`: cenários D* mudam o
  `_before`; só a linha do `role` é deste cenário.

Depois de aplicar: `./run db:reset` se o schema mudou; rode os testes
e o `./run phpcs` de novo.

## Armadilhas

- A constante não muda o banco: `role` continua ENUM
  `('admin', 'participant')`. Perfil novo exige mudar `schema.sql` e
  `./run db:reset`.
- Dentro do model use `self::ROLE_...`; fora, `User::ROLE_...` com
  `use App\Models\User;` no topo (o populate e o Cest já importam).
- Não troque `'admin'` em `config/App.php`/`config/routes.php`: ali é o
  nome do middleware, não o perfil.
- O `role="alert"`/`role="button"` das views é atributo HTML, não o
  perfil; o `grep` acha, mas não mexa.
