# R11. Anterior/próximo Evento no show

Pedido, como o professor faria na hora: "Nos detalhes do Evento,
coloque links para o Evento anterior e o próximo, na ordem de data.
No primeiro não aparece 'Anterior'; no último não aparece 'Próximo'."

Chance: média-baixa. Tempo estimado: 20-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R11.

Branch no clone: `fvp/r11-anterior-proximo-no-show`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                   |
|-----------|------------------------------------------------------------|
| `7833bae` | `feat: Add previous and next event links to the show page` |

## O que mudou

- `app/Models/Event.php`:
  - `previous(): ?Event` e `next(): ?Event`, públicos, chamam o
    privado `neighbor(string $operator, string $direction)`;
  - SQL no model, com `prepare` e `bindValue`:

```sql
SELECT id, <colunas> FROM events
WHERE starts_on < :starts_on
   OR (starts_on = :same_starts_on AND id < :id)
ORDER BY starts_on DESC, id DESC
LIMIT 1
```

    (`next()` usa `>` e `ASC`); devolve `new static($row)` ou `null`
    se não houver linha ou se o Evento ainda não foi gravado.
  - `use Core\Database\Database;` e `use PDO;`.
- `app/views/admin/events/show.phtml`: acima dos botões, um `<nav>`
  com "← Anterior" (`#previous_event`, `rel="prev"`) e "Próximo →"
  (`#next_event`, `rel="next"`), cada um com
  `route('admin.events.show', ['id' => ...])` e o título do vizinho
  no `title` (escapado). O link some quando o vizinho é `null`.
- Testes:
  - `EventTest` (5): ordem por data e não por id; primeiro sem
    anterior e último sem próximo; Evento único ou novo sem
    vizinhos; três Eventos na mesma data ordenados por id (ida e
    volta); percorrer com `next()` visita todos uma vez, em ordem.
  - `EventsControllerTest` (3): links certos no show do Evento do
    meio (cadastrados fora de ordem); primeiro sem "Anterior" e
    último sem "Próximo"; Evento único sem navegação.
  - `tests/Acceptance/admin/events/NavigateEventsCest.php` (novo):
    do Evento 01 para o 02 com "Próximo" e de volta com "Anterior";
    o Evento 16 não tem "Próximo".

## Por que assim

- SQL no model: a regra "quem vem antes" é do domínio, e a consulta
  com `LIMIT 1` traz só uma linha. Carregar `Event::all()` e procurar
  o índice no controller funcionaria, mas lê a tabela inteira e põe
  lógica no controller.
- Desempate por `id`: só `starts_on < :d` pularia os Eventos do mesmo
  dia; `starts_on <= :d` (sem o id) cairia no próprio Evento ou
  ficaria indo e voltando entre dois. A condição composta e o
  `ORDER BY starts_on, id` nos dois sentidos garantem uma ordem total,
  a mesma da listagem ordenada do N3.
- Dois nomes de parâmetro (`:starts_on` e `:same_starts_on`) para o
  mesmo valor: placeholder repetido só funciona com prepares
  emulados; com nomes distintos funciona sempre.
- `$operator`/`$direction` são interpolados, mas vêm só de
  `previous()`/`next()` (código), nunca da requisição. Os valores vão
  por `bindValue` (`id` com `PDO::PARAM_INT`).
- `new static` com retorno `?Event`: o `phpstan` aceita (static é
  subtipo de Event); um `instanceof Event` ali seria acusado como
  sempre verdadeiro.
- O controller não mudou: a view chama os métodos do model. Se
  preferir o controller "dono" dos dados, calcule `$previous`/`$next`
  no `show` e passe no `compact`; o comportamento é o mesmo.

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

Resultado obtido: Unit 164, Integration 48, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

No navegador (populate): `/admin/events/2` tem "← Anterior" para o
Evento 1 e "Próximo →" para o 3; `/admin/events/1` não tem
"Anterior"; `/admin/events/16` não tem "Próximo".

## Relação com outros cenários

- N3 (`n3-ordenar-por-data`): mesma ordem (`starts_on, id`). Com os
  dois aplicados, "Próximo" leva ao Evento seguinte da listagem.
  Sem o N3, a listagem segue o `id`, e no populate as duas ordens
  coincidem.
- N8: se a listagem ganhar outra ordem ou filtro, a navegação do
  show deve seguir o mesmo critério; acrescente a condição no
  `WHERE` de `neighbor()` (ex.: `AND kind = :kind`) com `bindValue`.
- R5 (`r5-created-updated-at`) e R2 (`r2-nome-do-criador`) mexem no
  mesmo `show.phtml`: conflito de texto simples.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r11-anterior-proximo-no-show`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r11-anterior-proximo-no-show`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r11 \
    s50/fvp/4.2/exemplos/r11-anterior-proximo-no-show/projeto
git fetch fvp-r11 fvp/r11-anterior-proximo-no-show
git cherry-pick 7833bae
git remote remove fvp-r11
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r11-anterior-proximo-no-show/solucao.bundle \
    fvp/r11-anterior-proximo-no-show:fvp-r11
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 7833bae
# opcional, limpa a ref
git branch -D fvp-r11
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am \
    s50/fvp/4.2/exemplos/r11-anterior-proximo-no-show/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r11-anterior-proximo-no-show/solucao.patch
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

- `show.phtml`: outros cenários acrescentam itens na `<dl>` ou
  mexem nos botões do fim. O `<nav>` deste fica logo antes dos
  botões "Editar"/"Voltar"; mantenha os dois lados.
- `Event.php`: métodos novos e `use` no topo (R3/R5 importam
  `DateTime`); mantenha todos os `use`, sem repetir.
- `EventTest`/`EventsControllerTest`: testes novos no mesmo trecho;
  mantenha todos.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Ordenar por `id` em vez de data: "Próximo" leva a um Evento que
  não é o seguinte na agenda (o teste com datas fora de ordem pega).
- Esquecer o desempate: Eventos no mesmo dia são pulados ou a
  navegação entra em laço.
- `ORDER BY` no sentido errado no `previous()`: `ASC` traz o
  primeiro de todos, não o imediatamente anterior. Tem de ser `DESC`.
- Montar o link na view sem checar `null`: `$previous->id` em `null`
  dá warning, que o `ErrorsHandler` vira página 500 no primeiro
  Evento.
- Interpolar valores no SQL (`WHERE id < {$this->id}`): use
  `bindValue`, mesmo sendo número.
- `Event::all()` + `array_search` no controller: funciona, mas lê
  tudo e tira a regra do model.
