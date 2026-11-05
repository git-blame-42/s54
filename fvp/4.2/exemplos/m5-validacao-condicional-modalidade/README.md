# M5. Validação condicional por modalidade

Pedido, como o professor faria na hora: "Evento online não tem local:
o local só é obrigatório para presencial e híbrido. Em compensação,
Evento online precisa de um link. Ajuste o model, o formulário e os
testes."

Chance: média. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M5.

Branch no clone: `fvp/m5-validacao-condicional-modalidade`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `2e39004` | `feat: Require location only for in-person events and...` |

Mensagem completa: `feat: Require location only for in-person events
and link for online events` (corpo: coluna `link` nova, `location`
passa a aceitar `NULL`).

## O que mudou

- `database/schema.sql`: `location VARCHAR(255) NULL` (era `NOT NULL`)
  e coluna nova `link VARCHAR(255) NULL`.
- `app/Models/Event.php`: `link` em `$columns` e no PHPDoc; em
  `validates()`, o `notEmpty('location')` fixo vira:

```php
// Online exige link; presencial e híbrido exigem local.
if ($this->modality === 'online') {
    Validations::notEmpty('link', $this);
} else {
    Validations::notEmpty('location', $this);
}
```

- `EventsController`: `'link'` na lista `FIELDS` (o `fill` só copia
  os campos dessa lista).
- `_form.phtml`: campo "Link" (`event[link]`, `type="url"`) depois de
  "Local", com dica "Obrigatório para Eventos online." nos dois.
- `show.phtml`: linha "Link"; `show` e `index` passam a usar
  `htmlspecialchars((string) $event->location)`.
- `populate.php`: os 3 Eventos online ficam sem local (`null`); online
  e híbridos ganham `https://meet.example.com/evento-N`.
- Testes:
  - `EventTest`: presencial e híbrido sem local inválidos (só
    `location` com erro); presencial e híbrido sem link válidos; online
    sem link inválido; online sem local salva e volta do banco com
    `location` `null`. `errorsOf()` passa a olhar `link`.
    `test_modality_should_accept_every_enum_value` agora manda link.
  - `EventsControllerTest`: online sem link marca só o `link`; online
    com link e sem local salva e o show mostra o link; presencial sem
    local marca só o `location`. O teste de edição confere o link
    preenchido no form.
  - Fixtures com `modality => 'online'` ganharam `link`
    (`EnrollmentTest`, `EventsAccessTest`, `BaseEventsCest`:
    `seedEvents`, `validEventData`, `fillEventForm`).
  - Cest novo `tests/Acceptance/admin/events/CreateByModalityCest.php`
    (online sem link: erro só no link; preenche o link e salva;
    presencial sem local: erro só no local).

## Por que assim

- Decisão: coluna nova `link` em vez de reaproveitar `location`. O
  pedido fala em "local" e "link" como coisas diferentes e diz que
  online não tem local; guardar link dentro de "Local" deixaria a
  listagem e o show com um rótulo errado. O catálogo traz a outra
  forma (link dentro de `location`), que muda menos: ver a variante
  abaixo. Se o professor disser "no campo local", use a variante.
- A regra fica em `validates()`, com `Validations::notEmpty`; só a
  escolha do campo depende da modalidade. Nada no controller.
- `modality` vazia ou inválida cai no `else` e exige local: é o
  comportamento de antes (e o form vazio continua marcando `location`).
- Híbrido: o pedido só exige local. O link fica opcional (o populate
  preenche, como transmissão).
- `location` precisa aceitar `NULL`: `Model::save()` grava todas as
  colunas, e um Evento online criado sem local (populate, testes)
  manda `NULL`. Pelo form, campo vazio chega como `''`.
- Cast `(string)` nas views: `htmlspecialchars(null)` gera deprecation,
  que o `ErrorsHandler` transforma em 500 na listagem.
- Formato de URL não é validado (o pedido só diz "exige link"); ver a
  receita abaixo.

### Variante do catálogo: link dentro de `location`

Sem schema, form ou view novos. Online exige que o local seja uma URL:

```php
if ($this->modality === 'online') {
    Validations::notEmpty('location', $this) &&
        $this->validatesLocationIsUrl();
} else {
    Validations::notEmpty('location', $this);
}

private function validatesLocationIsUrl(): void
{
    if (filter_var($this->location, FILTER_VALIDATE_URL) === false) {
        $this->addError('location', 'deve ser um link (URL) válido!');
    }
}
```

Depois troque para `https://...` o local de toda fixture online:
`EnrollmentTest` ("Online"), `EventsAccessTest` (2), `validEventData`
e `seedEvents` do `BaseEventsCest`, `CreateWithInvalidDataCest`
("Laboratório B203" com online) e os 3 online do populate. Rótulo do
campo: "Local ou link".

### Receita: validar o formato do link (coluna nova)

```php
Validations::notEmpty('link', $this) && $this->validatesLinkIsUrl();
```

com o mesmo método privado acima trocando `location` por `link`.

## Como verificar

```
./run up -d db php php_test web
# schema mudou: refaz o banco de desenvolvimento
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 165 testes, Integration 48, Acceptance 25
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. `db:populate` cria
os 16 Eventos e 14 Inscrições sem erro.

Testes novos:

- `EventTest`:
  - `test_in_person_and_hybrid_events_should_require_location` (2)
  - `test_in_person_and_hybrid_events_should_not_require_link` (2)
  - `test_online_event_should_require_link`
  - `test_online_event_should_not_require_location`
- `EventsControllerTest`:
  - `test_create_online_event_without_link_marks_link_field`
  - `test_create_online_event_with_link_and_without_location_saves_event`
  - `test_create_in_person_event_without_location_marks_location_field`
- `CreateByModalityCest`:
  - `onlineEventRequiresLinkButNotLocation`
  - `inPersonEventRequiresLocation`

No navegador: "Novo Evento", modalidade Online, local e link vazios:
erro só sob "Link". Preencha o link e salve: o show mostra o link e o
local vazio. Com Presencial e local vazio, erro só sob "Local". Por
HTTP, `/admin/events/2` (online do populate) mostra
`https://meet.example.com/evento-2`.

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`) e M6 (`m6-unicidade-titulo-data`)
  mexem nas linhas vizinhas de `validates()`; junte as chamadas.
- S4 (`s4-testes-de-borda`): testes de borda escritos sobre a base
  supõem `location` sempre obrigatório; com este cenário, um Evento
  online sem local passa a ser válido.
- T2 (`t2-phpcs-phpstan-verdes`): a lista de campos do `errorsOf()`
  passou de 130 colunas; virou variável `$fields`.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m5-validacao-condicional-modalidade`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh \
    s50/fvp/4.2/exemplos/m5-validacao-condicional-modalidade
cd /caminho/do/seu/repositorio
git remote add fvp-m5 \
    s50/fvp/4.2/exemplos/m5-validacao-condicional-modalidade/projeto
git fetch fvp-m5 fvp/m5-validacao-condicional-modalidade
git cherry-pick 2e39004
git remote remove fvp-m5
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/m5-validacao-condicional-modalidade/solucao.bundle \
    fvp/m5-validacao-condicional-modalidade:fvp-m5
git cherry-pick 2e39004
# opcional, limpa a ref
git branch -D fvp-m5
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/m5-validacao-condicional-modalidade
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

Depois de aplicar: `./run db:populate` (o schema mudou).

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

- `database/schema.sql` e `$columns` do `Event`: M1 (campo novo) e M3
  (ENUM/SET) mexem no mesmo bloco. Fique com as duas colunas.
- `EventsController::FIELDS` e `_form.phtml`: outro campo novo na
  mesma linha/ponto; mantenha os dois.
- `populate.php`: o array do `new Event([...])` no laço; junte as
  chaves.
- `BaseEventsCest` (`seedEvents`, `validEventData`, `fillEventForm`):
  outros cenários que mudam o form também mexem aí.
- `EventTest::errorsOf()`: junte os campos na lista `$fields`.

## Armadilhas

- Esquecer `link` em `FIELDS` do controller: o form envia, o `fill`
  ignora, e todo Evento online falha com "não pode ser vazio!" no link.
- Manter `location NOT NULL`: Evento online sem local dá
  `Integrity constraint violation` no `save()` (o model manda `NULL`).
- Schema mudou: rode `./run db:populate`. Os testes recriam o schema
  sozinhos, então passar nos testes não prova que o banco de
  desenvolvimento foi atualizado.
- `htmlspecialchars($event->location)` sem `(string)`: 500 na
  listagem assim que existir Evento online sem local.
- Toda fixture com `'modality' => 'online'` precisa de link, senão o
  `save()` devolve `false` em silêncio (várias fixtures não conferem o
  retorno) e o teste falha mais adiante, longe da causa. Procure com
  `grep -rn "'online'" tests/`.
- `requiredFieldsProvider` do `EventTest` continua valendo: o
  `params()` padrão é presencial, então `location` vazio ainda é erro.
- O `.invalid-feedback` continua irmão do input (a dica `.form-text`
  fica no meio): o seletor `#event_link ~ .invalid-feedback` funciona.
