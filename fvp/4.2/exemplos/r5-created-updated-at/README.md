# R5. Mostrar `created_at`/`updated_at` no show e na listagem

Pedido, como o professor faria na hora: "Mostre nos detalhes e na
listagem quando cada Evento foi cadastrado e quando foi alterado pela
última vez, com data e hora."

Chance: média. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R5 (armadilha: colunas fora de `$columns`;
SQL próprio no model ou construtor).

Branch no clone: `fvp/r5-created-updated-at`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                     |
|-----------|----------------------------------------------|
| `85565db` | `feat: Show event created_at and updated_at` |

## O que mudou

- Schema sem mudança: `events` já tem
  `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` e
  `updated_at ... DEFAULT CURRENT_TIMESTAMP ON UPDATE
  CURRENT_TIMESTAMP`.
- `app/Models/Event.php`:
  - `created_at` e `updated_at` entram em `$columns` (e no PHPDoc
    `@property string|null`);
  - `save()` sobrescrito: na criação define `created_at`; em toda
    gravação define `updated_at`; os dois com
    `date('Y-m-d H:i:s')`. Se a validação falhar, os valores antigos
    voltam (nada foi gravado);
  - `update(array $data)` sobrescrito: acrescenta `updated_at` (hora
    do PHP) quando não vem nos dados;
  - `formattedCreatedAt()` e `formattedUpdatedAt()`: `dd/mm/aaaa
    hh:mm`, vazio se o Evento ainda não foi gravado.
- `app/views/admin/events/show.phtml`: "Criado em" e "Atualizado em"
  depois de "Vagas".
- `app/views/admin/events/index.phtml`: colunas "Criado em" e
  "Atualizado em" entre "Vagas" e "Ações".
- Testes:
  - `EventTest`: criação define as duas datas (iguais, entre dois
    instantes medidos); `created_at` passado no construtor é
    ignorado; `save()` numa edição muda `updated_at` e mantém
    `created_at`; `save()` inválido não mexe nas datas; `update()`
    grava a hora do PHP; formato `dd/mm/aaaa hh:mm`.
  - `EventsControllerTest`: a listagem tem as duas colunas com as
    datas na linha do Evento; o show mostra as duas datas; o `update`
    pelo controller mantém `created_at` (mesmo com `created_at`
    enviado no formulário) e muda `updated_at`.
  - `tests/Acceptance/admin/events/TimestampsCest.php` (novo): show
    e listagem com as datas, edição pelo formulário, "Criado em"
    fica e "Atualizado em" muda.

## Por que assim

- Armadilha principal: `Model::save()` grava **todas** as colunas de
  `$columns`. Pôr `created_at`/`updated_at` em `$columns` sem
  preenchê-las manda `NULL` explícito no `INSERT` (o `DEFAULT` só vale
  quando a coluna não é citada) e o `NOT NULL` derruba a gravação. No
  `UPDATE`, o `save()` reescreveria `created_at` com o valor lido.
  Por isso o model preenche as duas antes de chamar `parent::save()`.
- Hora do PHP, não do MySQL: o PHP usa `America/Sao_Paulo`
  (`Dockerfile`) e o contêiner `db` está em UTC. Deixar o
  `DEFAULT`/`ON UPDATE CURRENT_TIMESTAMP` agir gravaria 3 horas
  adiantado (conferido: `SELECT NOW()` no `db` dá 19:39 quando o PHP
  marca 16:39). Por isso o `update()` também preenche `updated_at`.
- Escolhida (colunas em `$columns`, preenchidas no model): `findById`,
  `all`, `where` e o `Paginator` já trazem as datas sem consulta
  extra; a regra fica num lugar só (o model); o controller não muda e
  o formulário não consegue alterar as datas (o `fill()` só copia
  `FIELDS`).
- Alternativa (documentada, não aplicada): deixar as colunas fora de
  `$columns` (o banco preenche com `DEFAULT` e `ON UPDATE`) e lê-las
  num método do model com consulta própria:

```php
/** @return array{created_at: string, updated_at: string}|null */
public function timestamps(): ?array
{
    $stmt = Database::getDatabaseConn()->prepare(
        'SELECT created_at, updated_at FROM events WHERE id = :id'
    );
    $stmt->bindValue(':id', $this->id);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}
```

  É mais curta, mas faz uma consulta a mais por Evento (N+1 na
  listagem, que agora mostra as datas), grava a hora do MySQL (UTC
  aqui) e o `ON UPDATE` não muda `updated_at` quando o `save()`
  regrava os mesmos valores.
- Formatação no model (`formattedCreatedAt()`), igual ao R3:
  nenhuma view faz `date(...)` sozinha.
- `update()` com `updated_at` explícito é respeitado: é assim que os
  testes "recuam" as datas, sem `sleep()` (o `TIMESTAMP` guarda
  segundos e o teste seria lento e instável).

## Como verificar

```
./run up -d db php php_test web
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 165, Integration 48, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. `./run db:populate` sem
erro (o populate usa `save()`, então os Eventos dele recebem a hora
do PHP). Schema não mudou.

No navegador: `/admin/events` e `/admin/events/1` mostram "Criado
em" e "Atualizado em" com a hora local do populate; edite o Evento e
só "Atualizado em" muda.

## Relação com outros cenários

- R3 (`r3-helper-formatacao`): mesmo padrão (métodos de apresentação
  no model). Se os dois forem aplicados, os métodos convivem; o R3 já
  importa `DateTime`, então mantenha um só `use DateTime;`.
- N3 (`n3-ordenar-por-data`): com as datas disponíveis, a listagem
  poderia ser ordenada por `created_at DESC, id DESC` ("mais recentes
  primeiro") com o mesmo `orderBy:` do N3, sem SQL novo.
- M1 (campo novo): uma coluna nova vai em `$columns` antes de
  `created_at`/`updated_at`; o `save()` deste cenário não muda.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r5-created-updated-at`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r5-created-updated-at`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r5 \
    s50/fvp/4.2/exemplos/r5-created-updated-at/projeto
git fetch fvp-r5 fvp/r5-created-updated-at
git cherry-pick 85565db
git remote remove fvp-r5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r5-created-updated-at/solucao.bundle \
    fvp/r5-created-updated-at:fvp-r5
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 85565db
# opcional, limpa a ref
git branch -D fvp-r5
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r5-created-updated-at/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r5-created-updated-at/solucao.patch
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

- `Event.php`: `$columns` (campo novo do M1 no mesmo ponto), o
  `use DateTime;` (R3) e métodos novos perto de `validates()`.
  Fique com todas as colunas e todos os métodos, um `use` só.
- `show.phtml` e `index.phtml`: outros cenários acrescentam itens na
  mesma `<dl>` e colunas na tabela (R2 "Criado por", R3
  "Descrição"); mantenha todos e confira `<th>` contra `<td>`.
- `EventTest`/`EventsControllerTest`: testes novos no mesmo trecho;
  mantenha todos.

Depois de aplicar: `./run db:populate`, os testes e o `./run phpcs`.

## Armadilhas

- Pôr as colunas em `$columns` e não preenchê-las: `INSERT` com
  `NULL` explícito, erro de `NOT NULL` ("Column 'created_at' cannot
  be null").
- Preencher `created_at` também na edição: a data de cadastro muda a
  cada `save()`. Só quando `newRecord()`.
- Confiar no `ON UPDATE CURRENT_TIMESTAMP`: hora do MySQL (UTC aqui,
  3 horas adiantada) e nada muda se os valores forem os mesmos.
- Esquecer `Model::update()`: ele não passa por `save()`; sem o
  override, `updated_at` fica com a hora do MySQL.
- Copiar `created_at` do formulário: o controller só copia `FIELDS`
  (teste cobre o envio de `created_at` no `update`).
- Teste com `sleep(1)` para ver `updated_at` mudar: lento. Recue as
  datas com `update([...])` antes.
- `DateTime::createFromFormat` com o formato errado devolve `false`:
  o método devolve vazio em vez de quebrar a view.
