# P21. Imagem de capa (banner) do Evento por upload

Pedido, como o professor faria na hora: "O Evento ganha uma imagem de
capa. No formulário de cadastro e de edição há um campo de arquivo;
aceite só JPG ou PNG de até 2 MB, de verdade (não confie na extensão).
A imagem aparece no detalhe do Evento. Na edição, deixar o campo vazio
mantém a imagem atual."

Chance: baixa. Tempo estimado: 35-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P21.

Branch no clone: `fvp/p21-banner-upload`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                |
|-----------|-----------------------------------------|
| `a65b218` | `feat: Add event cover image upload`    |

## O que mudou

- `database/schema.sql`: `banner_name VARCHAR(255) NULL` depois de
  `capacity` em `events`. Guarda só o nome gerado; `NULL` = sem capa.
- `app/Services/EventBanner.php` (novo, molde do `ProfileAvatar` do
  problem-track):
  - `validate(array $file)`: confere o código de erro do upload, o
    tamanho real do arquivo (`filesize`, até `MAX_SIZE` = 2 MB) e o
    tipo pelo conteúdo com `finfo` (`image/jpeg` ou `image/png`).
    Registra o problema em `errors('banner')` do Evento.
  - `store(array $file)`: gera o nome com `bin2hex(random_bytes(16))`
    mais a extensão do tipo detectado, move com `move_uploaded_file`
    para `public/assets/uploads/events/`, apaga a capa anterior e
    grava `banner_name` com `Model::update()`.
  - `url()`: `/assets/uploads/events/<nome>` ou `null`.
  - `delete()`: apaga o arquivo do disco.
- `app/Models/Event.php`:
  - `'banner_name'` em `$columns` e `@property string|null`.
  - `attachBanner(?array $file)`: guarda o arquivo de `$_FILES` numa
    propriedade privada; campo vazio (`UPLOAD_ERR_NO_FILE`) não anexa
    nada.
  - `validates()` chama `banner()->validate()` quando há arquivo.
  - `save()` sobrescrito: `parent::save()` e, se deu certo e há
    arquivo, `banner()->store()`.
  - `banner(): EventBanner`; `safeDestroy()` apaga o arquivo depois do
    `DELETE`.
- `app/Controllers/Admin/EventsController.php`: `create` e `update`
  chamam `$event->attachBanner($this->bannerUpload())`. O método
  privado `bannerUpload()` lê `$_FILES['event_banner']`, porque o
  `Request` do framework não carrega `$_FILES`. `FIELDS` não muda:
  `banner_name` nunca vem do formulário.
- `app/views/admin/events/_form.phtml`: `enctype="multipart/form-data"`
  no `<form>` e o bloco "Imagem de capa" (`type="file"`,
  `name="event_banner"`, `accept`, `$invalid('banner')`,
  `$feedback('banner')`). Na edição mostra a capa atual.
- `app/views/admin/events/show.phtml`: `<img class="event-banner">` no
  topo quando há capa.
- Testes novos:
  - `tests/Unit/Services/EventBannerTest.php` (10): PNG aceito; texto
    com nome `.png` recusado; mais de 2 MB; `UPLOAD_ERR_INI_SIZE`;
    `UPLOAD_ERR_PARTIAL`; nome gerado (o do cliente era
    `../../foto do cliente.jpg`); troca apaga o arquivo antigo;
    `move_uploaded_file` recusa arquivo que não veio de POST;
    `delete()`; `url()` nula.
  - `EventTest` (+3): banner inválido barra o `save()` sem gravar;
    campo vazio mantém `banner_name`; `safeDestroy()` apaga o arquivo.
  - `EventsControllerTest` (+5): form multipart com o campo; criar com
    arquivo que não é imagem (erro no campo, nada gravado); editar sem
    arquivo mantém a capa; `show` e `edit` com e sem `<img>`.
  - `tests/Acceptance/admin/events/BannerUploadCest.php` (3): cadastrar
    com capa (confere nome gerado, arquivo no disco e a imagem
    carregada no navegador, `naturalWidth` 240); trocar a capa na
    edição (o arquivo antigo some); recusar `banner-falso.png`.
  - Arquivos de envio em `tests/Support/Data/`: `banner.png` (PNG
    240x60) e `banner-falso.png` (texto com extensão `.png`).

## Por que assim

- Validação no model: `Event::validates()` chama a validação do
  arquivo, então um upload inválido marca o campo junto com os outros
  erros e o `save()` devolve `false` antes do `INSERT`/`UPDATE`. Nada
  é gravado e não sobra arquivo órfão. Só depois de salvar o registro
  o arquivo é movido. O controller continua fino.
- O código de upload fica em `EventBanner`, como o `ProfileAvatar` do
  problem-track: o model expõe `banner()` e não carrega `finfo`,
  caminhos e `move_uploaded_file`.
- Tipo pelo conteúdo: `$file['type']` e a extensão vêm do navegador e
  podem ser qualquer coisa. `finfo` lê os bytes do arquivo. A extensão
  gravada sai do tipo detectado, não do nome enviado.
- Nome gerado: o nome do cliente pode ter `../`, espaços, acentos ou
  repetir o de outro Evento. 32 hexadecimais aleatórios evitam tudo
  isso e também o cache do navegador ao trocar a capa.
- Só JPG e PNG: o `server/nginx.conf` serve direto do disco apenas
  `css|js|jpeg|png|jpg`. Um `.webp` ou `.gif` cairia no
  `public/index.php` e daria 404.
- 2 MB é a regra do model; `php/uploads.ini` aceita até 40 MB
  (`upload_max_filesize`/`post_max_size`). Acima disso o PHP devolve
  `UPLOAD_ERR_INI_SIZE`, tratado com a mesma mensagem.
- Uploads não são versionados: o `.gitignore` da base já ignora
  `/public/assets/uploads/*` e mantém só o `.gitkeep`.
- `banner_name` fica fora de `FIELDS`: o formulário não consegue
  apontar a capa para um arquivo qualquer.

## Como verificar

```
./run up -d
# refaz o schema (o populate roda migrate()) e popula
./run db:populate
# classe nova (App\Services\EventBanner)
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
```

Resultado obtido: Unit 172 testes, Integration 50, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

No navegador (admin@example.com / 123456): edite um Evento, escolha um
PNG e salve; o detalhe mostra a imagem. Renomeie um `.txt` para `.png`
e envie: "deve ser uma imagem JPG ou PNG!" embaixo do campo, e nada
muda. O arquivo fica em `public/assets/uploads/events/` com nome de 32
hexadecimais, e `git status` não o lista.

## Relação com outros cenários

- M1 (`m1-campo-novo-workload-hours`): mesmo roteiro de coluna nova
  (schema, `$columns`, form, `show`), mas aqui a coluna não entra em
  `FIELDS` e o valor vem de `$_FILES`, não do `Request`. Se aplicar os
  dois, fique com as duas colunas em `schema.sql` e `$columns`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p21-banner-upload`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p21-banner-upload`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p21 \
    s50/fvp/4.2/exemplos/p21-banner-upload/projeto
git fetch fvp-p21 fvp/p21-banner-upload
git cherry-pick a65b218
git remote remove fvp-p21
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p21-banner-upload/solucao.bundle \
    fvp/p21-banner-upload:fvp-p21
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick a65b218
# opcional, limpa a ref
git branch -D fvp-p21
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p21-banner-upload/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p21-banner-upload/solucao.patch
```

O patch traz `tests/Support/Data/banner.png` como diff binário; o
`git am`/`git apply` recria o arquivo.

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

- `schema.sql` e `$columns` de `Event.php`: todo campo novo (M1, M4)
  mexe nas mesmas linhas. Fique com as duas colunas.
- `Event::validates()` e `Event::safeDestroy()`: cenários que mudam a
  validação ou a remoção mexem no mesmo método. Mantenha o bloco
  `if ($this->bannerUpload !== null)` no fim de `validates()` e o
  `banner()->delete()` depois de um `DELETE` bem-sucedido.
- `create`/`update` do controller e `_form.phtml`: mantenha a linha
  `attachBanner(...)` logo depois do `fill(...)` e o `enctype` no
  `<form>`.

## Armadilhas

- Sem `enctype="multipart/form-data"` o navegador manda só o nome do
  arquivo como texto: `$_FILES` chega vazio e nada acontece, sem erro.
- `$_FILES` não passa pelo `Request`: `getParam('event_banner')` é
  sempre `null`. Use `name="event_banner"` (fora de `event[...]`); com
  `event[banner]` o PHP monta `$_FILES['event']['name']['banner']`.
- Pasta sem permissão (Linux): o PHP-FPM roda como `www-data` e não
  grava em `public/assets/uploads/` de outro dono; `mkdir` e
  `move_uploaded_file` falham. O README do projeto e o CI já fazem
  `sudo chown www-data:www-data public/assets/uploads`. No Docker
  Desktop (macOS/Windows) não acontece.
- `move_uploaded_file` só move arquivo que chegou por POST; teste de
  unidade com `tempnam` precisa sobrescrever `moveUploadedFile()`
  (feito no `EventBannerTest` com classe anônima).
- Acima de `post_max_size` (40 MB) o PHP descarta o POST inteiro:
  `$_POST` e `$_FILES` chegam vazios e o form volta com todos os
  campos marcados como vazios.
- Arquivo inválido não pode ser movido antes da validação: se mover
  antes do `save()` e a validação falhar, sobra arquivo órfão.
- O navegador não reenvia o arquivo quando o form volta com erro: é
  preciso escolher a imagem de novo.
- `Model::save()` grava todas as colunas: `banner_name` vem do
  `findById` e é regravado igual; por isso ele não pode entrar em
  `FIELDS` (o formulário o apagaria).
- Parâmetros de rota só casam `{[a-z,_]+}`: se criar uma rota própria
  para o upload, use `{id}`, nunca `{eventId}`.
