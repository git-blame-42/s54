# M18. Janela de datas: antecedência mínima e limite máximo

Pedido, como o professor faria na hora: "Evento tem que ser cadastrado
com pelo menos 3 dias de antecedência e no máximo 365 dias à frente.
Hoje eu consigo cadastrar para 31/12/9999. Mude a validação do framework e
escreva os testes."

Chance: média-alta. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M18.

Branch no clone: `fvp/m18-janela-de-datas`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `f4b5f1f` | `feat: Event date window with minimum advance and max...` |

Mensagem completa: `feat: Event date window with minimum advance and
maximum limit`.

## O que mudou

- `lib/Validations.php`: `notPastDate` ganhou dois parâmetros opcionais
  no fim, `int $minDays = 0` e `?int $maxDays = null`. A ordem dos
  testes é: data inválida, passado ("não pode estar no passado!"),
  antecedência ("deve ter pelo menos 3 dias de antecedência!") e
  limite ("não pode passar de 365 dias a partir de hoje!"). Helper
  privado `days()` faz o singular ("1 dia"). Sem os parâmetros novos o
  comportamento é o de antes (9999-12-31 continua aceito).
- `app/Models/Event.php`: constantes `STARTS_ON_MIN_DAYS = 3` e
  `STARTS_ON_MAX_DAYS = 365`. O método privado `validatesStartsOnNotPast`
  virou `validatesStartsOnWindow` e chama
  `notPastDate('starts_on', $this, minDays: ..., maxDays: ...)` com
  argumentos nomeados. Continua valendo a regra da base: na edição, a
  data só é validada se `starts_on` mudou.
- `app/views/admin/events/_form.phtml`: o `input type="date"` ganhou
  `min` e `max` (hoje + 3 e hoje + 365) e um `form-text` "De 3 a 365
  dias a partir de hoje.", depois do `invalid-feedback`.
- Testes:
  - `NumberAndDateValidationsTest`: 5 testes novos. Um sem `$now`
    (sem janela, aceita 9999-12-31) e quatro com `$now` fixo
    (2026-11-05): limites +2/+3 e +365/+366; data passada mantém a
    mensagem de passado; singular.
  - `EventTest`: `test_starts_on_today_should_be_valid` saiu (hoje
    deixou de ser válido) e entraram 6 testes: antecedência, dentro da
    janela, depois do limite (inclui 9999-12-31), offsets do populate
    (+14 e +82), data nova fora da janela na edição, e Evento a 1 dia
    editável sem mudar a data.
  - `EventsControllerTest`: create com 9999-12-31 volta ao form com o
    erro; form `new` tem `min`, `max` e o texto de ajuda.
  - `CreateWithInvalidDataCest::createWithDateOutsideTheWindow`
    (+1 dia e +400 dias). `BaseEventsCest::validEventData` passou de
    `+1 day` para `+7 days`.

## Por que assim

- A regra fica no `Validations` (reutilizável) e o número fica no
  model, em constante: o professor pede "3 dias" hoje e "5 dias"
  amanhã, e só a constante muda.
- Parâmetros novos no fim, com padrão neutro: nenhuma chamada antiga
  de `notPastDate` muda de sentido. No model, argumentos nomeados
  pulam `$format` e `$now` sem repetir os padrões.
- `$now` injetável (já existia) deixa os testes de limite
  determinísticos; os testes do model usam datas relativas, como os
  da base.
- Data passada mantém a mensagem antiga: "não pode estar no passado!"
  é mais clara do que "3 dias de antecedência" para 2020-01-01, e os
  testes/Cests da base continuam valendo.
- A edição só valida a data quando ela muda. Sem isso, um Evento que
  já está a menos de 3 dias (ou no passado) não poderia mais ter a
  descrição corrigida.
- `min`/`max` no input são só ajuda no seletor: o form tem
  `novalidate`, então o navegador não bloqueia o envio e quem decide é
  o `validates()`.

## Como verificar

```
./run up -d
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5 && ./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 169, Integration 47, Acceptance 24 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. `db:populate` continua criando os
16 Eventos (ele salva com validação e lançaria exceção se algum caísse
fora da janela). Schema não mudou.

Na tela: em `/admin/events/new`, data de amanhã mostra "deve ter pelo
menos 3 dias de antecedência!"; 31/12/9999 mostra "não pode passar de
365 dias a partir de hoje!".

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`): mesmo formato (validação no
  `Validations`, número no model, testes de unidade com o limite e o
  limite + 1). Aqui a validação já existia e ganhou parâmetros; lá é
  uma função nova.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m18-janela-de-datas`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m18-janela-de-datas
cd /caminho/do/seu/repositorio
git remote add fvp-m18 \
    s50/fvp/4.2/exemplos/m18-janela-de-datas/projeto
git fetch fvp-m18 fvp/m18-janela-de-datas
git cherry-pick f4b5f1f
git remote remove fvp-m18
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/m18-janela-de-datas/solucao.bundle \
    fvp/m18-janela-de-datas:fvp-m18
git cherry-pick f4b5f1f
# opcional, limpa a ref
git branch -D fvp-m18
```

### 3. A partir do `solucao.patch`

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m18-janela-de-datas/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m18-janela-de-datas/solucao.patch
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

Arquivos que costumam conflitar neste cenário e por quê:

- `lib/Validations.php` (`notPastDate`): outros cenários de data (data
  dd/mm/aaaa, segunda data `ends_on`) também mexem na assinatura. Fique
  com todos os parâmetros, os de janela por último.
- `app/Models/Event.php` (`validates` e o método de data): mantenha a
  checagem "só valida se a data mudou" e a chamada com `minDays`/
  `maxDays`.
- `_form.phtml` (bloco da data) e `EventTest.php`/`BaseEventsCest.php`:
  fique com os dois lados; confira se algum teste do outro cenário usa
  data a menos de 3 dias (hoje, `+1 day`) e passe para `+7 days`.

Depois de aplicar: `./run db:populate` e os testes de novo.

## Armadilhas

- `test_starts_on_today_should_be_valid` da base inverte: hoje passa a
  ser inválido. Apague ou troque pelo teste de antecedência; não
  "conserte" a validação para fazê-lo passar.
- `BaseEventsCest::validEventData` usava `+1 day`: o
  `CreateSuccessfullyCest` quebra até mudar para algo dentro da janela.
- O populate usa hoje + 14 até hoje + 82 dias. Antecedência maior que
  14 ou limite menor que 82 faz o `db:populate` lançar "Evento inválido
  no populate"; ajuste os offsets juntos.
- Outros testes usam datas fixas relativas (+5, +10, +30, +90 dias):
  todos cabem em 3..365. Se o professor pedir outro intervalo, rode
  `grep -rn strtotime tests database` antes.
- Limite inclusivo nas duas pontas: +3 e +365 valem; +2 e +366 não.
  Compare com `$today->modify("+N days")`, não com `strtotime` + 86400
  segundos.
- "1 ano" não é "365 dias". Se o professor pedir "no máximo 1 ano à
  frente", `modify('+1 year')` dá a mesma data no ano seguinte: a
  janela tem 366 dias quando passa por um 29 de fevereiro, e o limite
  +365 recusaria o último dia. E `modify('+1 year')` a partir de
  29/02 cai em 01/03 (não existe 29/02 no ano seguinte). Escolha um
  dos dois e diga qual: aqui ficou `$maxDays` em dias (365); para
  "1 ano" de calendário, troque o parâmetro por um `modify` e teste
  com `$now` em 29/02/2028.
- `min`/`max` no input sem `novalidate` no form travariam o envio no
  navegador (e os Cests que testam data inválida), e na edição de um
  Evento já próximo o seletor não deixaria salvar.
