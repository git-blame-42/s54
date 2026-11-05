# R12. Tela reflete as regras do model

Pedido, como o professor faria na hora: "Se o Evento tem inscritos,
nem mostre o Remover. E o campo Vagas deveria dizer o mínimo aceito:
com 3 inscritos, não adianta oferecer 1."

Chance: média. Tempo estimado: 10-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R12.

Branch no clone: `fvp/r12-tela-reflete-regras`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                |
|-----------|---------------------------------------------------------|
| `7505f60` | `feat: Reflect enrollment rules in the events screens`  |

Corpo do commit:

```
Hide the Remove button of events with enrollments (Event::isRemovable,
same rule as safeDestroy) and set the capacity field minimum to the
enrollments count (Event::minCapacity), with a hint when there are
enrollments. The server-side rules stay in the model.
```

## O que mudou

- `app/Models/Event.php`, depois de `hasEnrollments()`:

```php
public function isRemovable(): bool
{
    return !$this->newRecord() && !$this->hasEnrollments();
}

public function minCapacity(): int
{
    return max(1, $this->enrollmentsCount());
}
```

  O `safeDestroy()` e o `validatesCapacityAgainstEnrollments()` não
  mudaram: continuam barrando no servidor.
- `index.phtml`: o form Remover só aparece se `isRemovable()`; senão
  a célula mostra `<span class="small text-body-secondary">Com
  inscrições</span>`. O `<form>` foi quebrado em duas linhas para
  caber em 130 colunas.
- `_form.phtml`: `min="<?= $minCapacity ?>"` no campo Vagas (era
  `min="1"` fixo) e, se o Evento tem Inscrições, a dica
  "Mínimo: N (total de inscrições)." sob o campo. O `novalidate` do
  form fica.
- Testes:
  - `EventTest`: `isRemovable()` falso para Evento novo, verdadeiro
    para salvo sem Inscrições, falso com 1 Inscrição (e o
    `safeDestroy()` concorda); `minCapacity()` é 1 sem Inscrições
    (novo ou salvo) e 3 com 3, e o model aceita exatamente esse valor
    e recusa um a menos.
  - `EventsControllerTest`: no `index`, o
    `action="/admin/events/{id}"` do Evento livre aparece e o do
    Evento com Inscrição não (o link Ver usa `href`), só um botão
    Remover, e "Com inscrições" aparece; `edit` com 2 Inscrições tem
    `min="2"` e a dica; `edit` sem Inscrições e `new` têm `min="1"`
    e nenhuma dica. O `test_destroy_keeps_event_with_enrollments`
    (DELETE direto) continua igual e verde.
  - `ListEventsCest`: cada linha confere "Com inscrições" se o Evento
    tem Inscrições (01 e 03), senão "Remover".
  - `DeleteEventCest::deleteEventWithEnrollmentsIsBlocked`: em vez de
    clicar Remover no Evento 01, confere que a linha não tem o botão,
    mostra "Com inscrições", e que a linha do Evento 02 tem o botão.
  - `UpdateWithInvalidDataCest`: no Evento 01 (3/3), o campo tem
    `min="3"` e a dica "Mínimo: 3"; o resto do cenário (digita 2,
    servidor recusa) não mudou.

## Por que assim

- A decisão fica no model (`isRemovable()`), não um
  `enrollmentsCount() === 0` na view: é a mesma regra do
  `safeDestroy()`, num lugar só. Se a regra mudar (ex.: "não remove
  Evento passado"), muda nos dois juntos.
- Esconder o botão é só visual. Um `DELETE` forjado (ou uma aba
  aberta antes da inscrição) ainda chega ao servidor, e o
  `safeDestroy()` recusa; o teste de Integration do `destroy` prova.
- Texto "Com inscrições" no lugar do botão, e não um botão
  `disabled`: diz por que não dá para remover, e o `disabled` quebra
  o `DeleteEventCest` do mesmo jeito (o clique não envia nada).
- `minCapacity()` no model espelha `validatesCapacityAgainstEnrollments`:
  a tela e a validação usam a mesma conta. O `max(1, ...)` mantém o
  mínimo 1 para Evento novo ou sem Inscrições.
- A dica só aparece com Inscrições: sem elas, o mínimo é 1 e não há
  o que explicar.
- `novalidate` continua: o `min` ajuda as setas do campo e o leitor de
  tela, mas quem barra é o model. Assim o erro do servidor aparece no
  lugar certo, com a mensagem em pt-BR.
- N+1: a célula Vagas já conta Inscrições por linha, e
  `isRemovable()` faz mais uma contagem por linha (cada uma com
  conexão PDO nova). Com 5 linhas por página é aceitável; o N11
  (`Model::count()`) barateia cada consulta.

## Como verificar

```
./run up -d db php php_test web
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 164 testes, Integration 49, Acceptance 23
(base: 159 / 45 / 23; na aceitação três Cests foram ajustados, nenhum
novo). PHPCS e PHPStan sem erros. Schema e populate não mudaram.

Testes novos:

- `EventTest`:
  - `test_new_event_should_not_be_removable`
  - `test_saved_event_without_enrollments_should_be_removable`
  - `test_event_with_enrollments_should_not_be_removable`
  - `test_min_capacity_should_be_one_without_enrollments`
  - `test_min_capacity_should_be_the_enrollments_count`
- `EventsControllerTest`:
  - `test_index_hides_remove_button_of_event_with_enrollments`
  - `test_edit_sets_min_capacity_to_enrollments_count`
  - `test_edit_without_enrollments_keeps_min_one_and_no_hint`
  - `test_new_form_has_min_capacity_one`

No navegador (populate): a página 1 de `/admin/events` mostra "Com
inscrições" no lugar de Remover nos Eventos 1 a 4 e o botão só no 5;
"Editar" do Evento 1 mostra o campo Vagas com `min="3"` e "Mínimo: 3
(total de inscrições).". Por HTTP, de dentro da rede do compose:

```
docker compose exec -T web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec -T web curl -s -b /tmp/c \
    http://localhost/admin/events | grep -E 'action=|Com inscr'
docker compose exec -T web curl -s -b /tmp/c \
    http://localhost/admin/events/1/edit | grep -E 'min=|Mínimo'
```

## Relação com outros cenários

- Q2 (`q2-bloquear-passado-cancelado`): também esconde Editar/Remover
  na listagem (`isEditable()`). Com os dois, a célula de ações vira
  `isEditable()` por fora e `isRemovable()` no Remover; ou ponha a
  regra do passado dentro de `isRemovable()` e do `safeDestroy()`.
- R6 (`r6-partials-remover-show`): leva o Remover para o
  `_actions.phtml` e para o `show`. Use o mesmo `if
  ($event->isRemovable())` no partial; assim vale nos dois lugares.
- N11 (`n11-model-count`): troca o `count(Enrollment::where(...))` do
  `enrollmentsCount()` por `COUNT(*)`; `isRemovable()` e
  `minCapacity()` ficam mais baratos sem mudar.
- Outros que mexem no form Remover da listagem (R4, Q1, Q5, P16,
  P19): o `<form>` deles vai para dentro do `if`.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/r12-tela-reflete-regras`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r12-tela-reflete-regras
cd /caminho/do/seu/repositorio
git remote add fvp-r12 \
    s50/fvp/4.2/exemplos/r12-tela-reflete-regras/projeto
git fetch fvp-r12 fvp/r12-tela-reflete-regras
git cherry-pick 7505f60
git remote remove fvp-r12
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/r12-tela-reflete-regras/solucao.bundle \
    fvp/r12-tela-reflete-regras:fvp-r12
git cherry-pick 7505f60
# opcional, limpa a ref
git branch -D fvp-r12
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/r12-tela-reflete-regras
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

Sem mudança de schema: não precisa de `db:populate`.

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue
# ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `index.phtml` (célula de ações): Q2, R4, R6, Q1, Q5 mexem no mesmo
  `<form>`. Mantenha o `if ($event->isRemovable())` por fora e a
  mudança do outro cenário dentro.
- `_form.phtml` (campo Vagas): M2 (máximo de vagas) e M21 (vagas
  ilimitadas, dica própria) mexem no mesmo `<input>`. Junte os
  atributos (`min`, `max`) e as dicas.
- `Event.php`: outros métodos novos depois de `hasEnrollments()`;
  fique com todos.
- `DeleteEventCest` e `ListEventsCest`: cenários que mudam a célula
  de ações também ajustam essas linhas; a conferência "Com inscrições"
  deste cenário continua valendo.

## Armadilhas

- A regra continua em `safeDestroy()`: esconder o botão não protege
  nada sozinho. Não apague o teste de `DELETE` direto do
  `EventsControllerTest`.
- `DeleteEventCest` clicava Remover no Evento 01 (3 Inscrições): sem o
  botão, o `click` falha porque não acha o elemento. A recusa do
  servidor fica provada no Integration.
- `ListEventsCest` conferia "Remover" em toda linha da página 1, mas
  os Eventos 01 e 03 têm Inscrições (`seedEvents` do
  `BaseEventsCest`).
- Não tire o `novalidate`: o `UpdateWithInvalidDataCest` digita 2
  com 3 inscritos e espera o erro do servidor. Sem `novalidate`, o
  navegador barra o envio e o Cest quebra.
- Na Integration, procure `action="/admin/events/{id}"` (só o form
  Remover tem `action` na listagem); `"/admin/events/{id}"` sozinho
  casa também com o `href` do Ver.
- `minCapacity()` lê as Inscrições do banco: no re-render de um
  `update` recusado, o `min` continua o total real, não o valor
  digitado.
