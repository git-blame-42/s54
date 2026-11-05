# M7. Horário do Evento

Pedido, como o professor faria na hora: "O Evento precisa ter horário
de início, não só a data. Não deixe cadastrar um Evento para um
horário que já passou, nem hoje mais cedo. Mostre data e hora no
formulário, na listagem e no detalhe."

Chance: média-alta. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M7.

Branch no clone: `fvp/m7-horario-do-evento`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                           |
|-----------|----------------------------------------------------|
| `f01890f` | `feat: Replace event date with starts_at datetime` |

Forma usada: a (B) do catálogo, `starts_on DATE` vira
`starts_at DATETIME`. A forma (A), colunas `TIME` ao lado de
`starts_on`, está resumida em "Por que assim".

## O que mudou

- `database/schema.sql`: `starts_at DATETIME NOT NULL` no lugar de
  `starts_on DATE NOT NULL`; índice renomeado para
  `index_events_on_starts_at`.
- `app/Models/Event.php`:
  - `@property string $starts_at` e a chave em `$columns`;
  - em `validates()`: `notEmpty('starts_at') &&
    validDate('starts_at', $this, 'Y-m-d H:i') &&
    validatesStartsAtNotPast()`;
  - `validatesStartsAtNotPast()` (era `validatesStartsOnNotPast`):
    rejeita com "não pode estar no passado!" quando
    `$this->starts_at < date('Y-m-d H:i')`. Comparação de strings,
    válida porque os dois lados já estão em `Y-m-d H:i`. O minuto
    atual é aceito. Na edição, continua só cobrando quando
    `starts_at` muda (Evento passado ainda pode ser corrigido);
  - `__set` sobrescrito só para `starts_at`: troca o `T` do
    `datetime-local` (`2030-05-10T14:30`) por espaço e corta os
    segundos que o MySQL devolve (`2030-05-10 14:30:00`). Os dois
    viram `2030-05-10 14:30`. Outro texto fica como veio e a
    validação recusa;
  - `startsAtInputValue()`: devolve `Y-m-d\TH:i` para o `value` do
    campo (texto inválido volta como foi digitado).
- `EventsController`: `'starts_at'` em `FIELDS` (o resto igual).
- `_form.phtml`: campo "Data e hora", `type="datetime-local"`,
  `id="event_starts_at"`, `value` de `startsAtInputValue()`.
- `index.phtml` e `show.phtml`: "Data e hora", formato
  `date('d/m/Y H:i', strtotime($event->starts_at))`.
- `populate.php`: mesmas datas relativas, com hora alternando entre
  08:00, 10:30, 14:00 e 19:00 (índice do Evento).
- Testes ajustados (chave `starts_on` vira `starts_at`, valores
  `Y-m-d H:i`): `EventTest`, `EnrollmentTest`, `EventsControllerTest`,
  `EventsAccessTest`, `BaseEventsCest` (`seedEvents` às 19:00;
  `validEventData`; `fillEventForm` troca o espaço por `T` e define o
  valor por `executeJS`, como a base fazia com o `date`),
  `ListEventsCest` (`d/m/Y H:i`), `UpdateSuccessfullyCest`
  (`seeInField` com `startsAtInputValue()`), `CreateWithInvalidDataCest`
  (agora `-1 hour`: prova a regra por hora no navegador),
  `UpdateWithInvalidDataCest`, `CreateSuccessfullyCest`.
- Testes novos:
  - `EventTest`: formatos inválidos incluem data sem hora,
    `24:00`, `10:60` e `9:00`;
    `test_starts_at_one_hour_ago_should_be_in_the_past`,
    `test_starts_at_in_one_hour_should_be_valid`,
    `test_starts_at_should_accept_the_datetime_local_format` (grava e
    relê do banco), `test_starts_at_input_value_keeps_invalid_text`.
    O antigo `test_starts_on_today_should_be_valid` saiu: com hora,
    "hoje" não é mais sempre válido.
  - `EventsControllerTest`:
    `test_create_accepts_starts_at_from_datetime_local_input`; o
    `test_edit_renders_form_filled` confere `type="datetime-local"` e
    o `value` com `T`; a listagem confere `dd/mm/aaaa 14:30`.

## Por que assim

- Uma coluna `DATETIME` guarda "quando" num lugar só: a regra "não no
  passado" compara um valor, e ordenar/filtrar por data (N3, N5, N9)
  continua sendo uma coluna. A troca mexe em todo lugar que cita
  `starts_on`, mas é só renomear e mudar o formato.
- `validDate` já aceita `$format` (`lib/Validations.php:99-107`); com
  `'Y-m-d H:i'` ele recusa `2030-02-31 10:00` e `24:00` (confere o
  formato de volta). `notPastDate` não serve: zera a hora de hoje
  (`setTime(0, 0)`) e aceitaria 08:00 quando já são 15:00. Em vez de
  criar `notPastDateTime` no lib, a regra ficou no método privado do
  model, que já existia e é uma linha.
- A normalização fica no model (`__set`), não no controller: vale
  para o formulário, para o que vem do banco (`findById` e `all` usam
  `new static($row)`; o `Paginator` usa `new $this->class($row)`, em
  `lib/Paginator.php:143`; os dois passam pelo construtor, que chama
  `__set`) e para os testes. Sem ela, editar um Evento sem mexer na
  hora falharia (`14:30:00` não é `Y-m-d H:i`). O controller continua
  só copiando `FIELDS`.
- Forma (A), alternativa menor: manter `starts_on DATE` e somar
  `starts_time TIME NOT NULL` (e `ends_time TIME NULL`, com
  `ends_time > starts_time` comparando strings `H:i`). Validação
  `validDate('starts_time', $this, 'H:i')`, `<input type="time">`
  preenchido por `executeJS` no Cest, e na view
  `date('d/m/Y', ...) . ' ' . substr($event->starts_time, 0, 5)`.
  Também precisa cortar os segundos (`14:00:00`) antes de validar. A
  regra "não no passado" por hora fica mais chata: junta
  `starts_on . ' ' . starts_time` para comparar.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:populate    # schema mudou
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 162 testes, Integration 46, Acceptance 23
(eram 159 / 45 / 23 na base). PHPCS e PHPStan sem erros;
`db:populate` cria 16 Eventos e 14 Inscrições.

Na tela (admin), a listagem mostra `19/11/2026 08:00` etc. (datas
relativas ao dia do populate); "Editar" abre o campo `datetime-local`
já preenchido. Salvar sem mexer na hora funciona; escolher hoje uma
hora atrás mostra "não pode estar no passado!" sob o campo.

## Relação com outros cenários

- M1 (campo novo): mesmos arquivos (schema, `@property`, `$columns`,
  `validates()`, `FIELDS`, form, views, populate, fixtures). Aqui o
  campo é trocado, não somado.
- M4 (`ends_on`): conflita. M4 compara `ends_on DATE` com
  `starts_on`, que aqui não existe mais. Para aplicar os dois, faça o
  término também `DATETIME` (`ends_at`, formato `Y-m-d H:i`, mesma
  normalização no `__set`) e compare `ends_at >= starts_at` como
  string; ou mantenha `ends_on DATE` e compare com
  `substr($this->starts_at, 0, 10)`.
- N3 (ordenar por data): troque `starts_on` por `starts_at` no
  `ORDER BY`. Com hora, dois Eventos no mesmo dia passam a ter ordem
  definida pela hora.
- N5 (futuros/passados): `starts_on >= CURDATE()` vira
  `starts_at >= NOW()` (ou `:now` com `date('Y-m-d H:i')`, para o
  teste fixar o instante). Um Evento de hoje às 08:00 vira "passado"
  às 09:00, o que muda as contagens dos testes do N5.
- N9 (filtro por período): com `DATETIME`, `BETWEEN '2026-11-01' AND
  '2026-11-30'` deixa de fora o dia 30 depois de 00:00; use
  `starts_at >= :from AND starts_at < :to + 1 dia`.
- Q2 (bloquear Evento passado): passa a considerar a hora.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m7-horario-do-evento`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m7-horario-do-evento`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m7 \
    s50/fvp/4.2/exemplos/m7-horario-do-evento/projeto
git fetch fvp-m7 fvp/m7-horario-do-evento
git cherry-pick f01890f
git remote remove fvp-m7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m7-horario-do-evento/solucao.bundle \
    fvp/m7-horario-do-evento:fvp-m7
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick f01890f
# opcional, limpa a ref
git branch -D fvp-m7
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m7-horario-do-evento/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m7-horario-do-evento/solucao.patch
```

Depois de aplicar: `./run db:populate` (schema mudou), os testes e o
`./run phpcs`.

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

- Tudo que cita `starts_on`: `schema.sql`, `Event.php`, `FIELDS`,
  `_form.phtml`, `index.phtml`, `show.phtml`, `populate.php` e os
  testes. Se o outro cenário trouxer `starts_on` novo, troque por
  `starts_at` e o valor por `Y-m-d H:i`
  (`grep -rn starts_on app database tests` deve ficar vazio).
- `BaseEventsCest::fillEventForm` (bloco do `executeJS`) e
  `startsAt()`: M4 também mexe aí.
- `Event::validates()` e o método "não no passado": Q2 e M4 usam a
  data de início.

## Armadilhas

- O MySQL devolve `DATETIME` como `Y-m-d H:i:s`. Sem normalizar,
  editar um Evento sem mexer na hora dá "não é uma data válida!". A
  prova é
  `EventTest::test_past_event_can_be_edited_when_starts_at_does_not_change`
  (carrega do banco, muda só a descrição e salva). O
  `UpdateSuccessfullyCest` não prova isso: ele troca `starts_at`
  (+90 dias).
- O `datetime-local` envia `2030-05-10T14:30` (com `T`), que
  `validDate(..., 'Y-m-d H:i')` recusa. Por isso a troca no `__set`;
  o `startsAtInputValue()` devolve o `T` no `value`, que é a forma
  normalizada que o navegador usa.
- `notPastDate` compara só o dia: um teste com `-1 hour` passaria
  sem erro. Teste com hora, não só com dia.
- Não use `date('Y-m-d H:i')` "agora" como valor válido nos testes: se
  o minuto virar entre montar e validar, o teste falha. Use `+1 hour`.
- `strtotime` na view com valor inválido dá 1970; a view só mostra o
  que veio do banco (já validado), então ali não acontece.
- Selenium: `fillField` não digita em `datetime-local` de forma
  confiável (depende do idioma); use `executeJS` com `Y-m-d\TH:i`.
- Fuso: PHP em `America/Sao_Paulo`, `DATETIME` sem fuso. Não troque
  por `TIMESTAMP` (o MySQL converte pelo fuso da sessão).
