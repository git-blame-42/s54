# P6. Duplicar Evento

Pedido, como o professor faria na hora: "Coloque um botão 'Duplicar'
na listagem. Ele cria uma cópia do Evento, com '(cópia)' no título,
data daqui a 7 dias e sem inscrições, e abre a edição da cópia."

Chance: média-alta. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P6.

Branch no clone: `fvp/p6-duplicar-evento`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                     |
|-----------|----------------------------------------------|
| `858c551` | `feat: Duplicate an Event from the listing`  |

Os testes unitários de `duplicate()` ficam de propósito no cenário S3
(`s3-teste-unitario-model`), empilhado sobre este; aqui ficam
controller, acesso e aceitação.

## O que mudou

- `app/Models/Event.php`: `duplicate(): Event` monta um `new Event()`,
  copia todas as colunas (`foreach (static::$columns as $column)
  $copy->$column = $this->$column;`), troca o título por
  `mb_substr($this->title, 0, 247) . ' (cópia)'` e `starts_on` por
  `date('Y-m-d', strtotime('+7 days'))`. Devolve a cópia **sem
  salvar**.
- `config/routes.php`: `POST /admin/events/{id}/duplicate` →
  `admin.events.duplicate` (grupo admin, depois do `destroy`).
- `app/Controllers/Admin/EventsController.php`: `duplicate`:
  `findEvent` (não achou → `eventNotFound()`), `$copy =
  $event->duplicate()`, `if ($copy->save())` → flash `success`
  "Evento duplicado! Revise os dados." e redirect para
  `admin.events.edit` da cópia; senão `danger` e volta ao `show` do
  original.
- `index.phtml`: form `method="post"` com o botão "Duplicar" em cada
  linha.

## Por que assim

- A cópia é regra do model; o controller só salva e escolhe o flash,
  como no `create`. Devolver sem salvar deixa o controller decidir o
  que fazer se o `save()` falhar.
- `new Event()` e não `clone $this`: o clone leva o `id`, continua
  "existente" (`newRecord()` falso) e o `save()` faria `UPDATE` no
  original.
- `$attributes` é privado em `Model`: a cópia passa por `__get`/`__set`
  (`$this->$column`), coluna por coluna de `$columns`.
- Inscrições moram em `event_user`, não em `events`: a cópia nasce sem
  nenhuma sem precisar de código extra.
- `user_id` do original (o pedido não diz o contrário). Com Q1 (só o
  dono edita), use o admin logado.
- `POST` porque cria um recurso; não precisa de `_method`.

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 159, Integration 52, Acceptance 24 (base: 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `EventsControllerTest`: `test_index_has_a_duplicate_button`,
  `test_duplicate_creates_a_copy_and_redirects_to_its_edit_form`,
  `test_duplicate_keeps_the_original_unchanged`,
  `test_duplicate_with_unknown_id_redirects_to_index`.
- `EventsAccessTest`: a rota em `eventRoutes()` (sem sessão →
  `/login`, Participante → `/dashboard`);
  `test_participant_should_not_duplicate_events`,
  `test_admin_should_duplicate_an_event`.
- `DuplicateEventCest` (novo): duplica o Evento 01 (lotado) pela
  listagem, cai na edição da cópia com os campos preenchidos, a cópia
  tem 0/3 vagas e a listagem passa a ter 17 Eventos.

## Relação com outros cenários

- S3 (`s3-teste-unitario-model`): o teste unitário de `duplicate()`,
  empilhado sobre este commit.
- P1 (código de check-in): com os dois, zere
  `$copy->checkin_code = null;` em `duplicate()` para o `save()` gerar
  outro (senão o `uniqueness` barra).
- P5 (status): com os dois, `$copy->status = 'scheduled';` (não copie
  um Evento cancelado como cancelado).
- Q1 (dono): `$copy->user_id` passa a ser o admin logado, definido no
  controller.
- O1 (rota RESTful nova): mesma receita de rota com sufixo.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p6-duplicar-evento`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é `s50/fvp/4.2/exemplos/p6-duplicar-evento`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p6 s50/fvp/4.2/exemplos/p6-duplicar-evento/projeto
git fetch fvp-p6 fvp/p6-duplicar-evento
git cherry-pick 858c551
git remote remove fvp-p6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/p6-duplicar-evento/solucao.bundle \
    fvp/p6-duplicar-evento:fvp-p6
git cherry-pick 858c551
# opcional, limpa a ref
git branch -D fvp-p6
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p6-duplicar-evento/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/p6-duplicar-evento/solucao.patch
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

- `config/routes.php`: P5, O1 e outros acrescentam rotas no mesmo
  ponto (depois do `destroy`). Fique com todas.
- `EventsController`: ação nova antes do `fill`; outra ação nova no
  mesmo lugar. Fique com as duas.
- `index.phtml` (célula de ações): P5, Q1, Q2, R4 mexem nos botões.
- `EventsAccessTest::eventRoutes` e o fim do `EventsControllerTest`.

## Armadilhas

- `clone $this`: `save()` vira `UPDATE` do original (ver acima).
- Copiar `starts_on`: de um Evento passado, a validação "não pode
  estar no passado" recusa a cópia. Por isso `+7 days`.
- Título de 255 caracteres + " (cópia)" estoura o `VARCHAR(255)`; o
  `mb_substr(..., 0, 247)` resolve (multibyte por causa do "ó").
- Redirecionar para `edit` sem flash: o admin não percebe que já está
  na cópia e não no original.
- Esquecer o `method="post"` no form: um link `GET` não casa a rota.
