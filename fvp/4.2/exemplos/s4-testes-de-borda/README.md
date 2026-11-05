# S4. Testes de borda pedidos pelo professor

Pedido, como o professor faria na hora: "Escreva testes para
quantidades negativas, valores não numéricos, duplicidade, edição não
autorizada e atualização sem alteração."

Chance: média-alta. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, S4.

Branch no clone: `fvp/s4-testes-de-borda`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz). Só testes:
nenhum arquivo de `app/`, `core/`, `lib/` ou do schema mudou.

| Commit    | Mensagem                                |
|-----------|-----------------------------------------|
| `7328321` | `test: Add edge case tests for events`  |

## O que mudou

- `tests/Unit/Models/Events/EventTest.php`, seção "testes de borda":
  - `invalidCapacityProvider` (10 casos): `-1`, `'-1'`, `'0'` dão "deve
    ser maior ou igual a 1!"; `'abc'`, `'1.5'`, `'1,5'`, `'1e1'`,
    `'010'` dão "deve ser um número inteiro!"; `''` e `null` dão "não
    pode ser vazio!". Em todos, só `capacity` tem erro e nada é salvo.
  - `' 10 '` e `'+5'` são aceitos (comportamento atual, documentado).
  - Capacidade `'3000000000'` e título com 256 caracteres passam na
    validação e o `save()` lança `PDOException` (ver Armadilhas).
  - Título só com espaços é aceito (comportamento atual).
  - `invalidStartsOnProvider` (7 casos): dia inexistente, 29/02 fora
    de ano bissexto, `31/12/2030`, data com hora, texto, ontem, ano
    passado; mensagem exata e só `starts_on` com erro.
  - Duplicidade: dois Eventos iguais são gravados (na base não há
    regra de unicidade; o M6 cria).
- `tests/Integration/Controllers/Admin/EventsControllerTest.php`:
  - create sem a chave `title`, com `capacity` `-1` e `abc`, com array
    em campo de texto e com `event` como string: form de volta, sem
    500, nada gravado.
  - `id` forjado no create não sobrescreve outro Evento; `user_id` e
    `id` forjados no update são ignorados.
  - update sem alteração: `Location` do show, flash e campos iguais.
  - update só com `title`: recusado, registro intacto (o `fill` copia
    todos os campos; o que falta fica nulo).
  - edit, update e destroy com id inexistente: `Location:
    /admin/events`, "Evento não encontrado!", nada muda.
- `tests/Integration/Access/EventsAccessTest.php`:
  - Participante com `_method` PUT/DELETE vai para `/dashboard`.
  - Participante em `/admin/events/999999/edit` vai para
    `/dashboard` (o middleware barra antes de procurar o Evento).
  - PUT e DELETE válidos de Participante não mudam nem removem o
    Evento.

O acesso de Participante e de visitante a cada uma das 8 rotas
`admin.events.*` já estava coberto por `eventRoutes()` na base; os
testes novos completam o que faltava (`_method` com sessão de
Participante e o Evento inexistente).

## Por que assim

- `new Event($this->params([...]))` + `isValid()`: o `createEvent()`
  do `EventTest` tem `assertTrue($event->save())` dentro e não serve
  para dado inválido.
- Data providers com a mensagem esperada: um caso por linha, e o nome
  do caso aparece no `--testdox` quando falha.
- `assertCount(1, $this->errorsOf($event))` (helper privado do
  `EventTest`, já existia): prova que só o campo testado tem erro.
- Onde a base aceita um valor estranho ou estoura no banco, o teste
  documenta o comportamento atual e o docblock diz "Comportamento
  atual". Corrigir é outro cenário (ver Armadilhas); o pedido é de
  testes.
- Ids forjados e acesso: conferidos no banco depois da requisição, não
  só pelo `Location`.

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

Resultado obtido: Unit 181 testes, Integration 61, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Para ver que os testes testam algo: troque `1` por `0` no
`integerMin('capacity', ...)` de `Event::validates()` e rode
`./run test tests/Unit/Models/Events/EventTest.php`; os casos `'0'`
falham. Desfaça com `git checkout app/`.

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`): limita o título a 100 e elimina o
  `PDOException` do título com 256 caracteres. Com o M2 aplicado,
  `test_title_above_the_varchar_limit_...` precisa virar "inválido".
- M6 (`m6-unicidade-titulo-data`): com ele,
  `test_duplicate_event_is_accepted` falha de propósito; troque pelo
  teste de duplicidade do M6.
- M5 (`m5-validacao-condicional-modalidade`): o PUT de Participante
  em `EventsAccessTest` manda `online` sem `link`. Continua passando
  (o middleware barra antes), mas acrescente o link para o payload
  seguir válido.
- `integerMax` (receita no M2) resolveria o `PDOException` da
  capacidade enorme.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/s4-testes-de-borda`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/s4-testes-de-borda
cd /caminho/do/seu/repositorio
git remote add fvp-s4 s50/fvp/4.2/exemplos/s4-testes-de-borda/projeto
git fetch fvp-s4 fvp/s4-testes-de-borda
git cherry-pick 7328321
git remote remove fvp-s4
```

### 2. A partir do `solucao.bundle`

```
git fetch s50/fvp/4.2/exemplos/s4-testes-de-borda/solucao.bundle \
    fvp/s4-testes-de-borda:fvp-s4
git cherry-pick 7328321
# opcional, limpa a ref
git branch -D fvp-s4
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/s4-testes-de-borda
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

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

- `EventTest.php`, `EventsControllerTest.php`, `EventsAccessTest.php`:
  testes novos de outros cenários no mesmo ponto (antes de
  `audience (SET)` e antes de `validParams()`); fique com os dois.
- `use PHPUnit\Framework\Attributes\DataProvider;` no
  `EventsControllerTest`: outro cenário pode ter acrescentado o mesmo
  `use`; deixe um só.

Depois de aplicar sobre M2, M5 ou M6, ajuste os testes citados em
"Relação com outros cenários".

## Armadilhas

- Bugs reais da base revelados (não corrigidos aqui, por pedido):
  - Capacidade acima de 2147483647 (`'3000000000'`) passa no
    `integerMin` e o MySQL estrito recusa no `INSERT`: `save()` lança
    `PDOException`, e pelo form a página dá 500. Correção: um
    `integerMax` (receita no M2).
  - Título com mais de 255 caracteres: mesmo efeito ("Data too
    long"). Correção: `maxLength` (M2), com limite ≤ 255.
- O catálogo diz que `' 10 '` com espaços é rejeitado; não é: o
  `filter_var(..., FILTER_VALIDATE_INT)` ignora espaços nas pontas e
  aceita `'+5'`. Já `'010'` (zero à esquerda) é rejeitado.
- `notEmpty` não faz `trim`: título `'   '` é válido.
- `errors()` guarda uma mensagem por campo; a última vence. Por isso
  cada caso confere a mensagem exata e o total de campos com erro.
- `expectException` antes da linha que lança: o que vem depois do
  `save()` não roda. Ponha as asserções de validação antes.
- `$_SESSION['flash']` sobrevive entre testes; o `setUp` do
  `EventsControllerTest` já zera, não remova.
- No teste com `match`, os três verbos passam o mesmo `$params`; o
  `edit` ignora o `event`, e tudo bem.
