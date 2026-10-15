# G4. Populate em classes, como no problem-track

Pedido, como o professor faria na hora: "Organizem o populate em
classes, como no problem-track, e mostrem que dá para testar."

Chance: média-baixa. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção G4.

Branch no clone: `fvp/g4-populate-em-classes`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `180d2a6` | `refactor: Move populate logic into classes` |

## O que mudou

- `database/Populate/UsersPopulate.php` (novo): classe
  `Database\Populate\UsersPopulate` com `public static function
  populate(): void`, que cria o admin (`admin@example.com`) e o
  participante (`user@example.com`), ambos com senha `123456`, e
  imprime a mesma mensagem de antes.
- `database/Populate/populate.php`: só carrega o bootstrap, chama
  `Database::migrate()` e `UsersPopulate::populate()`.
- `tests/Unit/Database/Populate/UsersPopulateTest.php` (novo): chama
  `UsersPopulate::populate()` e confere 2 usuários, e-mails, papéis
  (`admin` e `participant`), senha `123456` e a mensagem impressa.

## Por que assim

- Mesmo padrão do problem-track do professor: uma classe
  `<Recurso>sPopulate` por recurso em `database/Populate/`, chamada pelo
  `populate.php`. Para eventos (4.2), basta `EventsPopulate` ao lado.
- O populate passa a ser testável: antes era um script solto, sem como
  chamar de um teste; agora o teste unitário roda a classe no banco de
  teste (`Tests\TestCase` cria e migra o banco).
- PSR-4: o `composer.json` já mapeia `Database\` para `database/`;
  `Database\Populate\UsersPopulate` está em
  `database/Populate/UsersPopulate.php` com a mesma caixa
  (`composer dump-autoload --strict-psr` passa).
- Resultado igual ao anterior (`./run db:populate` imprime a mesma
  linha e cria os mesmos dois usuários).
- Texto da mensagem em pt-BR, identificadores em inglês, commit em
  inglês `type: Title` (`grading-path.md`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 72 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). PHPCS e PHPStan sem erros. `db:populate` imprime
`Usuários criados: admin@example.com (admin) e user@example.com
(participant), senha 123456`. Teste novo:
`test_populate_should_create_admin_and_participant` em
`tests/Unit/Database/Populate/UsersPopulateTest.php`.

No navegador: `http://localhost/login`, entre com
`admin@example.com` / `123456` (vai para `/admin`) e com
`user@example.com` / `123456` (vai para o painel do usuário).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `s50/exemplos-fvp/g4-populate-em-classes`
abaixo é o diretório deste cenário no s50.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-g4 s50/exemplos-fvp/g4-populate-em-classes/projeto
git fetch fvp-g4 fvp/g4-populate-em-classes
git cherry-pick 180d2a6
git remote remove fvp-g4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/g4-populate-em-classes/solucao.bundle fvp/g4-populate-em-classes:fvp-g4
git cherry-pick 180d2a6        # o bundle traz o objeto; o cherry-pick cria um commit novo
git branch -D fvp-g4           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/g4-populate-em-classes/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/g4-populate-em-classes/solucao.patch   # só o diff, sem commit
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
`database/Populate/populate.php`. Os cenários A7, B3, B5, D3, G1, G2,
G3 e H2 também mexem nele (usuário novo, coluna nova, eventos, laço de
usuários, constantes de perfil). Resolva deixando o `populate.php` só
com `Database::migrate()` e as chamadas às classes, e leve o conteúdo
que o outro cenário acrescentou para dentro de
`UsersPopulate::populate()` (ou de uma classe nova, como
`EventsPopulate`, chamada em seguida). Se o outro cenário mudou e-mails
ou a quantidade de usuários, ajuste o `assertCount` e os e-mails do
`UsersPopulateTest`.

Depois de aplicar: `./run db:reset && ./run db:populate`; rode os
testes e o `./run phpcs` de novo.

## Armadilhas

- PHPCS e PHPStan ignoram `database/`, mas o checador PSR-4 do CI não:
  nome da classe e do arquivo com a mesma caixa
  (`UsersPopulate.php`), namespace `Database\Populate`.
- O `echo` dentro de `populate()` vaza na saída do PHPUnit; o teste usa
  `getOutput()` do `Tests\TestCase` para capturar e conferir a mensagem.
- O nome segue o problem-track (`UsersPopulate`, plural), não
  `UserPopulate`.
