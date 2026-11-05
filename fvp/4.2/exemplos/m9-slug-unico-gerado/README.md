# M9. Slug único gerado no backend

Pedido, como o professor faria na hora: "Cada Evento ganha um slug
único gerado pelo backend a partir do título (`oficina-de-git`; se
repetir, `oficina-de-git-2`, `-3`...). O slug não vem do formulário,
aparece no detalhe e na listagem, e o Evento pode ser aberto em
`/events/{slug}`."

Chance: média. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M9.

Branch no clone: `fvp/m9-slug-unico-gerado`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                      |
|-----------|-----------------------------------------------|
| `b88fb5b` | `feat: Generate a unique slug for each event` |

## O que mudou

- `lib/StringUtils.php`: `slugify(string): string`. Minúsculas
  (`mb_strtolower`), acentos trocados por uma tabela fixa (`strtr`),
  todo trecho que não é `[a-z0-9]` vira um hífen, hífens das pontas
  removidos. Pode devolver `''` (título só com símbolos).
- `database/schema.sql`: `slug VARCHAR(255) NOT NULL` e
  `UNIQUE KEY uq_events_slug (slug)` em `events`.
- `app/Models/Event.php`:
  - `@property string|null $slug` e `'slug'` em `$columns`;
  - `save()` sobrescrito: em registro novo sem slug, gera
    `uniqueSlug($title)` e chama `parent::save()`. Na edição o slug
    não muda, mesmo com título novo;
  - `uniqueSlug()`: base do `slugify` (até 240 caracteres, `evento`
    se vazia) e sufixo `-2`, `-3`... enquanto
    `Event::exists(['slug' => ...])`;
  - `findBySlug()` (com `instanceof`);
  - `validates()`: `Validations::uniqueness('slug', $this)` quando há
    slug (registro novo antes do `save()` ainda não tem).
- `EventsController` do admin sem mudança: `slug` fica fora de
  `FIELDS`, então o que vier do form é ignorado.
- Rota nova no grupo `auth`: `GET /events/{slug}` →
  `App\Controllers\EventsController::show` (controller novo) com a
  view `app/views/events/show.phtml` (detalhe só de leitura: sem
  editar, vagas disponíveis). Slug desconhecido: "Evento não
  encontrado!" e redirect para `/dashboard`.
- Admin: a listagem mostra o slug como link embaixo do título; o
  `show` tem o par "Endereço" com o link `/events/{slug}`.
- Populate sem mudança: o `save()` gera o slug dos 16 Eventos.
- Testes:
  - `StringUtilsTest` (4): espaços, acentos (`AÇÃO`, `Über`),
    símbolos (`C++ / PHP & SQL` → `c-php-sql`), texto sem letras.
  - `EventTest` (8): slug gerado no `save()`; títulos repetidos →
    `-2`, `-3`; fallback `evento`; título de 255 caracteres; slug não
    muda na edição; `isValid()` antes do `save()` não exige slug;
    slug repetido forçado é inválido ("já existe um registro com esse
    dado"); `findBySlug`.
  - `Admin\EventsControllerTest` (3): `create` ignora `slug` do form;
    listagem e `show` têm o link; `update` mantém o slug.
  - `EventsControllerTest` (novo, 2): `show` por slug renderiza (sem
    link de editar) e slug desconhecido redireciona.
  - `EventsAccessTest` (2): sem sessão vai para `/login`; Participante
    e Administrador abrem `/events/{slug}`.
  - `ShowEventBySlugCest` (novo, 2): da listagem/`show` do admin até
    `/events/evento-01`; cadastrar outro "Evento 02" gera
    `evento-02-2`.

## Por que assim

- Gerar é regra de negócio: fica no model (`save()`), nunca no
  controller ou na view. `slugify` é utilitário genérico de texto, por
  isso vai para `lib/StringUtils.php`, que já tinha testes.
- Tabela de acentos fixa em vez de `iconv('ASCII//TRANSLIT')`: o
  resultado do `iconv` depende do locale e da libc da imagem.
- O laço com `exists()` garante o sufixo; a `UNIQUE KEY` protege de
  corrida entre dois cadastros; o `uniqueness` cobre quem tenta
  gravar um slug repetido à mão.
- Slug estável na edição: um endereço já divulgado não quebra quando
  o título é corrigido.
- Rota: a base não tem detalhe de Evento para Participante (O2). Em
  vez de abrir o `show` do admin, criei um `show` mínimo só de
  leitura no grupo `auth` (Participante e Administrador), com um
  controller próprio. Se o O2 existir, o `show` dele pode passar a
  buscar por slug.

## Como verificar

```
./run up -d
# refaz o schema (o populate roda migrate()) e popula
./run db:populate
# classe nova (App\Controllers\EventsController)
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
```

Resultado obtido: Unit 171 testes, Integration 52, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

No navegador: em `/admin/events` (admin@example.com / 123456) cada
título tem o slug embaixo; o link abre
`/events/introducao-a-apis-rest-com-php`. Logado como
`user@example.com`, o mesmo endereço abre o detalhe; um slug que não
existe volta ao painel com "Evento não encontrado!".

## Relação com outros cenários

- P1 (`p1-codigo-checkin-aleatorio`): mesmo mecanismo (campo gerado
  no `save()`, fora de `FIELDS`, laço com `exists()`, `UNIQUE KEY` e
  `uniqueness`). Aplicando os dois, junte os dois `save()` num só:
  gere o código e o slug antes do `parent::save()`.
- M6 (título único na mesma data): também usa `uniqueness`, mas
  rejeita o repetido em vez de gerar sufixo; com M6, títulos iguais
  na mesma data nem chegam a gerar `-2`.
- O2 (listagem e detalhe para o Participante): se existir, o `show`
  dele pode substituir o `App\Controllers\EventsController::show`
  deste cenário (mesma rota base `/events`).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m9-slug-unico-gerado`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m9-slug-unico-gerado`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m9 \
    s50/fvp/4.2/exemplos/m9-slug-unico-gerado/projeto
git fetch fvp-m9 fvp/m9-slug-unico-gerado
git cherry-pick b88fb5b
git remote remove fvp-m9
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m9-slug-unico-gerado/solucao.bundle \
    fvp/m9-slug-unico-gerado:fvp-m9
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick b88fb5b
# opcional, limpa a ref
git branch -D fvp-m9
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m9-slug-unico-gerado/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m9-slug-unico-gerado/solucao.patch
```

Depois de aplicar: `./run db:populate` (schema mudou),
`./run composer dump-autoload -o --strict-psr` (classe nova), os
testes e o `./run phpcs`.

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

- `Event.php`: P1 também sobrescreve `save()`; M1/M4 mexem em
  `$columns` e `validates()`. Fique com um `save()` só e com todas as
  chaves.
- `schema.sql`: outros campos e `UNIQUE KEY`; fique com todos.
- `config/routes.php`: O2/P2 também criam rotas `/events/...` no
  grupo `auth`; rotas fixas (`/events/mine`) antes de `{slug}`.
- `index.phtml`/`show.phtml` do admin: outras colunas e pares; fique
  com os dois.

## Armadilhas

- `slug` em `FIELDS`: o usuário escolheria o slug pelo formulário.
- Gerar no construtor ou em `validates()`: o construtor roda também
  ao carregar do banco; `validates()` roda em todo `isValid()`.
- `notEmpty('slug')` em `validates()`: `isValid()` antes do `save()`
  acusa slug vazio em registro novo (o slug só nasce no `save()`).
- Sem `!== ''` no fallback, título só com símbolos gera slug vazio e
  o segundo Evento assim colide.
- Título de 255 caracteres + `-2` estoura `VARCHAR(255)`: a base é
  cortada em 240.
- `strtolower` não baixa `Á` (multibyte): use `mb_strtolower`.
- Esquecer `db:populate`: `Unknown column 'slug'` só no navegador.
- Esquecer o `dump-autoload`: o `EventsController` novo pode não ser
  achado com o autoload otimizado (`-o`).
