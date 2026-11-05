# M1. Campo novo em `events` com validação (`workload_hours`)

Pedido, como o professor faria na hora: "Acrescente a carga horária ao
Evento (`workload_hours`, inteiro maior ou igual a 1, obrigatório). Ela
aparece no formulário, na listagem e no detalhe; vazio ou 0 dão erro
embaixo do campo."

Chance: alta. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M1.

Branch no clone: `fvp/m1-campo-novo-workload-hours`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                |
|-----------|-----------------------------------------|
| `5f8742b` | `feat: Add workload hours to events`    |

## O que mudou

- `database/schema.sql`: `workload_hours INT NOT NULL` depois de
  `capacity` em `events`.
- `app/Models/Event.php`: `@property int|string $workload_hours`, a
  chave em `$columns` e, em `validates()`, o par
  `notEmpty('workload_hours') && integerMin('workload_hours', 1)`,
  igual ao de `capacity`.
- `app/Controllers/Admin/EventsController.php`: `'workload_hours'` em
  `FIELDS` (só o que está ali é copiado do formulário).
- `app/views/admin/events/_form.phtml`: bloco "Carga horária (horas)",
  cópia do de `capacity` (`type=number`, `min=1`, `$invalid()` e
  `$feedback()`).
- `index.phtml`: coluna "Carga horária" (`8 h`); `show.phtml`: par
  `dt`/`dd` "Carga horária".
- `database/Populate/populate.php`: `'workload_hours' => 4` no
  `new Event` (igual para os 16 Eventos).
- Testes ajustados com a chave nova: `params()` do `EventTest`,
  `EnrollmentTest` (Evento do `setUp`), `validParams()` do
  `EventsControllerTest`, `EventsAccessTest` (`setUp` e o POST do
  Participante), `BaseEventsCest` (`seedEvents`, `validEventData` e
  `fillEventForm`, que agora preenche `workload_hours`).
- Testes novos ou ampliados:
  - `EventTest`: `workload_hours` na lista de obrigatórios (vazio dá
    "não pode ser vazio!"), `test_workload_hours_should_not_be_empty`,
    `test_workload_hours_should_be_at_least_one` (0, -2, texto, 1) e
    `test_workload_hours_should_be_saved`.
  - `EventsControllerTest`:
    `test_create_with_invalid_workload_hours_shows_error_below_the_field`
    (vazio e `0`: `is-invalid` e mensagem logo abaixo do campo, título
    e local mantidos, outros campos sem erro) e
    `test_create_saves_workload_hours_and_show_displays_it`.
  - `CreateWithInvalidDataCest`: formulário vazio marca
    `workload_hours`; carga `0` mostra "deve ser maior ou igual a 1!".
  - `CreateSuccessfullyCest`: vê `8 h` no `show` e confere o valor
    gravado.

## Por que assim

- Validação só no model, com `lib/Validations.php` (`notEmpty` e
  `integerMin`, que já rejeita texto e decimal). O controller continua
  fino: a string vai ao model, sem `(int)` (conversão silenciosa).
- `INT NOT NULL` sem `DEFAULT`: `Model::save()` insere todas as colunas
  de `$columns`, com `NULL` quando falta valor, então um `DEFAULT` não
  seria usado. Quem garante o valor é a validação.
- O populate usa o mesmo valor para todos, sem mexer nas linhas
  posicionais do array de Eventos.
- Saída sempre com `(int)` ou `htmlspecialchars` nas views.

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

Resultado obtido: Unit 163 testes, Integration 47, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

No navegador (admin@example.com / 123456): `/admin/events` tem a
coluna "Carga horária" com `4 h`; em "Novo Evento", enviar a carga
vazia ou `0` mostra o erro embaixo do campo e mantém os outros valores.

## Relação com outros cenários

- M4 (`m4-segunda-data-ends-on`): mesmo roteiro de campo novo (schema,
  `$columns`, `FIELDS`, form, views, populate, testes) para uma data.
  Os dois mexem nas mesmas linhas; se aplicar os dois, fique com as
  duas chaves em cada lista.
- M3 (`m3-enum-novo-seminar`): também muda `schema.sql` e pede
  `db:populate`.
- T1 (`t1-populate-campo-eventos-admin`): o populate precisa da chave
  `workload_hours` nos Eventos novos se este cenário estiver aplicado.
- P11 (`p11-soft-delete-arquivar`): outra coluna em `events`
  (`deleted_at`), conflito só de vizinhança no schema e no model.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m1-campo-novo-workload-hours`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m1-campo-novo-workload-hours`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m1 \
    s50/fvp/4.2/exemplos/m1-campo-novo-workload-hours/projeto
git fetch fvp-m1 fvp/m1-campo-novo-workload-hours
git cherry-pick 5f8742b
git remote remove fvp-m1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m1-campo-novo-workload-hours/solucao.bundle \
    fvp/m1-campo-novo-workload-hours:fvp-m1
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 5f8742b
# opcional, limpa a ref
git branch -D fvp-m1
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m1-campo-novo-workload-hours/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m1-campo-novo-workload-hours/solucao.patch
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
  todo campo novo (M4, P1, P5) mexe nas mesmas linhas. Fique com as
  duas colunas/chaves.
- `populate.php` e os helpers de teste (`params()`, `validParams()`,
  `BaseEventsCest`, `EventsAccessTest`): mantenha as chaves dos dois
  cenários, senão os Eventos ficam inválidos.

## Armadilhas

- Esquecer `$columns`: `Property workload_hours not found` ao atribuir.
- Esquecer `FIELDS`: o form envia, o controller ignora e a validação
  acusa "não pode ser vazio!".
- Esquecer `db:populate`: `Unknown column` só no navegador; os testes
  passam porque recriam o schema a cada teste.
- `DEFAULT 1` no schema não resolve: o `INSERT` manda `NULL`.
- `seedEvents` do `BaseEventsCest` não confere o `save()`: sem a chave
  nova, nenhum Evento é gravado e os Cests falham sem mensagem clara.
- O form tem `novalidate`: o `min="1"` do HTML não barra nada; o erro
  vem do servidor.
