# O11. Página 404 amigável, sem stack trace fora de development

Pedido, como o professor faria na hora: "Não mostre stack trace ao
usuário. URL inexistente deve mostrar uma página de não encontrado,
com link para voltar ao início; em development o trace continua."

Chance: baixa. Tempo estimado: 20-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O11.

Branch no clone: `fvp/o11-404-amigavel`, a partir de `main`
(`f31006b` do público).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `4dadfd3` | `feat: Show friendly 404/500 pages without stack...`     |

(mensagem completa: `feat: Show friendly 404/500 pages without stack
trace outside development`)

## O que mudou

- `core/Errors/ErrorsHandler.php`:
  - `viewFor(Throwable $e, string $env): ?string`: `null` em
    `development` (o handler segue com o trace de antes);
    `'errors/404'` para `HTTPException` 404; `'errors/500'` para o
    resto (exceção comum, `Error`, warning/notice, outro status).
  - `currentEnv(): string`: `$_ENV['APP_ENV']`, ou `'production'` se
    não houver valor.
  - `friendlyPage(string $view): string`: inclui
    `app/views/<view>.phtml` direto (com `ob_start`) e devolve o HTML.
  - `exceptionHandler`: depois do `ob_end_clean()`, se `viewFor` não
    for `null`, manda `HTTP/1.1 404 Not Found` (ou `500 Internal
    Server Error`), imprime a página e sai. Em development, o código
    antigo fica intacto.
  - `errorHandler` (warnings e notices): o mesmo, sempre com a página
    500, e `exit()`.
- `app/views/errors/404.phtml` e `500.phtml` (novos): HTML completo,
  sem layout e sem `$this`, título com `htmlspecialchars`, texto em
  pt-BR ("Página não encontrada" / "Erro interno"), botão "Voltar ao
  início" (`href="/"`). Não mostram mensagem, arquivo, linha nem
  trace.
- Testes:
  - `tests/Unit/Core/Errors/ErrorsHandlerTest.php` (novo, 7):
    `viewFor` dá 404/500 em `production` e `testing` e `null` em
    `development`; `currentEnv` lê `$_ENV` e cai em `production` sem
    valor ou com valor vazio; as duas páginas têm o texto e o link
    para `/` e não têm `Stack Trace` nem `.php`.
  - `tests/Integration/Access/NotFoundAccessTest.php` (novo, 1):
    Guzzle em `http://web:8080/rota-que-nao-existe` -> 404, frase
    `Not Found`, página amigável, sem `Stack Trace`, sem a URI e sem
    `/var/www`.
  - `tests/Acceptance/errors/NotFoundCest.php` (novo, 1): abre a rota
    inexistente, vê "Página não encontrada" e o `404`, não vê
    `Stack Trace`, clica em "Voltar ao início" e cai em `/`.

## Por que assim

- A decisão (`viewFor`) é uma função pura: dá para testar sem HTTP,
  sem `header()` e sem `exit()`, que são as partes que ficam no
  handler.
- `APP_ENV` é lido na hora de tratar o erro, não no `init()`: em
  `config/bootstrap.php` o `ErrorsHandler::init()` roda antes do
  `EnvLoader::init()`, e no `init()` o `.env` ainda não foi lido.
- `$_ENV`, nunca `getenv()`: o `EnvLoader` grava só em `$_ENV` (sem
  `putenv`) e o contêiner `php` não tem `APP_ENV` no ambiente; lá
  `getenv('APP_ENV')` é `false`. No `php_test`, o `EnvLoader` copia
  para `$_ENV` o `APP_ENV=testing` do compose.
- Sem `APP_ENV` (erro antes do `EnvLoader`, `.env` ausente) vale
  `production`: na dúvida, esconde os detalhes.
- `testing` esconde o trace (decisão): a regra é "só development
  mostra". Ganho: o `php_test` (porta 8080, a do Guzzle e do
  Selenium) já roda fora de development, então dá para provar a
  página 404 por HTTP e no navegador sem mexer no ambiente do
  contêiner. Custo: se um Cest cair numa exceção, a tela do Selenium
  mostra "Erro interno" em vez do trace; para depurar, rode a mesma
  URL na porta 80 (development).
- Sem `Controller::render()`: ele precisa de `$title`, layout e
  sessão (`currentUser()` na navbar); se a view falhasse, o handler
  entraria em laço. A view é incluída direto.
- Link para `/` fixo, sem `route('root')`: o erro pode acontecer antes
  de `config/routes.php` ser carregado.
- Frase do status padrão (`Not Found`): o código antigo põe a
  mensagem (com a URI digitada) no cabeçalho HTTP. Fora de
  development nem o cabeçalho nem a página repetem a URI.
- Development não mudou nada (nem os defeitos antigos, ver
  Armadilhas): o pedido é esconder fora de development.

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

Resultado obtido: Unit 166, Integration 46, Acceptance 24 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema e populate não mudaram.

Na tela (stack de desenvolvimento, porta 80). Em development, como
antes:

```
docker compose exec -T web curl -s -i http://localhost/nao-existe
# HTTP/1.1 404 URI /nao-existe not found.
# <h1>URI /nao-existe not found.</h1>
# ... in /var/www/core/Router/Router.php:122
# Stack trace:
```

Troque `APP_ENV=development` por `APP_ENV=production` no `.env` (o
`.env` é lido a cada requisição; não precisa reiniciar) e repita:

```
docker compose exec -T web curl -s -i http://localhost/nao-existe
# HTTP/1.1 404 Not Found
# <p class="display-1 text-secondary" id="error_status">404</p>
# <h1>Página não encontrada</h1>
# <a href="/" class="btn btn-primary" id="back_home">Voltar ao
#   início</a>
docker compose exec -T web curl -s http://localhost/nao-existe \
  | grep -cE 'Stack|/var/www'
# 0
```

No navegador: `/qualquer-coisa` mostra a página e a aba Rede mostra
404. Volte o `.env` para `APP_ENV=development` (o trace volta).

A página 500 não tem rota que a dispare na base; ela foi conferida
pelo teste unitário e, à mão, com um script descartável no contêiner
`php` (`ErrorsHandler::init()` + `trigger_error(..., E_USER_WARNING)`
e `throw new RuntimeException`): em `production` sai "Erro interno"
sem trace; em `development`, o trace de antes.

## Relação com outros cenários

- O8 (`o8-status-http`): "Evento não encontrado" vira 404 com uma
  view própria, `admin/events/not_found.phtml`, dentro do layout.
  Não conflita com o `errors/404.phtml` daqui: o O8 trata o Evento
  inexistente no controller; este, a rota inexistente no handler.
- N10 (`n10-pagina-id-invalido`): página/id inválidos continuam
  redirect com flash; não passam pelo `ErrorsHandler`.
- T4 (`t4-env-per-page`): outra leitura de `$_ENV`; vale a mesma
  regra (ler `$_ENV`, não `getenv()`, e ter um padrão).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/o11-404-amigavel
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é `s50/fvp/4.2/exemplos/o11-404-amigavel`.
Cherry-pick cria commit novo (SHA diferente); preserva conteúdo e
mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o11 <caminho>/projeto
git fetch fvp-o11 fvp/o11-404-amigavel
git cherry-pick 4dadfd3
git remote remove fvp-o11
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/o11-404-amigavel:fvp-o11
git cherry-pick 4dadfd3
# opcional, limpa a ref
git branch -D fvp-o11
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
# arquivos marcados "both modified" / "both added"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
# ou: git am --continue
git cherry-pick --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `core/Errors/ErrorsHandler.php`: outro cenário que mexa no handler
  (raro). Os métodos novos são independentes; mantenha o desvio
  `if ($view !== null)` logo depois do `ob_end_clean()`.

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classes de teste novas), `./run phpcs`, `./run phpstan` e os
testes.

## Armadilhas

- `getenv('APP_ENV')` devolve `false` no contêiner `php`: o `.env`
  só vai para `$_ENV`. Com `getenv`, o desvio nunca vê development
  e o trace some também na máquina do desenvolvedor.
- Ler o `APP_ENV` no `init()` (ou no construtor) pega `$_ENV` vazio:
  o `EnvLoader::init()` vem depois no bootstrap.
- `ob_end_clean()` descarta o que já foi impresso; nada pode sair
  antes do `header()`, senão "headers already sent".
- Não reaproveite `$e->getMessage()` na página: a mensagem do 404
  traz a URI digitada (o `<h1>` antigo a imprime sem escape).
- `set_error_handler` só pega warnings e notices. Exceções e `Error`
  (ex.: classe inexistente) caem no `set_exception_handler`, que
  também mostra a 500. Erro fatal de verdade (memória esgotada,
  sintaxe) não passa por nenhum dos dois.
- O contêiner de teste roda em `testing`, então os testes por HTTP
  (Guzzle, Selenium) não enxergam o trace. Para testar development
  por HTTP seria preciso mudar o ambiente do `php_test`; fica no
  teste unitário do `viewFor`.
- O código de development tem defeitos antigos que não foram
  mexidos: `{get_class($e)}` no heredoc não chama a função (imprime
  `$e` inteiro, com trace) e o `case E_USER_WARNING` imprime
  `$errorStr` duas vezes.
- Pasta nova de teste (`tests/Unit/Core/Errors`): namespace igual ao
  caminho, senão o `--strict-psr` reclama.
