# P1. Código de check-in aleatório (1000–9999) por Evento

Pedido, como o professor faria na hora: "Todo Evento precisa de um
código de check-in de 4 dígitos, entre 1000 e 9999, gerado
aleatoriamente pelo sistema no cadastro. O código não pode se repetir
entre Eventos e não vem do formulário. Mostre o código na listagem e
na página do Evento. Atualize o populate e escreva os testes."

Chance: alta. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P1. É o análogo, no CRUD de Eventos, da FVP
real da 4.1 (código aleatório no login, P12).

Branch no clone: `fvp/p1-codigo-checkin-aleatorio`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                |
|-----------|---------------------------------------------------------|
| `e2c5568` | `feat: Generate a random check-in code for each Event`  |

## O que mudou

- `database/schema.sql`: coluna `checkin_code SMALLINT UNSIGNED NOT
  NULL` em `events` e `UNIQUE KEY uq_events_checkin_code`.
- `app/Models/Event.php`:
  - `@property int|string|null $checkin_code` e `'checkin_code'` em
    `$columns`;
  - constantes `CHECKIN_CODE_MIN = 1000` e `CHECKIN_CODE_MAX = 9999`;
  - `public static function newCheckinCode(): int`: sorteia com
    `random_int(1000, 9999)` e repete enquanto
    `self::exists(['checkin_code' => $code])`;
  - `save()` sobrescrito: se o registro é novo e o código está vazio,
    `$this->checkin_code = self::newCheckinCode();` e então
    `parent::save()`;
  - em `validates()`: `notEmpty` → `integerMin(1000)` → teto 9999
    (método privado `validatesCheckinCodeMax()`, pois `lib/` não tem
    `integerMax`) → `Validations::uniqueness('checkin_code', $this)`.
    Esse bloco só roda se o Evento já existe ou se alguém definiu um
    código: Evento novo sem código é válido, porque o `save()` gera o
    código antes do `isValid()` do `parent::save()`.
- `app/Controllers/Admin/EventsController.php`: sem mudança. A
  constante `FIELDS` não ganhou `checkin_code`: o `fill` ignora o
  campo se ele vier no POST, no `create` e no `update`.
- Views: coluna "Código" na listagem (`index.phtml`) e linha
  "Código de check-in" no `show.phtml` (`<dd id="event_checkin_code">`).
- `database/Populate/populate.php`: sem mudança; os 16 Eventos ganham
  código sozinhos no `save()`.
- Os helpers de teste que criam Evento direto pelo model
  (`EventTest::params`, `EnrollmentTest`, `EventsAccessTest`,
  `BaseEventsCest`...) também não mudam, pelo mesmo motivo.

## Por que assim

- Geração no `save()` do model: é regra de negócio, então fica no
  model (gerar no controller o professor recusa). Todo caminho que
  cria Evento (controller, populate, testes, Cests) ganha o código sem
  repetir código, e o controller nem sabe que o campo existe.
- `static`: sortear o código não depende de um Evento carregado; o
  método só consulta a tabela. A consulta usa `exists` (que usa
  `where` com `prepare`/`bindValue`), então não há SQL no controller.
- Validação dupla de propósito: `uniqueness` no model dá a mensagem
  para o usuário; o `UNIQUE KEY` no banco garante a regra mesmo numa
  corrida entre dois cadastros simultâneos.
- `Model::save()` grava todas as colunas de `$columns`. Por isso um
  `DEFAULT` no schema não resolveria: o INSERT mandaria `NULL`. O
  código precisa estar no objeto antes do INSERT, e o `save()`
  sobrescrito garante isso.
- `SMALLINT UNSIGNED` (0 a 65535) cabe a faixa inteira; a faixa
  1000–9999 é cobrada no model.
- O código fica fora de `FIELDS`: é o mesmo cuidado que o `user_id`
  já tinha (nunca vem do formulário).

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

Resultado obtido: Unit 166, Integration 49, Acceptance 23 (base: 159 /
45 / 23). PHPCS e PHPStan sem erros. O populate roda sem erro e gera
16 códigos distintos (`SELECT COUNT(DISTINCT checkin_code) FROM
events` dá 16).

Testes novos:

- `EventTest`:
  - `test_new_checkin_code_should_be_between_1000_and_9999`
  - `test_new_checkin_code_should_not_return_a_code_in_use`
  - `test_save_should_generate_the_checkin_code_of_a_new_event`
  - `test_existing_event_with_blank_checkin_code_should_be_invalid`
  - `test_checkin_code_should_be_unique`
  - `test_checkin_code_outside_the_range_should_be_invalid`
  - `test_existing_event_should_keep_its_checkin_code_when_updated`
- `EventsControllerTest`:
  - `test_create_generates_the_checkin_code`
  - `test_create_ignores_checkin_code_sent_by_the_form`
  - `test_update_does_not_change_the_checkin_code`
  - `test_index_and_show_display_the_checkin_code`
- `CreateSuccessfullyCest::createEventSuccessfully`: confere o código
  no `show` e a faixa no banco.

Na tela (dentro da rede do compose):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/1 | grep -A1 'check-in'
```

## Relação com outros cenários

- P4 (`p4-confirmar-presenca-codigo`) usa este código: o Participante
  inscrito digita o código para confirmar presença. P4 parte de P2 com
  este commit aplicado por cima (cherry-pick).
- P6 (`p6-duplicar-evento`): se os dois forem aplicados, a cópia
  precisa de um código novo: zere `$copy->checkin_code = null;` em
  `duplicate()` e o `save()` gera outro (senão o `uniqueness` barra).
- P12 (catálogo, FVP real da 4.1): mesma ideia de `random_int` com
  faixa fixa, ali para o login.
- O1: se o professor pedir "regenerar o código", é uma ação nova
  (`PUT /admin/events/{id}/checkin_code`) que chama `newCheckinCode()`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p1-codigo-checkin-aleatorio`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p1-codigo-checkin-aleatorio`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p1 \
    s50/fvp/4.2/exemplos/p1-codigo-checkin-aleatorio/projeto
git fetch fvp-p1 fvp/p1-codigo-checkin-aleatorio
git cherry-pick e2c5568
git remote remove fvp-p1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p1-codigo-checkin-aleatorio/solucao.bundle \
    fvp/p1-codigo-checkin-aleatorio:fvp-p1
git cherry-pick e2c5568
# opcional, limpa a ref
git branch -D fvp-p1
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p1-codigo-checkin-aleatorio/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p1-codigo-checkin-aleatorio/solucao.patch
```

Depois de aplicar: `./run db:populate` (o schema mudou) e os testes.

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

- `database/schema.sql` e `Event::$columns`: todo cenário de campo
  novo (M1, P5...) mexe no fim da lista. Fique com as duas colunas.
- `Event::validates()`: outras validações no fim do método; mantenha
  as duas.
- `Event::save()`: outro cenário que também sobrescreva `save()`
  (ex.: P5 definindo `status`); junte as duas regras num só método.
- `EventsControllerTest`/`EventTest`: testes novos no mesmo ponto;
  fique com os dois.
- `index.phtml`: outra coluna nova na mesma tabela.

## Armadilhas

- Esquecer `checkin_code` em `$columns`: o valor vai para o objeto mas
  não para o INSERT, e o MySQL recusa (`NOT NULL` sem default).
- Pôr `checkin_code` em `FIELDS` ou num `<input>` do formulário: o
  pedido diz que não vem do form, e o teste
  `test_create_ignores_checkin_code_sent_by_the_form` pega isso.
- Gerar no controller ("regra de negócio no model") ou na view.
- Gerar sem o `newRecord()`: troca o código de um Evento já divulgado
  a cada edição. Gere só ao criar.
- `rand()`/`mt_rand()` funcionam, mas `random_int` é o gerador
  criptograficamente seguro do PHP; é o que o professor espera ver.
- Sem o laço `do { } while (exists)`, dois Eventos podem sortear o
  mesmo número (1 em 9000 por par); o `save()` falharia no
  `uniqueness` e o admin veria "dados incorretos" sem ter errado nada.
- `uniqueness` na edição compara com o próprio registro no banco; por
  isso salvar o Evento sem mudar o código continua válido.
- Validar `notEmpty('checkin_code')` sempre: `isValid()` num Evento
  novo (antes do `save()`) acusaria código vazio e quebraria dezenas
  de testes (`test_valid_event_should_be_valid`, o requiredFields...).
  Por isso o bloco só roda para registro existente ou código definido.
  Alternativa citada no catálogo: gerar no construtor.
- `exists()` usa `where` com igualdade: serve para uma condição.
