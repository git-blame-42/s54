# P4. Confirmar presença pelo código do Evento

Pedido, como o professor faria na hora: "O Participante inscrito
confirma presença digitando o código do Evento (o número de 4
dígitos); o backend valida e registra a confirmação."

Chance: média-alta. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P4.

Branch no clone: `fvp/p4-confirmar-presenca-codigo`. Empilhada: o P4
precisa da inscrição (P2, que depende de O2) e do código (P1).

- base: `fvp/p2-inscricao-participante` (O2 → P2), sobre `main`
  (`f31006b`);
- P1 trazido por cherry-pick (`bb5bf0e` na branch
  `fvp/p1-codigo-checkin-aleatorio` virou `d6f79fc` aqui; aplicou
  sem conflito);
- P4 por cima.

Commits (`main..fvp/p4-confirmar-presenca-codigo`):

- `9d7c82f` `feat: Add events listing and details for participants`
  (base, O2)
- `95e5e82` `feat: Allow participants to enroll in events`
  (base, P2)
- `d6f79fc` `feat: Generate a random check-in code for each Event`
  (base, P1 cherry-picked de `bb5bf0e`)
- `cac1915` `feat: Confirm event presence with the check-in code`
  (este cenário)

## O que mudou

Só o último commit (`cac1915`):

- `database/schema.sql`: `confirmed_at TIMESTAMP NULL DEFAULT NULL`
  em `event_user` (NULL = presença não confirmada).
- `app/Models/Enrollment.php`:
  - `'confirmed_at'` em `$columns` e `@property string|null`;
  - `confirm(string $code): bool`: se já confirmada, erro "presença
    já confirmada!"; se o Evento não existe, o código não é só
    dígitos (`ctype_digit`) ou `(int) $code !== (int)
    $event->checkin_code`, erro genérico "código inválido!"; senão
    `update(['confirmed_at' => date('Y-m-d H:i:s')])`;
  - `isConfirmed(): bool`.
- `app/Models/User.php`: `enrollmentIn(Event $event): ?Enrollment`
  (`Enrollment::findBy` com `event_id` e `user_id`).
- `app/Models/Event.php`: `confirmedCount(): int` (inscrições com
  `isConfirmed()`).
- `config/routes.php`, grupo `auth`: `POST /events/{id}/checkin`,
  nome `events.checkin`.
- `app/Controllers/EnrollmentsController.php`: ação `checkin`.
  Evento inexistente → "Evento não encontrado!" e `/events`; não
  inscrito → "Você não está inscrito neste evento!"; código por
  `getParam('code')` com `is_string` + `trim`; sucesso → "Presença
  confirmada!"; falha → `errors('base')`. Volta para o `show`.
- `app/views/events/show.phtml` (Participante): inscrito e não
  confirmado vê o form (`name="code"`, `inputmode="numeric"`,
  `maxlength="4"`, botão "Confirmar presença"); confirmado vê
  "Presença confirmada em dd/mm/aaaa hh:mm.". O código nunca aparece.
- `app/views/admin/events/show.phtml`: "Presenças confirmadas"
  (`confirmados/inscritos`), acima do código de check-in do P1.
- Testes:
  - `EnrollmentTest` (Unit, 8): nova inscrição não confirmada;
    código certo grava `confirmed_at`; código errado não grava;
    malformados (vazio, `12a4`, `-123`, `12.0`) recusados; confirmar
    duas vezes mantém a primeira data.
  - `EnrollmentsControllerTest` (Integration, 5): código certo (com
    espaços em volta), errado (mensagem genérica sem o código),
    código como array (sem 500), não inscrito, Evento inexistente.
  - `ParticipantEventsAccessTest` (Integration, 2): sem sessão vai
    para `/login` e nada é gravado; Participante confirma por HTTP.
  - `ParticipantCheckinCest` (Acceptance, novo, 2): Participante 1
    no Evento 03 erra o código, vê "código inválido!", acerta, vê
    "Presença confirmada!" e o form some; não inscrito não vê o form.

## Por que assim

- Regra no model (`Enrollment::confirm`), controller fino, como o
  catálogo pede. O controller só trata o formato HTTP do parâmetro.
- `ctype_digit` + comparação `int` com `int`: `'1234' === 1234` é
  sempre falso, e `(int) '12a4'` daria 12 sem o `ctype_digit`.
- Mensagem genérica e o código nunca na página do Participante: quem
  fornece o código é o admin, no local.
- `update()` e não `save()`: não passa por `validates()` (a checagem
  de vagas e de duplicidade é da criação). Proposital.
- Coluna em `$columns`: o `save()` da inscrição nova grava
  `confirmed_at = NULL` (o `Model` grava todas as colunas) e o
  `where`/`findById` passam a ler a coluna.
- Rota: o orquestrador pediu `POST /events/{id}/checkin`
  (`events.checkin`, ação `checkin`). O catálogo sugere
  `/events/{id}/enrollments/confirmation`
  (`events.enrollments.confirm`, ação `confirm`); as duas são
  RESTful, troque só a linha da rota e o nome no form se preferir.
- `confirmedCount()` no admin é o passo 7 do catálogo.

## Como verificar

```
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 179, Integration 79, Acceptance 31 (pilha
O2+P2+P1 sem o P4: 171 / 72 / 29; `main`: 159 / 45 / 23). PHPCS e
PHPStan sem erros; `db:populate` sem erro.

Na tela (populate): logue como admin, abra o primeiro Evento em
`/admin/events/1` e anote o código de check-in. Saia, logue como
`user@example.com` / `123456` (inscrito no Evento 1), abra
`/events/1`, digite um código errado ("código inválido!") e depois o
certo ("Presença confirmada!" e a data). No admin, "Presenças
confirmadas" passa a 1/3. Pelo terminal (troque 7429 pelo código):

```
docker compose exec web curl -s -c /tmp/u -d \
    'user[email]=user@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/u -d code=7429 \
    http://localhost/events/1/checkin
docker compose exec web curl -s -b /tmp/u http://localhost/events/1
```

## Relação com outros cenários

- O2 (`o2-eventos-para-participante`) e P2
  (`p2-inscricao-participante`): base (área do Participante e
  inscrição).
- P1 (`p1-codigo-checkin-aleatorio`): gera o código que este cenário
  valida.
- P3 (`p3-cancelar-inscricao`) mexe no mesmo `EnrollmentsController`
  e no mesmo bloco do `events/show.phtml`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/p4-confirmar-presenca-codigo
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p4-confirmar-presenca-codigo`. Cherry-pick cria
commits novos (SHAs diferentes); preserva conteúdo e mensagem.

Primeiro decida o que o alvo já tem:

- nada (só `main`): a pilha toda, por intervalo
  (`main..fvp/p4-confirmar-presenca-codigo`, 4 commits);
- O2 e P2, sem P1: `d6f79fc cac1915`;
- O2, P2 e P1: só o topo, `cac1915`;
- P1 sem O2/P2: `9d7c82f 95e5e82 cac1915`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p4 <caminho>/projeto
git fetch fvp-p4 main fvp/p4-confirmar-presenca-codigo
# alvo só com main: a pilha toda
git cherry-pick \
    fvp-p4/main..fvp-p4/fvp/p4-confirmar-presenca-codigo
# alvo que já tem O2, P2 e P1: só o topo
git cherry-pick cac1915
git remote remove fvp-p4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/p4-confirmar-presenca-codigo:fvp-p4
# alvo só com main
git cherry-pick 9d7c82f 95e5e82 d6f79fc cac1915
# alvo que já tem O2, P2 e P1
git cherry-pick cac1915
# opcional, limpa a ref
git branch -D fvp-p4
```

### 3. A partir do `solucao.patch` (`git am`)

O patch é uma série com os quatro commits.

```
# alvo só com main: aplica os quatro
git am <caminho>/solucao.patch
# se um commit da base já existe e conflitar, pule-o
git am --skip
# só o diff, sem commit
git apply --3way <caminho>/solucao.patch
```

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
# ou: git am --continue
git cherry-pick --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `database/schema.sql`: outro campo em `event_user` (P3, P5) ou em
  `events`. Fique com todas as colunas; depois `./run db:populate`.
- `Enrollment.php`, `$columns`: junte as colunas dos dois cenários.
- `events/show.phtml`, bloco "Você está inscrito": P3 põe ali o
  botão de cancelar. Mantenha os dois dentro do
  `if ($enrollment !== null)`.
- `EnrollmentsController.php` e os testes: ações e testes novos no
  fim do arquivo; mantenha os dois blocos.
- `config/routes.php`: outra rota de inscrição no grupo `auth`.
  Fique com as duas.

Depois de aplicar: `./run db:populate` (schema mudou), `./run phpcs`
e os testes.

## Armadilhas

- Esquecer `./run db:populate` depois de mudar o schema: o banco de
  desenvolvimento fica sem `confirmed_at` e o `show` dá 500 (os
  testes recriam o schema sozinhos e passam).
- `'1234' === 1234` é falso: converta os dois lados para `int`, mas
  só depois do `ctype_digit` (`(int) '12a4'` é 12).
- `getParam('code')` pode vir array (`code[]=...`): `is_string`
  antes do `trim`, senão `TypeError` no `confirm(string)`.
- Não ponha o código na mensagem de erro nem na página do
  Participante.
- `update()` não valida: é proposital aqui; diga isso se perguntarem.
- `confirmed_at` fora de `$columns`: o `update()` grava, mas
  `findById` não lê a coluna e `isConfirmed()` fica sempre falso.
- O `return` depois de cada `redirectTo` do ramo de erro é
  obrigatório: no teste de controller o `redirectTo` não dá `exit`.
- Limite de tentativas (força bruta em 9000 códigos) não foi pedido;
  se for, guarde as tentativas em `$_SESSION` por Evento.
