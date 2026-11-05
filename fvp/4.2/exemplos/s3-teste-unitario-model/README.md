# S3. Teste unitário do método novo do model

Pedido, como o professor faria na hora: "Você criou o
`Event::duplicate()`. Agora escreva os testes unitários dele no
`EventTest`: título, data, inscrições, dono e se a cópia é gravada."

Chance: alta. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, S3.

Branch no clone: `fvp/s3-teste-unitario-model`, empilhada sobre P6
(`fvp/p6-duplicar-evento`), que parte de `main` (`f31006b`, "[E4.2]
feat: CRUD completo de Eventos"). O método testado é o de P6.

| Commit    | Mensagem                                             |
|-----------|------------------------------------------------------|
| `b0e1b12` | (base, P6) `feat: Duplicate an Event from the listing` |
| `af1a650` | `test: Cover Event::duplicate() with unit tests`     |

## O que mudou

Só `tests/Unit/Models/Events/EventTest.php`, seção nova
`P6: duplicate`, com 8 testes:

- `test_duplicate_should_return_a_new_unsaved_event`: `newRecord()` e
  nada gravado ainda.
- `test_duplicate_should_append_copy_to_the_title`
- `test_duplicate_should_keep_the_title_within_255_characters`: título
  de 255 caracteres, a cópia continua com 255 e salva.
- `test_duplicate_should_move_starts_on_to_seven_days_from_today`
- `test_duplicate_of_a_past_event_should_be_valid` (usa
  `createPastEvent()`).
- `test_duplicate_should_keep_the_other_columns_and_the_user`: outro
  admin como dono, `kind`, `modality`, `audience`, `location`,
  `capacity`.
- `test_duplicate_should_be_persisted_without_enrollments`: original
  com 3 Inscrições (`enroll($event, 3)`), cópia salva, `findById`
  acha a cópia com 0 Inscrições; o original continua com 3.
- `test_duplicate_should_not_change_the_original`

## Como escrever o teste de um método novo

1. Abra o teste do model (`tests/Unit/Models/Events/EventTest.php`) e
   use os helpers que já existem no topo: `createUser()`, `params()`,
   `createEvent([...])` (já faz `assertTrue(save())`), `enroll($event,
   N)`; para Evento passado, `createPastEvent()`.
2. Um teste por comportamento, nome em inglês
   `test_<metodo>_should_<efeito>`, como
   `test_where_should_filter_by_conditions` e
   `test_available_spots_should_be_capacity_minus_enrollments`.
3. Arranje (`createEvent`), aja (chame o método), confira (uma
   asserção principal; mais só se forem do mesmo efeito).
4. Se o método grava ou deveria gravar, não pare no `isValid()`:
   `save()` e depois `Event::findById(...)`, que lê do banco.
5. Datas relativas (`strtotime('+7 days')`), nunca fixas.
6. Cada teste recria o schema: não há dados do populate; crie tudo.
7. Rode só os seus enquanto escreve:

```
./run test tests/Unit/Models/Events/EventTest.php \
    --filter duplicate
```

## Por que assim

- `duplicate()` devolve a cópia sem salvar (o controller de P6 salva).
  Por isso há um teste do "ainda não gravou" e outro que salva e lê de
  volta: juntos cobrem "persistido" sem fingir que o método grava.
- O teste do dono usa **outro** admin: com o admin padrão do `setUp`
  o teste passaria mesmo se `duplicate()` pusesse o usuário logado.
- O teste de inscrições confere os dois lados (cópia 0, original 3):
  prova que as Inscrições não foram movidas nem copiadas.
- Os testes de controller, acesso e aceitação ficaram em P6; aqui só o
  model, que é o que o pedido cobra.

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

Resultado obtido: Unit 167, Integration 52, Acceptance 24 (com P6:
159 / 52 / 24; base `main`: 159 / 45 / 23). `--filter duplicate`: 8
testes, 37 asserções. PHPCS e PHPStan sem erros.

## Relação com outros cenários

- P6 (`p6-duplicar-evento`): base obrigatória (o método testado).
- A mesma receita serve para o método de qualquer outro cenário:
  `cancel()` (P5), `isEditable()` (Q2), `newCheckinCode()` (P1),
  `searchByTitle`/`upcoming` (N2, N3). Os exemplos desses cenários já
  trazem os testes; use-os como modelo.
- S1/S2: aceitação e acesso por HTTP da mesma modificação.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/s3-teste-unitario-model`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/s3-teste-unitario-model`.

Escolha o que aplicar:

- Sem P6 no seu repositório: aplique o range inteiro
  `f31006b..af1a650` (P6 + S3, dois commits, nessa ordem).
- Com P6 já aplicado: aplique só o topo, `af1a650`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-s3 \
    s50/fvp/4.2/exemplos/s3-teste-unitario-model/projeto
git fetch fvp-s3 fvp/s3-teste-unitario-model
# range (P6 + S3):
git cherry-pick f31006b..af1a650
# ou só o topo, se já tem P6:
# git cherry-pick af1a650
git remote remove fvp-s3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/s3-teste-unitario-model/solucao.bundle \
    fvp/s3-teste-unitario-model:fvp-s3
git cherry-pick f31006b..af1a650
# ou só o topo: git cherry-pick af1a650
# opcional, limpa a ref
git branch -D fvp-s3
```

### 3. A partir do `solucao.patch` (`git am`)

O patch traz os dois commits (P6 e S3), em ordem.

```
git am s50/fvp/4.2/exemplos/s3-teste-unitario-model/solucao.patch
# já tem P6? quando o primeiro commit vier vazio ou conflitar:
# git am --skip
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

- `EventTest.php`: outros cenários também acrescentam seções antes de
  `starts_on: "não passada"...`. Fique com as duas seções.
- Se o seu `duplicate()` salva a cópia, troque
  `test_duplicate_should_return_a_new_unsaved_event` por um que
  confira `assertFalse($copy->newRecord())`, e tire o `save()` dos
  outros.
- Com P1 aplicado, acrescente
  `assertNotEquals($event->checkin_code, $copy->checkin_code)` depois
  do `save()`; com P5, `assertEquals('scheduled', $copy->status)`.

## Armadilhas

- Testar só o que o método devolve e não o banco: "persistido" pede
  `save()` + `findById`.
- Usar o admin do `setUp` para checar `user_id`: o teste não distingue
  "dono original" de "usuário logado".
- `strlen` em vez de `mb_strlen`: "ó" tem 2 bytes e o teste de 255
  caracteres falha.
- Data fixa (`'2026-12-01'`): o teste quebra quando a data passar.
- Nome de teste em português ou sem `test_`: o PHPUnit não roda.
