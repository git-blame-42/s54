# P23. Código gravado no registro, exigido para remover

Pedido, como o professor faria na hora: "Ao cadastrar, o backend gera
um código de 4 dígitos (1000 a 9999) que fica gravado no Evento. O
admin vê o código no detalhe e só remove o Evento se digitar esse
código. Código errado ou em branco: avise e não remova."

Chance: alta. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P23. Variante do catálogo (não implementada
aqui, ver "Por que assim"): exigir o código para salvar uma edição.

Branch no clone: `fvp/p23-codigo-do-registro-para-remover`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `2af97ce` | `feat: Require the Event confirmation code to remove it` |

## O que mudou

- `database/schema.sql`: coluna `confirmation_code SMALLINT UNSIGNED
  NOT NULL` em `events`, depois de `capacity`. Sem `UNIQUE`: o código
  é conferido junto com o `id` do Evento.
- `app/Models/Event.php`:
  - `@property int|string|null $confirmation_code`,
    `'confirmation_code'` em `$columns` e as constantes
    `CODE_MIN = 1000` / `CODE_MAX = 9999`;
  - construtor sobrescrito (molde do `User`): depois do
    `parent::__construct`, se o código está vazio (`null` ou `''`),
    `random_int(CODE_MIN, CODE_MAX)`;
  - `confirmsCode(mixed $input): bool`: `false` se não for string ou
    não for só dígitos (`ctype_digit`); senão
    `hash_equals((string) $this->confirmation_code, $input)`. Sem
    `trim`: `' 4321'` e `'04321'` não valem.
- `app/Controllers/Admin/EventsController.php` (`destroy`): depois do
  `findEvent` e **antes** do `safeDestroy()`, código que não confere →
  flash `danger` "Código de confirmação incorreto!", redirect para o
  `show` do Evento e `return`. Código certo → fluxo de antes. `FIELDS`
  não ganhou o campo.
- `show.phtml`: linha "Código de confirmação" com
  `<span id="confirmation_code_value">` e, no fim, o form `POST` +
  `_method=DELETE` com o campo `confirmation_code` (vazio) e o botão
  "Confirmar remoção".
- `index.phtml`: "Remover" deixou de ser form `DELETE` (que sempre
  falharia sem o campo) e virou link para o `show`.
- Populate sem mudança: os 16 Eventos ganham código no construtor.

## Por que assim

- Construtor, e não o `create` do controller: populate, `EventsAccessTest`
  e `BaseEventsCest` fazem `new Event(...)` + `save()` sem passar pelo
  controller; o construtor cobre todos esses caminhos.
- "Só se vazio" é obrigatório: o construtor roda também a cada leitura
  do banco (`findById`, `where`, `Paginator` fazem `new` com a linha).
  Gerar sempre trocaria o código a cada carga, e o `show` mostraria um
  código diferente do conferido no `destroy`.
- Fora de `FIELDS`: o `fill` não copia o campo, então o formulário não
  escolhe o código (nem no `create` nem no `update`) e o `update` não o
  zera.
- `confirmsCode(mixed)`: `getParam` pode devolver `null` ou array
  (`confirmation_code[]=1`). Com o tipo `string` no parâmetro, isso
  seria `TypeError` (500). `is_string` + `ctype_digit` recusam tudo que
  não for só dígitos antes da comparação; `hash_equals` compara strings
  sem as conversões do `==`.
- Sem `(int)`: `(int) ' 4321'` e `(int) '04321'` dão `4321`, e
  `(int) '12a'` dá `12`.
- Conferir antes do `safeDestroy()`: com código errado não se revela
  se o Evento tem Inscrições; com código certo a mensagem da regra P03
  do projeto continua igual.
- Erro volta ao `show`: é onde estão o código e o campo para tentar de
  novo.
- O código aparece na própria tela: não é segredo do admin, é prova de
  leitura antes de uma ação irreversível, e barra `DELETE` forjado sem
  o código.
- Na view, `htmlspecialchars((string) ...)` em vez do `(int)` que o
  catálogo sugere: mesma saída, e segue a regra de escapar toda saída
  sem conversão silenciosa.
- Variante (editar): o mesmo `input` dentro do `if ($isUpdate)` do
  `_form.phtml`; no `update`, depois do `fill`, código errado →
  `FlashMessage::danger(...)`, `render` do `edit` e `return`. Código
  errado com dados inválidos mostra só o erro do código (proposital).

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

Resultado obtido: Unit 164, Integration 55, Acceptance 24 (base: 159 /
45 / 23). PHPCS e PHPStan sem erros. O populate roda sem erro.

Testes novos:

- `EventTest`: código de um Evento novo na faixa (200 sorteios);
  código passado ao construtor é mantido;
  `test_confirmation_code_should_be_kept` (não muda ao salvar, nem
  lido por `where` e `paginate`);
  `test_confirms_code_should_accept_only_the_code` (`'1234'`, `'12a'`,
  `' 4321'`, `'4321 '`, `'04321'`, `''`, `'-4321'`, `'4321.0'`,
  `'4e3'`, `null`, array e int recusados); conferência com o valor
  lido do banco.
- `EventsControllerTest`: link "Remover" da listagem; código e form no
  `show` (`id="confirmation_code_value">4321<`); `create` com `'42'`
  grava outro valor; `update` mantém o código; `destroy` certo
  (remove), certo com Inscrições (mensagem de sempre), sem código,
  errado, array, errado com Inscrições (não revela) e código de outro
  Evento. Os dois testes antigos de `destroy` viraram o caso "certo".
- `EventsAccessTest::test_admin_should_remove_only_with_the_confirmation_code`:
  por HTTP, `'0000'` volta ao `show`; lê o código do HTML e remove.
- `DeleteEventCest`: `clickRemove` vai ao `show`, lê
  `#confirmation_code_value`, digita em `#confirmation_code` e clica em
  "Confirmar remoção"; cenário novo `deleteWithWrongCodeGoesBackToShow`.

Na tela (dentro da rede do compose):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/16 | grep confirmation_code
```

## Relação com outros cenários

- P16 (remover com código de confirmação): código na sessão, gerado ao
  abrir a tela de remoção, de uso único. Aqui o código nasce no create,
  fica no registro e não muda. Não aplique os dois: ambos mudam o
  `destroy`, o botão "Remover" e o `DeleteEventCest`.
- P1 (código de check-in): mesma geração com `random_int`, mas público
  e único, só exibido. Com P1 na base, não reuse `checkin_code` (ele
  aparece na listagem).
- P24 (validade e tentativas): empilha sobre este.
- Q1/Q2: outro *early return* no `destroy`; a ordem fica dono →
  editável → código.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p23-codigo-do-registro-para-remover`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p23-codigo-do-registro-para-remover`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p23 \
    s50/fvp/4.2/exemplos/p23-codigo-do-registro-para-remover/projeto
git fetch fvp-p23 fvp/p23-codigo-do-registro-para-remover
git cherry-pick 2af97ce
git remote remove fvp-p23
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p23-codigo-do-registro-para-remover/solucao.bundle \
    fvp/p23-codigo-do-registro-para-remover:fvp-p23
git cherry-pick 2af97ce
# opcional, limpa a ref
git branch -D fvp-p23
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am \
    s50/fvp/4.2/exemplos/p23-codigo-do-registro-para-remover/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p23-codigo-do-registro-para-remover/solucao.patch
```

Depois de aplicar: `./run db:populate` (coluna nova) e os testes.

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

- `database/schema.sql` e `Event::$columns`: todo cenário de coluna
  nova (M1, P1...) mexe nas mesmas linhas. Fique com todas.
- Construtor do `Event`: outro cenário que defina padrão no construtor
  (ou P1, que gera no `save()`). Junte tudo depois do
  `parent::__construct`.
- `index.phtml` (célula de ações) e `show.phtml` (botões do fim): R4,
  Q1, P5, P6 mexem nas mesmas linhas.
- `EventsController::destroy`: Q1/Q2 põem *early return* no mesmo
  ponto; a conferência do código fica por último, antes do
  `safeDestroy()`.
- `EventsControllerTest` (testes de `destroy`) e
  `DeleteEventCest::clickRemove`: envie o código nos testes dos
  outros cenários também.

## Armadilhas

- Sem o código em `$columns`, o `INSERT` não o envia e a coluna `NOT
  NULL` sem padrão lança `PDOException` (o `save()` grava só e todas
  as colunas de `$columns`).
- Gerar no `create` do controller: populate, testes e Cests gravam
  `NULL`. Gerar no `save()` depois do `isValid()` com validação de
  faixa: o populate lança `RuntimeException`.
- Gerar sempre no construtor (sem o "só se vazio"): o código muda a
  cada leitura. `test_confirmation_code_should_be_kept` pega.
- Pôr o código em `FIELDS`: o `update` sem o campo grava `NULL` e o
  admin escolhe o código.
- `confirmsCode(string $input)`: `null` ou array viram `TypeError`.
- `hash_equals` só aceita strings: converta o valor do banco com
  `(string)`.
- O `Request` dos testes de controller é um só por teste e `addParams`
  faz `array_merge`: um `destroy` "sem código" depois de um com código
  ainda envia o código. Um caso por teste.
- `'42'` no teste de create: valor fora da faixa nunca é gerado; com
  `'1111'` o teste falharia 1 vez em 9000.
- Pôr o código num `<input type="hidden">` ou no `value` do campo: o
  form "confirma sozinho". O campo começa vazio.
- Esquecer o `return` depois do `redirectTo`: no teste de controller
  (onde não há `exit`) a ação seguiria para o `safeDestroy()`.
