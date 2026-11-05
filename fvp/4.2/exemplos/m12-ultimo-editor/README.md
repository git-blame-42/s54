# M12. Último editor (`updated_by`) gravado da sessão no update

Pedido, como o professor faria na hora: "Guarde quem foi o último
administrador a editar cada Evento e mostre no detalhe: 'Última
edição por Fulano'. Esse dado não pode vir do formulário."

Chance: média-baixa. Tempo estimado: 20-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M12.

Branch no clone: `fvp/m12-ultimo-editor`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                   |
|-----------|--------------------------------------------|
| `f44b8b1` | `feat: Record the last editor of an event` |

## O que mudou

- `database/schema.sql` (`events`): `updated_by INT NULL` e
  `CONSTRAINT fk_events_updated_by FOREIGN KEY (updated_by)
  REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE`.
- `app/Models/Event.php`:
  - `'updated_by'` em `$columns` e `@property int|null
    $updated_by`.
  - `editor(): BelongsTo` (`belongsTo(User::class,
    'updated_by')`, como `user()`).
  - `editorName(): string`: `''` se `updated_by` for `null`; senão
    o nome do editor, com `instanceof User` ao converter o `Model`
    devolvido por `get()`.
- `app/Controllers/Admin/EventsController.php` (`update`): depois
  do `fill`, `$event->updated_by = $this->currentUser()?->id;`.
  `updated_by` **não** entra em `FIELDS`; `create` não mexe nele
  (fica `NULL`).
- `app/views/admin/events/show.phtml`: abaixo dos dados,
  `<p class="last-editor">Última edição por <nome></p>` só quando
  `editorName() !== ''` (nome com `htmlspecialchars`).
- Populate: sem mudança; os 16 Eventos ficam com `updated_by` NULL
  (`Model::save()` grava a coluna como `NULL`).
- Testes:
  - `EventTest`: Evento novo sem editor (`updated_by` NULL e
    `editorName()` `''`); `editor()` aponta para o usuário de
    `updated_by` e `editorName()` devolve o nome.
  - `EventsControllerTest`:
    - `test_update_records_the_logged_admin_as_last_editor`: outra
      admin logada edita; `updated_by` é o id dela e `user_id`
      (criador) não muda.
    - `test_update_ignores_updated_by_sent_by_the_form`:
      `updated_by` forjado em `event[...]` e na raiz é ignorado.
    - `test_create_leaves_updated_by_empty`.
    - `test_show_displays_the_last_editor_only_after_an_edit`.
  - Aceitação: `tests/Acceptance/admin/events/LastEditorCest.php`:
    o show do Evento 02 não tem a linha; edita pelo formulário e vê
    "Última edição por Administrador".

## Por que assim

- Dado de auditoria vem da sessão (`currentUser()`), como o
  `user_id` do `create`. Se viesse do formulário, qualquer admin
  poderia assinar a edição com o nome de outro.
- `fill()` só copia `FIELDS`: não incluir `updated_by` ali é o que
  garante que o valor do POST é descartado (o teste prova).
- `NULL` = nunca editado; por isso a coluna é `NULL` e o `create`
  não a preenche. `ON DELETE SET NULL`: remover o usuário editor
  não bloqueia nem apaga o Evento.
- `editorName()` no model (e não `editor()->get()->name` na view):
  o `get()` do `BelongsTo` tem retorno `Model` e não aceita `null`;
  com `updated_by` NULL, chamar na view daria erro. O método
  devolve `''` e a view só testa a string.
- `instanceof User`: o PHPStan nível 6 sabe que `get()` devolve
  `Model`, não `User`; o `instanceof` estreita o tipo para `->name`.

## Como verificar

```
# o schema mudou: refaça o banco
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 161, Integration 49, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. `db:populate` sem erro.

Na tela: como `admin@example.com`, abra `/admin/events/1` (sem a
linha), clique em "Editar", salve, e veja "Última edição por
Administrador" (o nome do admin do populate).

O populate da base tem um admin só, que também é o criador de todos
os Eventos. Para ver que o editor é quem está logado (e não o
criador), é preciso um segundo admin:

- com T1 ou Q1 aplicado, o populate já cria `admin2@example.com`
  (senha `123456`);
- sem eles, promova um Participante direto no banco:

```
docker compose exec db mysql -ueventos-user -peventos-pwd \
  eventos_development \
  -e "UPDATE users SET role='admin' WHERE email='user@example.com'"
```

Entre com o segundo admin, edite o Evento 1 e o `show` passa a dizer
"Última edição por Usuário" (ou o nome do admin2), embora o Evento
seja do Administrador. O `test_update_records_the_logged_admin_as_last_editor`
faz o mesmo com uma segunda admin criada no teste.

## Relação com outros cenários

- R2 (nome do criador no `show` e na listagem): mesmo molde
  (`BelongsTo` + nome com `instanceof`), mas sobre `user_id`. Os dois
  juntos ficam lado a lado no `show` ("Criado por" e "Última edição
  por").
- R5 (mostrar `created_at` e `updated_at`): complementa este;
  "Última edição por X em dd/mm/aaaa" junta `updated_by` com
  `updated_at` (lembre de pôr as duas colunas em `$columns` só se
  for lê-las; `updated_at` o banco preenche sozinho).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m12-ultimo-editor`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m12-ultimo-editor`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m12 s50/fvp/4.2/exemplos/m12-ultimo-editor/projeto
git fetch fvp-m12 fvp/m12-ultimo-editor
git cherry-pick f44b8b1
git remote remove fvp-m12
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/m12-ultimo-editor/solucao.bundle \
    fvp/m12-ultimo-editor:fvp-m12
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick f44b8b1
# opcional, limpa a ref
git branch -D fvp-m12
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m12-ultimo-editor/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/m12-ultimo-editor/solucao.patch
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

- `database/schema.sql` e `$columns` do `Event`: todo cenário de
  campo novo (M1, P1) acrescenta coluna no mesmo ponto; fique com
  todas.
- `EventsController::update`: cenários de restrição (só o dono
  edita) e de validação mexem no mesmo método; mantenha a linha
  `$event->updated_by = ...` depois do `fill` e antes do `save`.
- `admin/events/show.phtml`: R2 e R5 acrescentam linhas no mesmo
  ponto; fique com todas.
- `EventTest`/`EventsControllerTest`: métodos novos no mesmo ponto;
  fique com os dois lados.

Depois de aplicar: `./run db:populate` (schema mudou), os testes e
o `./run phpcs`.

## Armadilhas

- Pôr `updated_by` em `FIELDS`: o valor passa a vir do POST, que é
  exatamente o que o pedido proíbe.
- Esquecer `'updated_by'` em `$columns`: o `save()` não grava a
  coluna e o editor nunca aparece (o teste do controller falha).
- `updated_by INT NOT NULL`: o `create` (e o populate) manda `NULL`
  e o `INSERT` quebra, porque o `Model::save()` grava todas as
  colunas.
- `$event->editor()->get()->name` direto na view: com `updated_by`
  NULL, o `findBy` devolve `null` e o `get()` (retorno `Model`)
  lança `TypeError`, 500 no `show` de todo Evento nunca editado.
- Gravar o editor também no `create`: aí "Última edição" aparece
  em Evento recém-criado; se o professor quiser isso, documente.
- `db:populate` esquecido: o banco de desenvolvimento não tem a
  coluna e o `update` dá 500 (os testes recriam o schema e passam).
