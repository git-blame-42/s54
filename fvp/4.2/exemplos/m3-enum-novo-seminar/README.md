# M3. Valor novo em ENUM (tipo Seminário)

Pedido, como o professor faria na hora: "Acrescente o tipo de Evento
Seminário. Ele deve aparecer no formulário, ser gravado e aparecer na
listagem e nos detalhes."

Chance: média. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M3.

Branch no clone: `fvp/m3-enum-novo-seminar`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                           |
|-----------|------------------------------------|
| `d50417b` | `feat: Add seminar to event kinds` |

## O que mudou

- `database/schema.sql`: `kind` passa a ser
  `ENUM('lecture', 'workshop', 'short_course', 'academic_week',
  'seminar')`.
- `app/Models/Event.php`: `'seminar' => 'Seminário'` em `KINDS`.
- Views sem mudança: o `select` do `_form.phtml`, a listagem e o
  show percorrem `Event::KINDS`.
- Testes:
  - `EventTest::test_kind_should_save_every_enum_value`: chama
    `save()` (via `createEvent`) para cada chave de `KINDS` e relê do
    banco.
  - `EventTest::test_kind_seminar_should_be_saved_with_its_label`.
  - `EventsControllerTest::test_create_with_seminar_kind_saves_and_shows_label`:
    a opção aparece no formulário, o `create` grava `seminar` e o
    rótulo "Seminário" sai na listagem e no show.
  - `tests/Acceptance/admin/events/CreateSeminarCest.php` (novo):
    cadastro pelo navegador escolhendo "Seminário".

## Por que assim

- O valor tem de estar nos dois lugares. Só na constante: a
  validação `inclusion` aceita, mas o `INSERT` falha com
  `Data truncated for column 'kind'`. Só no schema: a validação
  rejeita com "não é uma opção válida!".
- O teste antigo `test_kind_should_accept_every_enum_value` só chama
  `isValid()`, que não toca no banco, e passa com o schema velho.
  O teste novo usa `save()`. Conferido: com o `schema.sql` da base,
  os dois testes novos de model falham; com o ENUM novo, passam.
- Valor em inglês sem acento (`seminar`, padrão das colunas e
  valores do banco); rótulo em pt-BR (`Seminário`) na constante,
  num só lugar.
- Valor no fim do ENUM: os valores existentes não mudam de posição.

## Como verificar

```
./run up -d db php php_test web
# refaz o schema (o ENUM mudou) e os dados
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 161, Integration 46, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. `./run db:populate` sem
erro. Os testes recriam o schema a cada teste, então passar nos
testes não prova que o banco de desenvolvimento foi atualizado:
rode o `db:populate`.

No navegador: em `/admin/events/new`, o select "Tipo" tem
"Seminário"; cadastre um Evento com ele e veja o rótulo no show e na
listagem.

## Relação com outros cenários

- T1 (`t1-populate-campo-eventos-admin`): o laço dos Eventos 17-30
  usa `array_keys(Event::KINDS)`, então com os dois aplicados alguns
  Eventos gerados viram Seminário sem mudar o populate.
- R3 (`r3-helper-formatacao`): cria `Event::KIND_BADGES`. Com os
  dois, dê uma cor a `seminar` no mapa; o teste
  `test_every_kind_should_have_a_badge` do R3 falha até isso.
- A mesma receita vale para `modality` (ENUM) e `audience` (SET,
  ex.: `alumni` => "Egressos"); o SET já é tratado por
  `audienceList()`/`setAudienceList()`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m3-enum-novo-seminar`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m3-enum-novo-seminar`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m3 \
    s50/fvp/4.2/exemplos/m3-enum-novo-seminar/projeto
git fetch fvp-m3 fvp/m3-enum-novo-seminar
git cherry-pick d50417b
git remote remove fvp-m3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m3-enum-novo-seminar/solucao.bundle \
    fvp/m3-enum-novo-seminar:fvp-m3
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick d50417b
# opcional, limpa a ref
git branch -D fvp-m3
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m3-enum-novo-seminar/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m3-enum-novo-seminar/solucao.patch
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

- `database/schema.sql`: um campo novo (M1) mexe na mesma tabela
  `events`. Fique com a coluna nova e com o ENUM novo.
- `Event.php`, constante `KINDS`: outro valor novo no mesmo ponto;
  mantenha os dois (e os dois no ENUM).
- `EventTest`/`EventsControllerTest`: testes novos no mesmo trecho;
  mantenha todos.

Depois de aplicar: `./run db:populate` (o schema mudou), os testes e
o `./run phpcs`.

## Armadilhas

- Testar só com `isValid()`: passa sem o schema novo. Use `save()`.
- Esquecer o `./run db:populate`: no navegador o `INSERT` falha
  (página 500 ou "Data truncated"), mesmo com os testes verdes.
- Valor com acento ou em pt-BR no banco (`'seminário'`): foge do
  padrão; o rótulo fica na constante.
- Renomear ou reordenar valores antigos do ENUM: os dados existentes
  passam a valer outro tipo ou viram vazio.
- Escrever o `<option>` à mão no `_form.phtml`: o formulário já
  percorre `KINDS`; duplicar quebra a regra "rótulos num só lugar".
