# M22. Limite de N Eventos por dia

Pedido, como o professor faria na hora: "No máximo 3 eventos no mesmo
dia. Ao cadastrar ou editar um quarto Evento na mesma data, mostre o
erro no formulário."

Chance: média-baixa. Tempo estimado: 20-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M22.

Branch no clone: `fvp/m22-limite-por-dia`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit | Mensagem |
|--------|----------|
| `ba36010` | `feat: Limit events per day` |

## O que mudou

- `app/Models/Event.php`:
  - constante `MAX_EVENTS_PER_DAY = 3`;
  - o encadeamento de `starts_on` em `validates()` virou `if`, porque
    `validatesStartsOnNotPast()` devolve `void` e não dá para
    encadear mais uma regra depois dela com `&&` (aqui os trechos
    estão quebrados para caber em 80 colunas):

```php
if (Validations::notEmpty('starts_on', $this)
    && Validations::validDate('starts_on', $this)) {
    $this->validatesStartsOnNotPast();
    $this->validatesEventsPerDayLimit();
}
```

  - método privado novo:

```php
private function validatesEventsPerDayLimit(): void
{
    $sameDay = array_filter(
        self::where(['starts_on' => $this->starts_on]),
        fn(Event $event) => $event->id !== $this->id
    );

    if (count($sameDay) >= self::MAX_EVENTS_PER_DAY) {
        $this->addError('starts_on', 'já tem '
            . self::MAX_EVENTS_PER_DAY
            . ' eventos neste dia, escolha outra data!');
    }
}
```

- Controller e views sem mudança: o erro sai em `errors('starts_on')`
  e o `_form.phtml` já o mostra sob o campo de data.
- `tests/Unit/Models/Events/EventTest.php`: 5 testes novos (3 no mesmo
  dia é válido; o 4º falha com a mensagem e não grava; outro dia não
  conta; editar um dos 3 sem mudar a data funciona; mover um Evento
  de outro dia para o dia cheio falha e não grava).
- `tests/Integration/Controllers/Admin/EventsControllerTest.php`: 2
  testes novos (create no dia cheio volta ao form com o erro e o
  flash; update de um dos 3 mantendo a data redireciona para o show).
- Testes de paginação que criavam 7 ou 12 Eventos com a mesma data
  (`EventTest` e `EventsControllerTest`) agora usam `+{$i} days`: com
  o limite, o 4º Evento deixava de ser salvo.
- `populate.php` sem mudança: os 16 Eventos têm datas diferentes.

## Por que assim

- Regra de negócio fica no model, em `validates()`, como as outras.
  A consulta usa `Model::where` (igualdade, `prepare` + `bindValue`
  no framework); nenhum SQL novo.
- `$event->id !== $this->id`: no create, `$this->id` é `null` e
  nenhum Evento é descartado; no update, o próprio Evento sai da
  contagem. O `id` do framework é `?int`, então `!==` é seguro.
- `>=` e não `>`: o novo Evento ainda não está no banco; com 3 outros
  no dia, ele seria o 4º.
- Erro em `starts_on` (não em `base`): o form marca o campo de data,
  que é o que o usuário precisa trocar.
- Só roda depois de `notEmpty` e `validDate`: com data vazia ou
  inválida não há o que contar, e a mensagem de limite não pode
  sobrescrever "não é uma data válida!" (cada campo guarda uma
  mensagem só).
- Constante pública: os testes usam `Event::MAX_EVENTS_PER_DAY`, e
  mudar o N é trocar um número.

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
# o populate continua válido (datas distintas)
./run db:populate
```

Resultado obtido: Unit 164 testes, Integration 47, Acceptance 23
(eram 159 / 45 / 23 na base). PHPCS e PHPStan sem erros. Schema não
mudou; `db:populate` cria os 16 Eventos sem erro.

Sem o filtro por `id`,
`test_update_on_a_full_day_should_not_count_the_event_itself` falha
(o update conta a si mesmo e recusa o próprio Evento).

Na tela (logado como admin): cadastre três Eventos na mesma data e
tente um quarto. O form volta com "Existem dados incorretos!" e, sob
a data, "já tem 3 eventos neste dia, escolha outra data!". Editar o
título de um dos três continua funcionando. Conferido por HTTP: os
três primeiros POST deram `302`, o quarto `200` com o erro.

## Relação com outros cenários

- M6 (`m6-unicidade-titulo-data`): também valida pela data, mas com
  `Validations::uniqueness` (no máximo 1 com o mesmo título e data).
  A variante "mesmo local no mesmo dia" do M6 é este cenário com
  N = 1. Se aplicar os dois, os dois blocos ficam em `validates()`;
  ponha o do M6 depois, já que ele só roda se `starts_on` não tiver
  erro.
- Variante local + dia: troque a condição por
  `self::where(['starts_on' => $this->starts_on,
  'location' => $this->location])` e rode a regra só se `location`
  também passou no `notEmpty`. Ajuste a mensagem ("neste local").
  Repare que `where` é igualdade exata: "Sala A" e "sala a " contam
  separado.
- N11 (`n11-model-count`): com `Model::count()`, a contagem fica
  `Event::count(['starts_on' => $this->starts_on])`, mas aí não dá
  para tirar o próprio id (só igualdade). Seria preciso descontar 1
  quando é update e a data não mudou (buscando o original com
  `findById`, como `validatesStartsOnNotPast()` faz). Mais simples:
  ficar com o `array_filter` deste cenário.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m22-limite-por-dia`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m22-limite-por-dia`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m22 \
    s50/fvp/4.2/exemplos/m22-limite-por-dia/projeto
git fetch fvp-m22 fvp/m22-limite-por-dia
git cherry-pick ba36010
git remote remove fvp-m22
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m22-limite-por-dia/solucao.bundle \
    fvp/m22-limite-por-dia:fvp-m22
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick ba36010
# opcional, limpa a ref
git branch -D fvp-m22
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m22-limite-por-dia/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m22-limite-por-dia/solucao.patch
```

Depois de aplicar: os testes e o `./run phpcs` (schema não mudou).

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

- `Event.php`, bloco de `starts_on` em `validates()`: M4, M6, G2 e
  outros cenários de data mexem nas mesmas linhas. Mantenha um `if`
  com `notEmpty` + `validDate` e chame as regras dos dois dentro dele.
- `EventTest.php` e `EventsControllerTest.php`: testes novos no mesmo
  ponto; fique com os dois. Se o outro cenário também cria vários
  Eventos com a data padrão (`+30 days` / `+10 days`), espalhe as
  datas, senão o 4º não é salvo.

## Armadilhas

- O update conta a si mesmo: sem o filtro por `id`, um dia com 3
  Eventos não deixa editar nem a descrição de nenhum deles. Teste a
  edição, não só o create.
- Use `>=` com a contagem dos outros. `> 3` deixaria entrar o 4º.
- Testes existentes que criam muitos Eventos com a mesma data
  (paginação) quebram com a regra nova; o erro aparece como
  "Failed asserting that false is true" no `createEvent`. Espalhe as
  datas em vez de afrouxar a regra.
- Confira o `populate.php`: se dois ou mais Eventos caírem no mesmo
  `+N days` acima do limite, o `db:populate` para com "Evento inválido
  no populate". Hoje as datas são todas diferentes.
- Dado antigo: se o dia já tinha mais que N Eventos antes da regra,
  editar qualquer um deles sem mudar a data falha. Para liberar, conte
  só quando `starts_on` muda (como `validatesStartsOnNotPast()` faz),
  se o professor pedir.
- `Model::where` carrega os objetos para contar; para "3 por dia" isso
  é irrelevante. Com N11 aplicado, veja a nota acima.
