# P13. Admin inscreve e remove um Participante pelo show

Pedido, como o professor faria na hora: "no detalhe do Evento da área
administrativa, o Administrador escolhe um Participante num select e o
inscreve; a lista de inscritos tem um botão para remover a inscrição.
O `user_id` vem do formulário, então valide no model: o usuário existe,
é Participante, há vaga e ele ainda não está inscrito."

Chance: média-alta. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P13.

Branch no clone: `fvp/p13-admin-inscreve-remove-participante`, a partir
de `main` (`f31006b`).

| Commit | Mensagem |
|--------|----------|
| `40ed7e4` | `feat: Admin enrolls and removes participants from event show` |

## O que mudou

- `config/routes.php`, no grupo `admin`, depois das rotas de Eventos:
  - `POST /admin/events/{event_id}/enrollments`
    (`admin.events.enrollments.create`);
  - `DELETE /admin/events/{event_id}/enrollments/{id}`
    (`admin.events.enrollments.destroy`).
- `app/Controllers/Admin/EnrollmentsController.php` (novo):
  - `create`: acha o Evento por `event_id` da rota (senão flash
    "Evento não encontrado!" e volta para a listagem), lê
    `enrollment[user_id]` do form (só se for escalar), cria a
    `Enrollment` e chama `save()`. Sucesso: "Participante inscrito com
    sucesso!". Falha: flash `danger` com `errors('base')` ou, se o
    select veio vazio, "Selecione um Participante!". Redireciona para
    `admin.events.show`.
  - `destroy`: `Enrollment::findBy(['id' => ..., 'event_id' => ...])`
    com os dois ids da rota; achou, `destroy()` e "Inscrição removida
    com sucesso!"; senão "Inscrição não encontrada!".
- `app/Models/Enrollment.php`: `validates()` novo. `notEmpty` em
  `event_id` e `user_id`; só ao criar: usuário existe (id inteiro via
  `filter_var`), tem `role` `participant`, Evento existe, não há
  duplicata (`self::exists` com as duas chaves) e
  `availableSpots() > 0`. Mensagens em `errors('base')`.
- `app/Models/Event.php`: `enrollments()` (`HasMany` por `event_id`) e
  `enrollableParticipants()` (Participantes ainda não inscritos, para
  o select).
- `app/views/admin/events/show.phtml`: depois do `</dl>`, seção
  "Inscritos" (tabela `#enrollments` com nome, e-mail e form
  `_method=DELETE` "Remover", ou "Nenhuma inscrição.") e o form com o
  `<select id="enrollment_user_id" name="enrollment[user_id]">` e o
  botão "Inscrever". Sem ninguém a oferecer: "Todos os Participantes
  já estão inscritos.".
- Testes:
  - `EnrollmentTest`: duplicata agora dá `save()` falso (antes esperava
    `PDOException`); o `UNIQUE` continua testado com classe anônima sem
    validação; lotado, última vaga, admin recusado, usuário inexistente
    (9999, `'abc'`, `'1abc'`), Evento inexistente, campos vazios.
  - `EventTest`: `enrollments()` e `enrollableParticipants()`.
  - `EnrollmentsControllerTest` (novo, 14 testes): create ok,
    `event_id` do form ignorado, duplicata, admin, usuário inexistente,
    lotado, select vazio, `user_id` array, Evento inexistente; destroy
    ok, Inscrição de outro Evento não sai, Evento inexistente; show com
    lista, botão e select (com `htmlspecialchars`).
  - `EventsAccessTest`: as 2 rotas em `eventRoutes()` e a de DELETE em
    `methodOverrideRoutes()`; Participante logado não inscreve nem
    remove; Admin inscreve e remove por HTTP.
  - `ManageEnrollmentsCest` (novo, 3 cenários): inscrever e remover no
    Evento 02; Evento 01 lotado recusa um 4º Participante; select sem
    os inscritos.

## Por que assim

- Recurso aninhado RESTful (`enrollments` dentro de `events`, com
  `create`/`destroy`): é o que a rubrica cobra; verbo na URI
  (`/enroll`) não.
- O `user_id` vem do form, então tudo que depende dele é regra do
  model (`validates()`), não `if` no controller. O controller só filtra
  a forma do payload (escalar ou nada) e mostra a mensagem.
- `Route` extrai `{event_id}` e `{id}` com o padrão `{[a-z,_]+}` e o
  `Request::addParams` faz `array_merge` com os da rota POR ÚLTIMO:
  um `event_id` ou `id` no corpo do POST não ganha do da URL.
- `findBy` por `id` E `event_id`: sem o `event_id`, o form do Evento A
  removeria uma Inscrição do Evento B só trocando o número.
- `filter_var(..., FILTER_VALIDATE_INT)` em vez de `(int)`: `'1abc'`
  viraria 1 e inscreveria o usuário 1.
- Mensagens em `base` (minúsculas, como as do `safeDestroy`), porque a
  tela mostra o erro na flash, não embaixo de um campo.
- Select só com Participantes não inscritos: o Admin não precisa
  adivinhar; a validação continua barrando quem mandar o POST na mão.

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
./run db:populate
```

Resultado obtido: Unit 170 testes, Integration 66, Acceptance 26
(base 159 / 45 / 23). PHPCS e PHPStan sem erros; schema não mudou;
`db:populate` sem erro.

No navegador: entre como `admin@example.com`, abra `/admin/events/2`
(sem inscritos), escolha um Participante e clique "Inscrever"; ele
aparece em "Inscritos" e some do select; "Remover" desfaz. No Evento 1
(3/3) inscreva `fernanda@example.com`: "não há vagas neste evento!".

## Relação com outros cenários

- P2 (inscrição do Participante): mesmas regras em
  `Enrollment::validates()`. Lá o `user_id` é o do logado; aqui vem do
  form, por isso as regras "existe" e "é Participante". Aplicando os
  dois, fique com um `validates()` só, com todas as regras, e ajuste
  as mensagens de duplicata ("você já está" x "o participante já
  está").
- O3 (inscritos no `show`): O3 lista por `participants()`
  (`BelongsToMany`); aqui a lista precisa do id da Inscrição para o
  botão remover, por isso percorre `enrollments()` e chama `user()`.
  Mantenha só uma seção "Inscritos" (a deste, que tem o botão).
- N11 (contagem): `enrollmentsCount()` e `availableSpots()` da base
  continuam sendo a fonte da regra de vagas e do "1/10" no show.
- P3 (cancelar a própria inscrição) é o `destroy` do Participante;
  este é o do Admin, em outro controller e outro prefixo de rota.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/p13-admin-inscreve-remove-participante
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p13-admin-inscreve-remove-participante`.
Cherry-pick cria commit novo (SHA diferente); preserva conteúdo e
mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p13 <caminho>/projeto
git fetch fvp-p13 fvp/p13-admin-inscreve-remove-participante
git cherry-pick 40ed7e4
git remote remove fvp-p13
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/p13-admin-inscreve-remove-participante:fvp-p13
git cherry-pick 40ed7e4
# opcional, limpa a ref
git branch -D fvp-p13
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am <caminho>/solucao.patch
# só o diff, sem commit
git apply --3way <caminho>/solucao.patch
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

Arquivos que costumam conflitar e por quê:

- `app/Models/Enrollment.php`: P2 e M11 também criam `validates()`.
  Junte as regras num método só (vazio, usuário, Evento, duplicata,
  vagas) e rode o `EnrollmentTest` dos dois.
- `EnrollmentTest.php`: P2 troca o mesmo teste da duplicata; fique com
  uma versão e ajuste a mensagem esperada.
- `show.phtml`: O3 e N6 mexem depois de Vagas. Uma seção "Inscritos"
  só, a deste cenário.
- `config/routes.php` e `EventsAccessTest.php`: outras rotas novas no
  mesmo ponto (O1, P5, P6); fique com todas, e nos providers também.
- `Event.php`: outro `use` ou método no mesmo ponto; fique com os dois.

Depois de aplicar: rode `./run phpcs` e os testes de novo.

## Armadilhas

- Nome do parâmetro da rota: o padrão aceita só `[a-z,_]`, então
  `{event_id}` funciona e `{eventId}` não casa (404). No controller é
  `getParam('event_id')`, não `getParam('id')` (que é a Inscrição).
- Sem o `_method=DELETE` no form, o POST para `.../enrollments/{id}`
  dá 404 (não existe POST com 2 ids).
- `EnrollmentTest` da base espera `PDOException` na duplicata; com a
  validação ele falha. Troque por `save()` falso e mantenha o teste do
  `UNIQUE` com uma subclasse anônima de `validates()` vazio.
- O populate, o `BaseEventsCest` e o `EventTest` só inscrevem
  Participantes dentro da capacidade; se o seu alvo inscreve admin ou
  passa da capacidade, a validação nova quebra o seed. O populate
  lança `RuntimeException` se `save()` falhar.
- phpstan: `@property int $user_id` faz `filter_var($this->user_id)`
  virar "comparação sempre falsa"; use `(string)`. E zerar
  `$_SESSION['flash'] = []` no meio do teste faz o phpstan achar que a
  chave não existe; leia por um helper `flash()`.
- `redirectTo` sem `return` depois do "não encontrado": no teste de
  controller ele só imprime `Location:` e o método seguiria.
- `ControllerTestCase` reusa o mesmo `Request` no teste:
  `addParams` acumula. Duas chamadas no mesmo teste herdam os
  parâmetros da anterior.
- `selectOption` do Selenium compara o texto exato: o `<option>` está
  numa linha só (`Nome (e-mail)`).
