# P17. Remover Evento com inscrições em cascata, com confirmação

Pedido, como o professor faria na hora: "Hoje um Evento com inscrições
não pode ser removido. Na página de confirmação de remoção, acrescente
a opção 'Remover também as N inscrições'. Com o código certo e a opção
marcada, remova as inscrições e o Evento juntos, sem deixar nada pela
metade. Sem a opção, continua recusando. Diga no flash quantas
inscrições foram removidas."

Chance: média. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P17.

Branch no clone: `fvp/p17-remover-em-cascata`, empilhada sobre P16
(`fvp/p16-remover-com-codigo-confirmacao`), que parte de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz). P17 usa a
página de confirmação e o código de P16.

| Commit    | Mensagem                                                   |
|-----------|------------------------------------------------------------|
| `0c88e5c` | (base, P16) `feat: Require a confirmation code to ...`     |
| `47030c1` | `feat: Remove an Event with its enrollments when forced`   |

A mensagem completa do commit de P16 é `feat: Require a confirmation
code to remove an Event`.

## O que mudou

- `app/Models/Event.php`:
  - `safeDestroy(bool $force = false): bool`: sem `force`, igual a
    antes (recusa com "não é possível remover um evento com inscrições
    (N)!"); com `force` e inscrições, chama o privado
    `destroyWithEnrollments()`;
  - `destroyWithEnrollments()`: uma conexão
    (`Database::getDatabaseConn()`), `beginTransaction()`, `DELETE FROM
    event_user WHERE event_id = :event_id`, `DELETE FROM events WHERE
    id = :id`, `commit()`. Se o segundo `DELETE` não apagar 1 linha
    ou der `PDOException`, `rollBack()` e erro em `errors('base')`;
  - `removedEnrollments(): int` (propriedade privada
    `$removedEnrollments`, zerada a cada `safeDestroy`).
- `app/Controllers/Admin/EventsController.php` (`destroy`): depois do
  código de P16, `$force = $request->getParam('force') === '1';`,
  `$event->safeDestroy(force: $force)` e flash pelo helper privado
  `destroyedMessage()`: "Evento removido com sucesso!" ou "Evento
  removido com sucesso, com N inscrições removidas!" (singular com 1).
- `app/views/admin/events/delete.phtml`: se o Evento tem inscrições,
  checkbox `#force` (`name="force" value="1"`) "Remover também as N
  inscrições (sem isso o evento não é removido)".
- Schema sem mudança (a FK continua `ON DELETE RESTRICT`).

## Por que assim

- Transação no model, e não `ON DELETE CASCADE` no schema: com cascata
  na FK, **qualquer** `DELETE` do Evento (inclusive o `destroy()` puro
  e um engano no console) levaria as inscrições junto, e a regra P03
  ("Evento com inscrições não é removido") só ficaria de pé no código.
  Com `RESTRICT` + transação, apagar inscrições é sempre um ato
  explícito (`force`), e o banco continua barrando o resto.
- Alternativa, se o professor pedir: no `schema.sql`,
  `fk_event_user_event ... ON DELETE CASCADE`; aí `safeDestroy(force:
  true)` vira só `$this->destroy()` e o teste
  `test_plain_destroy_with_enrollments_is_blocked_by_the_foreign_key`
  precisa ser invertido. Para contar as inscrições removidas, conte
  antes (`enrollmentsCount()`).
- Mesma conexão nos dois `DELETE`: `Database::getDatabaseConn()` cria
  um PDO novo a cada chamada; `Enrollment::destroy()` +
  `$this->destroy()` rodariam em conexões diferentes, fora da
  transação.
- Inscrições antes do Evento: a FK `RESTRICT` recusaria a ordem
  inversa.
- `force` só vale com o código certo: o `check` de P16 vem antes, então
  código errado não apaga nada (nem inscrição).
- `=== '1'`: `force[]=1` (array) ou `force=sim` não forçam.
- Contagem vem do `rowCount()` do `DELETE` das inscrições, dentro da
  transação, e não de uma contagem anterior.

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

Resultado obtido: Unit 173, Integration 63, Acceptance 25 (com P16:
169 / 58 / 24; base `main`: 159 / 45 / 23). PHPCS e PHPStan sem
erros. Schema não mudou.

Testes novos:

- `EventTest`:
  `test_safe_destroy_with_force_should_remove_the_event_and_its_enrollments`
  (só as do Evento; as de outro ficam),
  `test_safe_destroy_without_force_should_keep_the_event_and_its_enrollments`,
  `test_safe_destroy_with_force_and_no_enrollments_should_remove_the_event`,
  `test_safe_destroy_with_force_should_fail_for_a_new_record`.
- `EventsControllerTest`: checkbox só com inscrições; `destroy` com
  `force` (remove tudo, flash com 2 e com 1 no singular); `force` com
  código errado não remove nada; `force` em formato errado (array)
  continua recusando.
- `DeleteEventCest::deleteFullEventWithItsEnrollments`: Evento 01
  (lotado, 3/3) removido com a opção marcada; flash com "3 inscrições
  removidas". O cenário `deleteEventWithEnrollmentsIsBlocked` (sem a
  opção) continua passando.

## Relação com outros cenários

- P16 (`p16-remover-com-codigo-confirmacao`): base obrigatória (página
  de confirmação, código, `confirmationKey`).
- P3 (`safeDestroy`, Evento com inscrições não é removido): continua
  sendo o padrão; P17 só abre uma exceção explícita.
- Q2 (passado ou cancelado não remove): com os dois, o `isEditable()`
  vem antes do código e do `force`.
- P2/P4 (inscrição e presença): com P4, `event_user` ganha
  `confirmed_at`; a remoção em cascata apaga também essas presenças.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p17-remover-em-cascata`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p17-remover-em-cascata`.

Escolha o que aplicar:

- Sem P16 no seu repositório: aplique o range inteiro
  `5dedab5..47030c1` (P16 + P17, dois commits, nessa ordem).
- Com P16 já aplicado: aplique só o topo, `47030c1`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p17 \
    s50/fvp/4.2/exemplos/p17-remover-em-cascata/projeto
git fetch fvp-p17 fvp/p17-remover-em-cascata
# range (P16 + P17):
git cherry-pick 5dedab5..47030c1
# ou só o topo, se já tem P16:
# git cherry-pick 47030c1
git remote remove fvp-p17
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p17-remover-em-cascata/solucao.bundle \
    fvp/p17-remover-em-cascata:fvp-p17
git cherry-pick 5dedab5..47030c1
# ou só o topo: git cherry-pick 47030c1
# opcional, limpa a ref
git branch -D fvp-p17
```

### 3. A partir do `solucao.patch` (`git am`)

O patch traz os dois commits (P16 e P17), em ordem.

```
git am s50/fvp/4.2/exemplos/p17-remover-em-cascata/solucao.patch
# já tem P16? quando o primeiro commit vier vazio ou conflitar:
# git am --skip
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr` (P16
cria `lib/ConfirmationCode.php`) e os testes.

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

Arquivos que costumam conflitar neste cenário e por quê:

- `Event::safeDestroy`: outro cenário que mude a regra de remoção
  (ex.: soft delete, P11). Mantenha o parâmetro `force` e o desvio
  para `destroyWithEnrollments()` antes da recusa.
- `EventsController::destroy`: Q1/Q2 põem *early return* no mesmo
  ponto; o `force` fica logo antes do `safeDestroy`.
- `delete.phtml`: se o seu P16 for diferente, ponha o checkbox dentro
  do form que envia o `DELETE`.
- `DeleteEventCest` e `EventsControllerTest`: testes novos no mesmo
  ponto; fique com todos.

## Armadilhas

- Usar `Enrollment::destroy()` em laço + `$this->destroy()`: cada um
  abre uma conexão; se o segundo falhar, as inscrições já foram e não
  há `rollBack` que as traga de volta.
- Apagar o Evento antes das inscrições: a FK `RESTRICT` lança
  `PDOException`.
- Esquecer o `rollBack()` no `catch`: a conexão fecha com a transação
  aberta e o MySQL desfaz, mas o código fica enganoso; e sem
  `inTransaction()` um erro no `beginTransaction` vira outro erro.
- Trocar a FK para `ON DELETE CASCADE` sem avisar: o `destroy()` puro
  passa a apagar inscrições, e o teste da FK quebra (ver "Por que
  assim").
- Mostrar o checkbox marcado por padrão: vira remoção em cascata por
  descuido. Ele começa desmarcado.
- `if ($request->getParam('force'))`: `'0'` é falso, mas `'no'` é
  verdadeiro. Compare com `=== '1'`.
- Flash fixo "N inscrições": com 1 fica "1 inscrições"; o helper trata
  o singular.
