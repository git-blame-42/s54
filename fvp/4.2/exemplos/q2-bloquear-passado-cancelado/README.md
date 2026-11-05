# Q2. Bloquear edição e remoção de Evento passado ou cancelado

Pedido, como o professor faria na hora: "Evento que já aconteceu, ou
que foi cancelado, não pode mais ser editado nem removido. Esconda os
botões e barre também quem digitar a URL direto."

Chance: média. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, Q2.

Branch no clone: `fvp/q2-bloquear-passado-cancelado`, empilhada sobre P5
(`fvp/p5-status-cancelar-encerrar`), que parte de `main` (`f31006b` do público;
no bundle `5dedab5`, ver README raiz). Q2 usa `isCanceled()` de P5.

| Commit    | Mensagem                                                   |
|-----------|------------------------------------------------------------|
| `ba6b6f1` | (base, P5) `feat: Add Event status with cancel and ...`    |
| `c6273d4` | `feat: Block editing and removing past or canceled Events` |

A mensagem completa do commit de P5 é `feat: Add Event status with
cancel and finish actions`.

## O que mudou

- `app/Models/Event.php`: método novo

  ```php
  public function isEditable(): bool
  {
      return !$this->isCanceled() && $this->starts_on >= date('Y-m-d');
  }
  ```

  Evento de hoje ainda é editável; encerrado (`finished`) com data
  futura também (o pedido fala só de passado e cancelado).
- `app/Controllers/Admin/EventsController.php`: em `edit`, `update` e
  `destroy`, logo depois do `findEvent`, *early return*:
  `if (!$event->isEditable())` → flash `danger` "Evento passado ou
  cancelado não pode ser alterado!" e redirect para o `show`. O helper
  privado `canceledEventNotEditable` de P5 virou `eventNotEditable`.
- `index.phtml`: "Editar" e "Remover" só se `isEditable()`.
- `show.phtml`: "Editar" só se `isEditable()`.
- Testes de P5 que esperavam "Evento cancelado não pode ser editado!"
  passaram a esperar a mensagem nova.

## Por que assim

- A decisão fica no model (`isEditable()`); controller e views só
  perguntam. Uma regra, três lugares que a usam.
- *Early return* no controller, igual ao `eventNotFound()`: esconder o
  botão não basta, a URL `/admin/events/{id}/edit` e um `DELETE` feito
  à mão continuam chegando.
- `starts_on` vem do banco como `Y-m-d` (coluna `DATE`), então
  comparar strings dá o mesmo resultado que comparar datas.
- Os testes do model que garantem "Evento passado pode ter a descrição
  corrigida" (`EventTest::test_past_event_can_be_edited_when_starts_on
  _does_not_change` e vizinhos) continuam valendo e passando: falam de
  `save()`/`validates()`, e o bloqueio novo é da interface (controller
  e views). Se o professor quiser a regra também no `save()`, remova
  ou inverta esses testes com ele ciente.

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

Resultado obtido: Unit 173, Integration 68, Acceptance 30 (com P5:
168 / 63 / 27; base `main`: 159 / 45 / 23). PHPCS e PHPStan sem
erros.

Testes novos:

- `EventTest`: `test_future_scheduled_event_should_be_editable`,
  `test_event_happening_today_should_be_editable`,
  `test_past_event_should_not_be_editable`,
  `test_canceled_event_should_not_be_editable`,
  `test_finished_future_event_should_still_be_editable`.
- `EventsControllerTest`: `edit`/`update`/`destroy` de Evento passado e
  `destroy` de cancelado redirecionam para o `show` com `danger` e não
  alteram nada; listagem e `show` sem "Editar"/"Remover" no passado.
  Helper `createPastEvent()` (move a data com `update()`, que não
  valida).
- `PastEventCest` (novo): passado sem botões; URL direta de edição
  redireciona; cancelado sem botões.

No navegador, o populate não tem Evento passado. Crie um:

```
./run db:console
# no prompt do MySQL:
UPDATE events SET starts_on = '2020-01-01' WHERE id = 16;
```

O Evento 16 perde "Editar"/"Remover" e `/admin/events/16/edit`
redireciona para `/admin/events/16` com o flash.

## Relação com outros cenários

- P5 (`p5-status-cancelar-encerrar`): base obrigatória (status,
  `isCanceled()`, bloqueio de edição de cancelado, que este cenário
  generaliza).
- Q1 (só o dono edita e remove): outro *early return* no mesmo ponto.
  Com os dois, dono primeiro, depois `isEditable()`.
- P3 (remoção segura com inscrições): continua valendo; `isEditable()`
  é checado antes do `safeDestroy()`.
- Sem P5, dá para fazer só a parte da data:
  `return $this->starts_on >= date('Y-m-d');`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/q2-bloquear-passado-cancelado`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/q2-bloquear-passado-cancelado`.

Escolha o que aplicar:

- Sem P5 no seu repositório: aplique o range inteiro
  `f31006b..c6273d4` (P5 + Q2, dois commits, nessa ordem).
- Com P5 já aplicado: aplique só o topo, `c6273d4`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-q2 \
    s50/fvp/4.2/exemplos/q2-bloquear-passado-cancelado/projeto
git fetch fvp-q2 fvp/q2-bloquear-passado-cancelado
# range (P5 + Q2):
git cherry-pick f31006b..c6273d4
# ou só o topo, se já tem P5:
# git cherry-pick c6273d4
git remote remove fvp-q2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/q2-bloquear-passado-cancelado/solucao.bundle \
    fvp/q2-bloquear-passado-cancelado:fvp-q2
git cherry-pick f31006b..c6273d4
# ou só o topo: git cherry-pick c6273d4
# opcional, limpa a ref
git branch -D fvp-q2
```

### 3. A partir do `solucao.patch` (`git am`)

O patch traz os dois commits (P5 e Q2), em ordem.

```
git am s50/fvp/4.2/exemplos/q2-bloquear-passado-cancelado/solucao.patch
# já tem P5? aplique e pule o primeiro:
# git am --skip   (quando o P5 der conflito/vazio)
```

Depois de aplicar: `./run db:populate` (P5 mudou o schema) e os testes.

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

- `EventsController::edit`/`update`/`destroy`: Q1 e outros também
  põem *early return* logo após o `findEvent`. Fique com os dois
  blocos.
- `index.phtml` (célula de ações) e `show.phtml` (botões): R4, Q1, P6
  mexem nos mesmos botões. Ponha o botão do outro cenário dentro ou
  fora do `if (isEditable())` conforme a regra dele.
- Se o seu P5 não for exatamente o deste exemplo, o topo pode conflitar
  no helper `canceledEventNotEditable`: troque por `eventNotEditable`
  e a condição por `!$event->isEditable()`.

## Armadilhas

- Só esconder os botões: a URL direta e um `DELETE` manual continuam
  funcionando. O teste `test_destroy_of_a_past_event_keeps_the_record`
  pega isso.
- Esquecer o `destroy`: o pedido é "editar **nem remover**".
- Regra no controller (`$event->starts_on < date(...)` espalhado em
  três ações e duas views): fica inconsistente na primeira mudança.
- `>` em vez de `>=`: o Evento de hoje fica bloqueado antes de
  acontecer.
- Comparar `starts_on` como string só funciona porque o banco devolve
  `Y-m-d`; não compare com `d/m/Y`.
- O `return` depois do `redirectTo` é obrigatório: no teste de
  controller o redirect só imprime `Location:` e a ação seguiria.
- Contradiz a decisão documentada em `Event::validatesStartsOnNotPast`
  (Evento passado pode ter a descrição corrigida). Combine com o
  professor; aqui o model segue permitindo e a interface bloqueia.
