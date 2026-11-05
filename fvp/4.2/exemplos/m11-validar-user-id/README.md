# M11. Validar `user_id` no model (existe e é admin)

Pedido, como o professor faria na hora: "Hoje, um Evento com `user_id`
que não existe estoura a FK e dá erro 500. Valide no model: o criador
precisa existir e ser Administrador. Faça uma validação genérica
`exists` em `lib/Validations.php`, reaproveitável para outras chaves
estrangeiras."

Chance: média. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M11 ("Validar `user_id` no model (usuário
existe e é admin; hoje PDOException/500): `Validations::exists`-like,
EVT sem user_id").

Branch no clone: `fvp/m11-validar-user-id`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

Commit:

- `879d905` `feat: Validate that the event creator exists and is an
  admin`

## O que mudou

- `lib/Validations.php`: `exists(string $attribute, string
  $modelClass, $obj, array $conditions = [])`. O valor precisa ser um
  inteiro (aceita string numérica, como `integerMin`) e
  `$modelClass::exists(['id' => $id] + $conditions)` precisa achar o
  registro; senão erro "não existe!" no atributo. `$conditions`
  acrescenta filtros de igualdade (ex.: `['role' => 'admin']`).
- `app/Models/Event.php`, no início de `validates()`:

  ```php
  Validations::notEmpty('user_id', $this) &&
      Validations::exists('user_id', User::class, $this) &&
      $this->validatesCreatorIsAdmin();
  ```

  `validatesCreatorIsAdmin()` (privado) busca `User::findById` e, se
  não for `isAdmin()`, adiciona "deve ser um administrador!".
- Controller sem mudança: `create` continua pondo
  `$event->user_id = $this->currentUser()?->id` e `fill()` nunca copia
  `user_id` do formulário.
- Testes:
  - `tests/Unit/Lib/Validations/ExistsValidationsTest.php` (novo):
    model anônimo com `user_id` e `User` como `$modelClass`; id
    existente (int e string), id desconhecido, `null`/texto/array e
    `$conditions`.
  - `EventTest`: `user_id` vazio, inexistente (sem gravar e sem
    exceção), Participante como criador e Administrador válido.

## Por que assim

- Antes, `Event::validates()` não olhava `user_id`: um id inexistente
  passava na validação e o `INSERT` estourava a FK
  (`fk_events_user`) com `PDOException`, que o `ErrorsHandler` vira
  500. Agora é erro de validação, como os outros campos.
- `exists` genérica em `lib/Validations.php` (o mesmo lugar e estilo
  do M2): serve para `event_id` em Inscrição, `user_id` em comentário
  etc. A consulta é a do model (`Model::exists`, com `prepare` +
  `bindValue`), sem SQL em `lib/`.
- `filter_var(..., FILTER_VALIDATE_INT)` antes da consulta: `'abc'`
  ou array viram "não existe!" em vez de ir para o banco.
- "É admin" em método do model, não em `$conditions`: com
  `exists(..., ['role' => 'admin'])` a mensagem seria "não existe!"
  para um Participante, o que confunde. Duas mensagens distintas
  dizem o que está errado. A forma curta (uma linha, uma mensagem só)
  fica como alternativa, e o teste de `$conditions` mostra que ela
  funciona.
- O `&&` encadeia: vazio não consulta o banco; inexistente não busca
  o papel.
- Controller inalterado de propósito: `user_id` vem da sessão. A
  validação é uma defesa a mais (populate, testes, código futuro) e
  não substitui isso.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
./run db:populate
```

Resultado obtido: Unit 168 testes, Integration 45, Acceptance 23
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. `db:populate` sem
mudança (os 16 Eventos são do Administrador). Sem a mudança no
`Event`, os testes de `user_id` inexistente e de Participante falham
(o Evento passa na validação).

Testes novos:

- `ExistsValidationsTest`:
  - `test_exists_should_accept_an_existing_id`
  - `test_exists_should_accept_a_numeric_string_id`
  - `test_exists_should_reject_an_unknown_id`
  - `test_exists_should_reject_null_text_and_array_values`
  - `test_exists_should_apply_extra_conditions`
- `EventTest`:
  - `test_user_id_should_be_required`
  - `test_unknown_user_id_should_be_invalid_instead_of_a_foreign_key_error`
  - `test_participant_as_creator_should_be_invalid`
  - `test_admin_as_creator_should_be_valid`

`user_id` forjado no formulário: o teste já existia,
`EventsControllerTest::test_create_ignores_user_id_sent_by_the_form`
(manda `user_id = 999` e confere que o Evento ficou com o admin
logado). Ele continua passando sem mudança, assim como
`test_create_with_valid_data_saves_event_for_logged_admin`.

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`): mesmo padrão, validação nova em
  `lib/Validations.php` com o modelo de `integerMin` (atributo,
  objeto, `addError`, `return bool`) e chamada encadeada em
  `Event::validates()`. Se os dois forem aplicados, as funções ficam
  lado a lado.
- Q1 (`q1-so-dono-edita-remove`): o Q1 cria um segundo Administrador;
  continua válido (é admin). O `isOwnedBy` do Q1 e esta validação não
  se tocam.
- P10 (`p10-comentarios-evento`): `Comment` pode usar
  `Validations::exists('event_id', Event::class, $this)` no lugar do
  `notEmpty` simples.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m11-validar-user-id`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/m11-validar-user-id`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m11 s50/fvp/4.2/exemplos/m11-validar-user-id/projeto
git fetch fvp-m11 fvp/m11-validar-user-id
git cherry-pick 879d905
git remote remove fvp-m11
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/m11-validar-user-id/solucao.bundle \
    fvp/m11-validar-user-id:fvp-m11
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 879d905
# opcional, limpa a ref
git branch -D fvp-m11
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m11-validar-user-id/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/m11-validar-user-id/solucao.patch
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

- `lib/Validations.php`: M2 e outros acrescentam funções no mesmo
  ponto (antes de `integerMin`). Fique com todas.
- `Event::validates()`: cenários de campo novo (M1, M2) acrescentam
  linhas. Mantenha o bloco de `user_id` no topo e as linhas do outro.
- `EventTest.php`: testes novos no mesmo ponto; fique com os dois.

Depois de aplicar: rode os testes e o `./run phpcs` (sem schema).

## Armadilhas

- Validar só com `notEmpty`: `user_id = 9999` passa e o 500 continua.
- Escrever SQL dentro de `Validations::exists`: o padrão do framework
  é SQL só no model. `Model::exists` já faz `prepare` + `bindValue`.
- Comparar papel sem buscar o usuário: `user_id` é só um número; o
  papel está em `users.role`.
- Todo teste ou populate que cria Evento com `user_id` de
  Participante passa a falhar na validação (aqui nenhum fazia isso;
  `EventTest::test_user_should_have_many_events` usa outro admin).
- `$event->user_id` pode chegar como string (form, PDO): por isso o
  `filter_var` e o `(int)` no `findById`.
- `lib/` fica fora do PHPStan: a assinatura com `class-string` no
  PHPDoc é documentação, não verificação.
- Teste de `exists` com model anônimo precisa de banco: o arquivo usa
  `Tests\TestCase` (cria e migra o schema), não o `TestCase` puro do
  PHPUnit como `ValidationsTest`.
