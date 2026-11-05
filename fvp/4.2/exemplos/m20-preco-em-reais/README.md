# M20. Preço em reais com vírgula ("Gratuito" se 0)

Pedido, como o professor faria na hora: "O Evento agora pode ser pago.
Acrescente o preço (`price`). O usuário digita `10,50`, com vírgula;
a listagem e o detalhe mostram `R$ 10,50`, e `Gratuito` quando o preço
é zero. Valor inválido dá erro embaixo do campo."

Chance: média. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M20.

Branch no clone: `fvp/m20-preco-em-reais`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                |
|-----------|-----------------------------------------|
| `59fd70a` | `feat: Add price in reais to events`    |

## O que mudou

- `database/schema.sql`: `price DECIMAL(8, 2) NOT NULL` depois de
  `capacity` e `CONSTRAINT check_events_price CHECK (price >= 0)`.
- `lib/Validations.php`: `decimal($attribute, $obj, int $digits,
  int $decimals)`. Aceita vírgula ou ponto com a regex
  `/^\d{1,6}([.,]\d{1,2})?$/D` (6 e 2 vêm dos parâmetros). Erro:
  "deve ser um valor entre 0 e 999999,99, como 10,50!".
- `app/Models/Event.php`:
  - `@property int|string $price` e a chave em `$columns`;
  - em `validates()`: `notEmpty('price') && decimal('price', 6, 2)`;
    se passou, `str_replace(',', '.', ...)` troca a vírgula por ponto
    antes do `INSERT`/`UPDATE`;
  - `formattedPrice()`: `Gratuito` se o valor é 0, senão
    `'R$ ' . number_format($preco, 2, ',', '.')` (`R$ 1.234,50`);
  - `priceForInput()`: o valor do banco (`10.50`) volta ao formulário
    como `10,50`; valor inválido volta como foi digitado.
- `app/Controllers/Admin/EventsController.php`: `'price'` em `FIELDS`.
  O controller não converte nada.
- `app/views/admin/events/_form.phtml`: campo "Preço (R$)" com
  `type="text" inputmode="decimal"`, `placeholder="10,50"`, erro
  embaixo e a dica "Use vírgula nos centavos. Informe 0 para evento
  gratuito.".
- `index.phtml`: coluna "Preço"; `show.phtml`: par `dt`/`dd` "Preço".
  As duas usam `htmlspecialchars($event->formattedPrice())`.
- `database/Populate/populate.php`: minicursos custam `49.90`, os
  demais Eventos são gratuitos (`0`).
- Testes ajustados com a chave nova: `params()` do `EventTest`,
  `EnrollmentTest`, `EventsAccessTest` (`setUp` e o POST do
  Participante), `validParams()` do `EventsControllerTest` (`10,50`),
  `BaseEventsCest` (`seedEvents` com o Evento 02 a `10.50`,
  `validEventData` com `10,50` e `fillEventForm` com `price`).
- Testes novos ou ampliados:
  - `NumberAndDateValidationsTest`: `decimal` aceita `0`, `10,5`,
    `10.50`, `999999,99`; recusa `1e3`, `-1`, `+1`, `10,505`,
    `1.234,50`, `1234567`, `10,`, `,50`, `' 10'`, `"10\n"`, float,
    array e `true`; usa os dígitos informados.
  - `EventTest`: `price` nos obrigatórios; vírgula vira ponto e o
    banco devolve `10.50`, `999999.99`, `7.50`, `0.00`; inválidos não
    salvam e não são convertidos; `formattedPrice()` e
    `priceForInput()`.
  - `EventsControllerTest`: cria com `10,50` e vê `R$ 10,50` no show,
    na listagem e `value="10,50"` no editar; preço `0` mostra
    `Gratuito`; `1e3`, `1000000`, `-5` e `10,505` dão erro embaixo do
    campo sem gravar.
  - Cests: listagem (`Gratuito` no Evento 01, `R$ 10,50` no 02),
    cadastro (`R$ 10,50` no show, banco `10.50`), edição (abre com
    `10,50`, salva `0`, mostra `Gratuito`), formulário vazio marca
    `price`, `10,5` volta como `10,50` quando outro campo erra e
    `1e3` mostra o erro embaixo do campo.

## Por que assim

- `DECIMAL(8,2)` e não `FLOAT`: dinheiro precisa de centavos exatos.
  8 dígitos com 2 decimais dá no máximo `999999.99`; por isso a regex
  limita a 6 dígitos antes da vírgula.
- Regex e não `FILTER_VALIDATE_FLOAT`: o filtro aceita `1e3`, `-1` e
  `+1`, e com `FILTER_FLAG_ALLOW_THOUSAND` aceita `1,000` como mil. A
  regex aceita só o que um usuário brasileiro digita.
- Modificador `D` na regex: sem ele, `$` aceita uma quebra de linha
  no fim (`"10\n"` passaria e o MySQL recusaria).
- A validação fica em `lib/Validations.php` (como `integerMin`), e o
  model só chama. A troca de vírgula por ponto também fica no model,
  depois de validar: o controller continua fino e sem `(float)`.
- Obrigatório, sem `DEFAULT`: `Model::save()` insere todas as colunas
  de `$columns`, então um `DEFAULT 0` no schema nunca seria usado.
  Evento gratuito é `0`, e a dica do formulário diz isso.
- `type="text"` e não `type="number"`: conforme o idioma do
  navegador, o campo `number` recusa a vírgula e envia o valor vazio.
  `inputmode="decimal"` ainda abre o teclado numérico no celular.
- Formatação num só lugar (`formattedPrice()` no model), como em R3:
  listagem e show chamam o mesmo método.
- O editar mostra `10,50`, igual ao que o usuário digitou no cadastro.

## Como verificar

```
./run up -d
# refaz o schema (o populate roda migrate()) e popula
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
```

Resultado obtido: Unit 167 testes, Integration 48, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

No navegador (admin@example.com / 123456): `/admin/events` tem a
coluna "Preço", com `Gratuito` e `R$ 49,90` no minicurso da primeira
página. Em "Novo Evento", `10,50` grava `10.50` e o show mostra
`R$ 10,50`; `1e3` ou `1000000` mostram o erro embaixo do campo.

## Relação com outros cenários

- M1 (`m1-campo-novo-workload-hours`): mesmo roteiro de campo novo
  (schema, `$columns`, `FIELDS`, form, views, populate, testes). Os
  dois mexem nas mesmas linhas; se aplicar os dois, fique com as duas
  chaves em cada lista.
- R3 (`r3-helper-formatacao`): também põe a formatação no model
  (`formattedStartsOn()`, `excerpt()`). `formattedPrice()` segue o
  mesmo padrão e convive com os métodos de lá.
- M2 (`m2-validacao-maxlength`): também cria uma validação nova em
  `lib/Validations.php` e limita o valor ao que a coluna aguenta (aqui,
  `999999,99` para `DECIMAL(8,2)`). Com os dois, os testes de
  `NumberAndDateValidationsTest` ficam lado a lado.
- M23 (`m23-data-dd-mm-aaaa`): mesma ideia de aceitar o formato
  brasileiro no formulário e converter no model antes de gravar.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m20-preco-em-reais`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m20-preco-em-reais`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m20 \
    s50/fvp/4.2/exemplos/m20-preco-em-reais/projeto
git fetch fvp-m20 fvp/m20-preco-em-reais
git cherry-pick 59fd70a
git remote remove fvp-m20
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m20-preco-em-reais/solucao.bundle \
    fvp/m20-preco-em-reais:fvp-m20
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 59fd70a
# opcional, limpa a ref
git branch -D fvp-m20
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m20-preco-em-reais/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m20-preco-em-reais/solucao.patch
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

- `schema.sql`, `Event.php` (`@property`, `$columns`, `validates()`),
  `FIELDS` do controller, `_form.phtml`, `index.phtml`, `show.phtml`:
  todo campo novo (M1, M4, M17) mexe nas mesmas linhas. Fique com as
  duas colunas/chaves.
- `Event.php` (métodos de apresentação): R3 acrescenta métodos no
  mesmo trecho; mantenha todos.
- `lib/Validations.php`: M2 acrescenta `maxLength` e M18 mexe em
  `notPastDate`; mantenha todos os métodos.
- `populate.php` e os helpers de teste (`params()`, `validParams()`,
  `BaseEventsCest`, `EventsAccessTest`): mantenha as chaves dos dois
  cenários, senão os Eventos ficam inválidos.

## Armadilhas

- `(float)` no controller: `(float) '10,50'` vale `10.0` e o evento
  fica mais barato sem erro nenhum. A conversão tem de ser a troca de
  vírgula por ponto, e só depois de validar.
- Gravar `10,50` direto: o MySQL recusa com "Incorrect decimal value"
  (erro 1366) e a página dá 500.
- Valor acima de `999999,99`: "Out of range value for column 'price'"
  (erro 1264) e 500. Por isso a regex limita a 6 dígitos.
- `FILTER_VALIDATE_FLOAT` aceita `1e3` (mil) e `-1`; `is_numeric`
  também aceita `1e3`. Não use nenhum dos dois para validar.
- `$` sem o modificador `D` aceita `"10\n"`.
- `1.234,50` (com ponto de milhar) é recusado de propósito: o ponto
  também é separador decimal aceito, e `1.234` seria ambíguo.
- O banco devolve `price` como string `"10.50"` (DECIMAL vem como
  texto no PDO). Compare com `assertSame('10.50', ...)` ou
  `assertEquals(10.5, ...)`, e o `0` volta como `"0.00"`: por isso o
  "Gratuito" testa `(float) $preco === 0.0`, não `=== '0'`.
- `DEFAULT 0` no schema não resolve o campo vazio: o `INSERT` manda
  `NULL`. Quem garante o valor é a validação.
- O form tem `novalidate`; o erro vem sempre do servidor.
- Esquecer `db:populate`: `Unknown column 'price'` só no navegador; os
  testes passam porque recriam o schema a cada teste.
