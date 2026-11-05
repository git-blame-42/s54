# Q1. Só o dono edita e remove

Pedido, como o professor faria na hora: "Só o Administrador que criou o
Evento pode editar ou remover. Os outros Administradores podem ver, mas
não alterar. Esconda os botões e barre também o acesso pela URL."

Chance: média-alta. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, Q1.

Branch no clone: `fvp/q1-so-dono-edita-remove`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                           |
|-----------|----------------------------------------------------|
| `be206ca` | `feat: Restrict event edit and removal to its owner` |

## O que mudou

- `app/Controllers/Admin/EventsController.php`:
  - `findEvent` busca pelos Eventos do Administrador logado:
    `$this->currentUser()?->events()->findById((int) $id)` e devolve
    `$event instanceof Event ? $event : null`. Evento de outro
    Administrador vira `null` e cai no `eventNotFound` que já existia
    (flash "Evento não encontrado!", `Location: /admin/events`). Vale
    para `edit`, `update` e `destroy`.
  - `show` deixa de usar `findEvent` e chama
    `Event::findById((int) $request->getParam('id'))`: ver continua
    liberado para qualquer Administrador.
- `app/Models/Event.php`: `isOwnedBy(?User $user): bool`, compara
  `(int) $this->user_id` com `(int) $user->id` (falso para `null`).
- `app/views/admin/events/index.phtml`: "Editar" e "Remover" só
  aparecem se `$event->isOwnedBy($this->currentUser())`. "Ver" fica.
- `app/views/admin/events/show.phtml`: botão "Editar" com a mesma
  condição.
- `database/Populate/populate.php`: segunda Administradora
  `admin2@example.com` (senha `123456`), dona dos dois últimos Eventos
  (página 4 da listagem). As 14 Inscrições e o Evento 1 lotado não
  mudam.
- Testes:
  - `EventTest` (Unit): `isOwnedBy` com dono, outro e `null`; e com
    o Evento relido do banco.
  - `EventsControllerTest` (Integration), com um segundo Administrador
    (`otherAdmin()` + `Auth::login($outro)`): `edit`, `update` e
    `destroy` de Evento alheio terminam em `Location: /admin/events`
    com o flash e o Evento intacto; `show` alheio abre sem o link de
    edição; a listagem mostra os botões só no Evento próprio.
  - `OwnerOnlyCest` (Acceptance): logado como outro Administrador, a
    linha do Evento alheio não tem "Editar"/"Remover"; abrir
    `/admin/events/{id}/edit` pela URL volta para a listagem com o
    flash; o Evento que ele mesmo cria tem os dois botões.

## Por que assim

- Menor mudança: um ponto só (`findEvent`) protege as três ações.
  Usar a associação `User::events()` (`HasMany::findById`) faz a busca
  já filtrada por `user_id` no SQL do model; o controller não compara
  ids à mão.
- `instanceof Event`: `HasMany::findById` declara `?Model` e
  `findEvent` devolve `?Event`; sem a checagem o PHPStan (nível 6)
  acusa erro.
- Mesma mensagem de "não encontrado": não revela que o Evento existe e
  pertence a outra pessoa, e reaproveita o fluxo e o teste existentes.
  Se o professor pedir uma mensagem própria, compare
  `$event->isOwnedBy($this->currentUser())` depois de
  `Event::findById` e use
  `FlashMessage::danger('Você não pode alterar este Evento!')`.
- `show` amplo: o pedido restringe alterar, não ver. Alternativa, se
  o professor disser que "ver" também é só do dono: volte o `show`
  para `$this->findEvent($request)` (uma linha) e ajuste o teste
  `test_show_of_another_admins_event_is_allowed_without_edit_button`
  para esperar `Location: /admin/events`.
- A listagem continua mostrando todos os Eventos (é o painel de todos
  os Administradores). Esconder o botão é só conforto: a proteção real
  é o `findEvent`, coberta pelo teste da URL direta.
- `isOwnedBy` no model: a regra fica num lugar, usada pelas duas views
  e testável sem HTTP. O `(int)` nos dois lados evita falso negativo
  quando o PDO devolve o id como string.
  O catálogo mostra a versão sem método novo, direto na view:
  `<?php if ((int) $event->user_id === (int) $this->currentUser()?->id) : ?>`
  (mais rápida de digitar na prova; o efeito é o mesmo).

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

Resultado obtido: Unit 161 testes, Integration 50, Acceptance 26
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. Sem classe nova em
`app/`, não precisa de `dump-autoload`.

Testes novos:

- `EventTest`:
  - `test_is_owned_by_should_be_true_only_for_the_creator`
  - `test_is_owned_by_should_work_for_event_loaded_from_the_database`
- `EventsControllerTest`:
  - `test_show_of_another_admins_event_is_allowed_without_edit_button`
  - `test_index_hides_edit_and_remove_of_another_admins_event`
  - `test_edit_of_another_admins_event_redirects_to_index`
  - `test_update_of_another_admins_event_keeps_it_intact`
  - `test_destroy_of_another_admins_event_keeps_it`
- `OwnerOnlyCest`:
  - `otherAdminSeesEventsWithoutEditAndRemove`
  - `otherAdminCannotOpenEditByUrl`
  - `ownEventShowsEditAndRemove`

Os testes de acesso que já existiam (`EventsAccessTest`: sem sessão e
Participante) seguem passando sem mudança.

No navegador, depois do `./run db:populate`: entre como
`admin@example.com` e abra a página 4 de `/admin/events`: os dois
Eventos da Administradora 2 só têm "Ver". Abra
`/admin/events/16/edit` direto: volta para a listagem com "Evento não
encontrado!". Entre como `admin2@example.com`: só esses dois têm
"Editar" e "Remover". Pelo terminal (clone sem porta):

```
docker compose exec web sh -c "curl -s -c /tmp/c \
  -d 'user[email]=admin@example.com&user[password]=123456' \
  http://localhost/login >/dev/null; \
  curl -s -i -b /tmp/c http://localhost/admin/events/16/edit" \
  | grep -i location
# Location: /admin/events
```

Sem mexer no populate, dá para promover um Participante direto no
banco (vira dono de nada, então não vê botões em nenhum Evento):

```
docker compose exec db mysql -ueventos-user -peventos-pwd \
  eventos_development \
  -e "UPDATE users SET role='admin' WHERE email='user@example.com'"
```

## Relação com outros cenários

- R1 (`r1-flash-titulo-warning`): mexe nos textos de flash do mesmo
  controller. Se o professor pedir uma mensagem própria para Evento
  alheio, um `FlashMessage::warning(...)` do R1 cabe aqui.
- P10 (`p10-comentarios-evento`): também altera `show.phtml` (form e
  lista de comentários abaixo dos dados). Conflito só de vizinhança no
  fim do arquivo; fique com os dois blocos.
- P12 (`p12-codigo-aleatorio-login`): independente (login).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/q1-so-dono-edita-remove`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/q1-so-dono-edita-remove`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-q1 \
    s50/fvp/4.2/exemplos/q1-so-dono-edita-remove/projeto
git fetch fvp-q1 fvp/q1-so-dono-edita-remove
git cherry-pick be206ca
git remote remove fvp-q1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/q1-so-dono-edita-remove/solucao.bundle \
    fvp/q1-so-dono-edita-remove:fvp-q1
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick be206ca
# opcional, limpa a ref
git branch -D fvp-q1
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/q1-so-dono-edita-remove/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/q1-so-dono-edita-remove/solucao.patch
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

- `EventsController.php` (`findEvent`, `show`): outros cenários mudam
  a busca (filtros, "não editar Evento passado"). Mantenha o
  `findEvent` via `currentUser()?->events()` e acrescente a regra do
  outro cenário depois do `null`.
- `index.phtml` (coluna Ações): cenários que acrescentam colunas ou
  badges mexem na mesma linha. Mantenha o `if isOwnedBy` envolvendo
  só "Editar" e o form de "Remover".
- `populate.php`: outro cenário que mude o `foreach` dos Eventos. Fique
  com o `$owner` deste e com os campos do outro.
- `EventsControllerTest.php` / `EventTest.php`: testes novos no fim do
  arquivo; fique com os dois lados.

Depois de aplicar: `./run db:populate` (populate mudou), os testes e
`./run phpcs`.

## Armadilhas

- Sem o `instanceof Event`, o PHPStan reclama: `HasMany::findById`
  devolve `?Model`.
- `findEvent` também era usado pelo `show`: sem trocar a linha do
  `show`, Evento alheio passa a dar "Evento não encontrado!" até para
  ver. Decida com o professor e teste o caso.
- Esconder o botão não protege nada: teste a URL direta
  (`/admin/events/{id}/edit`, `PUT`, `DELETE`).
- Comparar `user_id` sem `(int)`: o PDO pode devolver string e o `===`
  falha.
- Os testes comparam o `Location` com `assertSame`: o redirecionamento
  de Evento alheio é `/admin/events`, sem barra no fim.
- O dono que atualiza com sucesso continua indo para
  `/admin/events/{id}` (o `show`), não para a listagem.
- No teste de controller, `Auth::login($outro)` troca o usuário da
  sessão; os Eventos criados antes continuam do primeiro Administrador.
- `user_id` nunca vem do formulário (`fill` ignora); o
  `test_update_of_another_admins_event_keeps_it_intact` confere que o
  dono não muda.
