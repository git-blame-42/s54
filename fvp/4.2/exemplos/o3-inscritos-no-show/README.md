# O3. Inscritos no `show` (`participants()`)

Pedido, como o professor faria na hora: "No detalhe do Evento, liste
quem está inscrito, com nome e e-mail."

Chance: média. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O3.

Branch no clone: `fvp/o3-inscritos-no-show`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                       |
|-----------|------------------------------------------------|
| `36a426d` | `feat: Show event participants in admin area`  |

## O que mudou

- `app/Models/Event.php`: relação nova `participants():
  BelongsToMany`, com `$this->BelongsToMany(User::class,
  'event_user', 'event_id', 'user_id')` (import de
  `Core\Database\ActiveRecord\BelongsToMany`).
- `app/views/admin/events/show.phtml`: depois de Vagas, seção
  "Inscritos" com uma tabela Nome / E-mail
  (`$event->participants()->get()`, saída com `htmlspecialchars`) ou
  "Nenhuma inscrição." quando não há inscritos.
- Testes:
  - `EventTest` (Unit):
    - `test_participants_should_return_the_enrolled_users` (2
      inscritos no Evento, 1 em outro; confere os e-mails, a classe
      `User` e `participants()->count()`);
    - `test_participants_should_be_empty_without_enrollments`.
  - `EventsControllerTest` (Integration):
    - `test_show_lists_participants_name_and_email` (dois inscritos,
      um com `<b>` no nome, que sai escapado; inscrito de outro
      Evento não aparece);
    - `test_show_without_enrollments_says_so`.
    - O helper `enroll()` ganhou o parâmetro opcional `$name`
      (padrão `'Participante'`, como antes).
  - `ShowParticipantsCest` (Acceptance, novo): Evento 01 da base
    mostra os 3 Participantes com e-mail; Evento 02 mostra "Nenhuma
    inscrição.".

## Por que assim

- Relação no model, do jeito do framework: `user()` já usa
  `belongsTo`; para N:N o `Model` oferece `BelongsToMany` (com B
  maiúsculo), que faz o JOIN pela tabela pivô com `prepare` +
  `bindValue`. Nada de SQL no controller nem na view.
- Devolver a relação (e não um array) segue o catálogo e o padrão de
  `user()`/`events()`: quem usa chama `->get()` ou `->count()`.
- Tabela pivô real: `event_user` (model `Enrollment`), não
  `enrollments`.
- Controller sem mudança: a view já recebe `$event`.
- Uma consulta só para a lista (sem N+1, diferente de
  `Enrollment::where` + `user()` por linha).

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

Resultado obtido: Unit 161, Integration 47, Acceptance 25 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela (populate): logue como `admin@example.com` / `123456`, abra
`/admin/events` e clique em "Ver" no primeiro Evento (3/3). Em
"Inscritos" aparecem Usuário, Mariana Souza e Carlos Pereira com os
e-mails. Sem porta no host:

```
docker compose exec web curl -s -c /tmp/c -d \
    'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/1
```

## Relação com outros cenários

- O1 (`o1-rota-eventos-lotados`) e P8 (`p8-exportar-csv`) mexem no
  mesmo controller, model e testes, em outros métodos.
- `participants()->count()` poderia substituir `enrollmentsCount()`,
  mas não foi trocado (não pedido; testes conferem o atual).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/o3-inscritos-no-show
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/o3-inscritos-no-show`. Cherry-pick cria commit
novo (SHA diferente); preserva conteúdo e mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o3 <caminho>/projeto
git fetch fvp-o3 fvp/o3-inscritos-no-show
git cherry-pick 36a426d
git remote remove fvp-o3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/o3-inscritos-no-show:fvp-o3
git cherry-pick 36a426d
# opcional, limpa a ref
git branch -D fvp-o3
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

- `app/Models/Event.php`: outro método novo no mesmo ponto (O1 põe
  `full()` perto de `hasEnrollments()`) ou outro `use` no topo.
  Fique com os dois.
- `show.phtml`: outro cenário que mexe em Vagas (badge "Lotado",
  M12). Mantenha os dois trechos; a seção "Inscritos" vem depois do
  `</dl>`.
- `EventsControllerTest.php`: o helper `enroll()` ganhou `$name`; se
  o alvo mudou o helper, junte as duas assinaturas (parâmetros
  opcionais no fim).

Depois de aplicar: rode `./run phpcs` e os testes de novo.

## Armadilhas

- O método do `Model` é `BelongsToMany`, com B maiúsculo (o PHP não
  diferencia maiúsculas em nome de método, mas mantenha igual ao
  core).
- Ordem dos parâmetros: tabela pivô, chave do Evento (`event_id`) e
  depois a do outro lado (`user_id`). Invertidos, a lista sai errada
  sem erro nenhum.
- `get()` devolve `array<Model>`: na view `$participant->name`
  funciona; em código de `app/` ou `tests/` o phpstan reclama de
  `fn(User $u)` no `array_map`. Use `instanceof User` (o teste usa
  `assertInstanceOf` dentro do `foreach`).
- `$event->participants` sem parênteses também funciona pelo `__get`
  mágico; prefira a forma explícita.
- `htmlspecialchars` no nome e no e-mail: o nome vem do cadastro e
  pode ter HTML (o teste cobre com `<b>`).
- `get()` não ordena: a lista segue a ordem do banco.
