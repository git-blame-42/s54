# P19. Remoção em lote (checkbox por linha + "Remover selecionados")

Pedido, como o professor faria na hora: "Na listagem de Eventos,
ponha um checkbox em cada linha e um botão 'Remover selecionados'.
Eventos com inscrições continuam protegidos, como na remoção de um
só."

Chance: baixa. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P19.

Branch no clone: `fvp/p19-remocao-em-lote`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                            |
|-----------|-------------------------------------|
| `c22f59a` | `feat: Add bulk removal of events`  |

## O que mudou

- `config/routes.php`: `DELETE /admin/events` (a coleção), nome
  `admin.events.destroy_many`, ação `destroyMany`, no grupo `admin`.
  Do navegador chega como `POST /admin/events` com `_method=DELETE`;
  o `POST` sem `_method` continua sendo o `create`.
- `app/Models/Event.php`: `destroyMany(array $ids): array` (estático).
  Para cada id (sem repetir): `findById`; se não existe, vai para
  `missing`; senão chama `safeDestroy()` (a regra P03 do código-base:
  Evento com inscrições não sai) e anota o título em `removed` ou
  `blocked`. PHPDoc `@return array{removed: array<int, string>,
  blocked: array<int, string>, missing: array<int, int>}`.
- `Admin\EventsController::destroyMany`: valida `ids[]` no método
  privado `selectedIds()`: precisa ser array não vazio e cada item
  string com `ctype_digit` e diferente de `"0"`. Um item inválido
  invalida a seleção inteira: flash `danger` "Selecione ao menos um
  evento válido para remover!" e nada é removido. Com ids válidos,
  chama o model e monta **um flash por tipo**:
  - `success`: "N evento(s) removido(s) com sucesso!";
  - `danger`: "Não é possível remover eventos com inscrições:
    Evento 01, Evento 03; 1 evento(s) não encontrado(s)!" (só as
    partes que aconteceram).
  Sempre redireciona para `/admin/events`.
- `app/views/admin/events/index.phtml`: `<form id="bulk_destroy_form"
  method="post">` com `_method=DELETE` e o botão "Remover
  selecionados", **acima** da tabela. Coluna nova com
  `<input type="checkbox" name="ids[]" value="<id>"
  form="bulk_destroy_form">` em cada linha (`id="event_select_<id>"`,
  `aria-label` com o título escapado).
- Testes:
  - `EventTest` (2): remove os sem inscrições e deixa os outros;
    lista misturada (livre, com inscrição, inexistente, repetido) cai
    em `removed`, `blocked` e `missing`.
  - `EventsControllerTest` (5 + 8 casos): form e checkboxes na
    listagem; remoção de dois; flash `success` e `danger` juntos;
    `invalidIdsProvider` (sem ids, lista vazia, texto, `1abc`, `-1`,
    `0`, `1.5`, lista aninhada) não remove nada; um id inválido
    (`'2 OR 1=1'`) derruba a seleção inteira.
  - `EventsAccessTest`: rota nova em `eventRoutes()` e em
    `methodOverrideRoutes()`; o Participante que manda
    `_method=DELETE` com `ids[]` não remove nada.
  - Cest novo `DestroyManyEventsCest` (3): marca Evento 02 e 04 e
    remove; marca 01, 02 e 03 (01 e 03 têm inscrições) e vê os dois
    flashes; clica sem marcar nada.

## Por que assim

- Rota RESTful: `DELETE` na coleção remove vários, `DELETE
  /admin/events/{id}` remove um. Os ids vão no corpo, não na URL.
- A regra de "não remover com inscrições" fica num lugar só: o laço
  chama `safeDestroy()`, que já trata a corrida com a FK (`try` /
  `catch PDOException`). Nada de `DELETE ... WHERE id IN (...)`, que
  pularia a regra (e a FK `RESTRICT` derrubaria a requisição com 500
  no primeiro Evento com inscrição).
- Validação no limite HTTP: `is_array` e `ctype_digit` antes de tocar
  no banco. Rejeitar tudo quando um item é inválido é mais simples de
  explicar do que remover "os que deu"; seleção legítima pela tela
  nunca tem item inválido.
- Um flash por tipo: o `FlashMessage` guarda `$_SESSION['flash']
  [$type] = $value`, então dois `danger` seguidos fariam o segundo
  apagar o primeiro. Por isso os problemas são juntados numa frase.
- `form="bulk_destroy_form"`: cada linha já tem um `<form>` de
  "Remover" (DELETE de um). Envolver a tabela num `<form>` criaria
  form dentro de form, que o HTML não permite (o navegador fecha o de
  fora no primeiro de dentro). O atributo `form` liga o checkbox a um
  formulário que está em outro lugar da página.
- Nome da rota com `_` (`destroy_many`): o Router aceita qualquer
  nome; a restrição de `{[a-z,_]+}` é só para parâmetro de URI.

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

Resultado obtido: Unit 161 testes, Integration 60, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `EventTest`:
  - `test_destroy_many_should_remove_events_without_enrollments`
  - `test_destroy_many_should_keep_events_with_enrollments_and_...`
    (`..._report_missing_ids`)
- `EventsControllerTest`:
  - `test_index_shows_bulk_destroy_form_and_checkboxes`
  - `test_destroy_many_removes_the_selected_events`
  - `test_destroy_many_keeps_events_with_enrollments_with_one_...`
    (`..._flash_per_type`)
  - `test_destroy_many_with_invalid_ids_removes_nothing` (8 casos)
  - `test_destroy_many_rejects_the_whole_selection_if_one_id_is_...`
    (`..._invalid`)
- `EventsAccessTest`: `admin.events.destroy_many` em `eventRoutes()`
  e `methodOverrideRoutes()`; `ids[]` no teste do Participante.
- `DestroyManyEventsCest`:
  - `removeSelectedEvents`
  - `eventsWithEnrollmentsAreKept`
  - `removeWithoutSelection`

No navegador (populate): marque o primeiro Evento (lotado) e um sem
inscrições, "Remover selecionados": aparecem o flash verde (1
removido) e o vermelho com o título do lotado. Por HTTP:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -i -b /tmp/c \
    -d '_method=DELETE&ids[]=1&ids[]=8' http://localhost/admin/events
# 302 Location: /admin/events (refaça o banco: ./run db:populate)
```

## Relação com outros cenários

- N10 (`n10-pagina-id-invalido`): a mesma ideia de não converter com
  `(int)` às cegas; aqui `ctype_digit` recusa `1abc`, que `(int)`
  transformaria em 1.
- L3 e L4 da 4.1 (citados no levantamento de lacunas da 4.2; sem
  exemplo pronto no s50): remoção e validação de parâmetros no
  `UsersController`, no mesmo molde.
- P11 (`p11-soft-delete-arquivar`): com arquivamento, o lote pode
  arquivar em vez de remover (troque `safeDestroy()` por `archive()`
  no laço).
- Q1 (`q1-so-dono-edita-remove`): com regra de dono, o laço também
  precisa barrar Eventos de outro Administrador (mais um grupo no
  resultado, ex.: `forbidden`).

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/p19-remocao-em-lote`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p19-remocao-em-lote
cd /caminho/do/seu/repositorio
git remote add fvp-p19 s50/fvp/4.2/exemplos/p19-remocao-em-lote/projeto
git fetch fvp-p19 fvp/p19-remocao-em-lote
git cherry-pick c22f59a
git remote remove fvp-p19
```

### 2. A partir do `solucao.bundle`

```
D=s50/fvp/4.2/exemplos/p19-remocao-em-lote
git fetch $D/solucao.bundle fvp/p19-remocao-em-lote:fvp-p19
git cherry-pick c22f59a
# opcional, limpa a ref
git branch -D fvp-p19
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/p19-remocao-em-lote
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

- `index.phtml`: a tabela é mexida por N1, N6, N8, N13 (colunas,
  cabeçalhos, formulário de filtro). Mantenha a coluna do checkbox
  em `<thead>` e `<tbody>` e o `bulk_destroy_form` fora da tabela; um
  formulário de filtro GET é outro `<form>`, separado.
- `config/routes.php`: outras rotas na coleção `/admin/events`; fique
  com todas (o método HTTP diferencia `POST` de `DELETE`).
- `EventsAccessTest`: uma linha por rota nos providers; corrija o
  comentário com o total.
- `EventsControllerTest`: o `use ...\DataProvider;` pode já existir;
  deixe um só.

## Armadilhas

- `<form>` em volta da tabela: inválido (form aninhado com os
  "Remover" de cada linha). Os checkboxes ficam fora do form "de
  verdade"; use `form="bulk_destroy_form"`.
- `DELETE /admin/events` sem `_method`: o navegador manda `POST` e
  cai no `create` (o form precisa do campo oculto).
- `(int) '1abc'` é 1: removeria o Evento 1 sem ninguém ter pedido.
  Use `ctype_digit`. `ctype_digit` com array ou `int` dá `false`/
  deprecation; por isso o `is_string` antes.
- Dois `FlashMessage::danger()` seguidos: só o último aparece.
- `WHERE id IN (...)` com os ids interpolados: injeção e pula o
  `safeDestroy()`. Se o professor pedir uma consulta só, use
  placeholders gerados (`?, ?, ?`) e ainda assim confira inscrições
  antes.
- Parâmetro de rota só aceita `{[a-z,_]+}` (`Route.php`): `{eventId}`
  ou `{ids}` com maiúscula ou dígito não vira parâmetro. Aqui não há
  parâmetro na URI, os ids vão no corpo.
- `$_SESSION['flash']` sobrevive entre testes: o `setUp` do
  `EventsControllerTest` zera; os testes conferem
  `assertArrayNotHasKey` para garantir que o outro tipo não apareceu.
