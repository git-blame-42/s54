# Neovim com LazyVim: dicas para o FVP

Para quem vem do IntelliJ e vai editar o projeto no terminal do
laboratório. Pressupõe o `scripts/10-install-neovim.sh` rodado: Neovim,
LazyVim e a configuração da disciplina (`scripts/files/nvim/`), com
intelephense, phpcs e phpstan, atalhos para o framework e para o
`./run`, e snippets. Testado em Ubuntu 24.04.

Depois do script, abra um terminal novo (ou saia e entre de novo): o
`nvim` fica em `~/.local/bin`, que só entra no PATH na próxima sessão
do shell. Abra sempre na raiz do projeto: `cd ~/eventos-do-campus &&
nvim`.

Dentro do editor, `Esp ?` abre este arquivo numa janela (`q` fecha).

Regra única: se travar, `Esc` duas vezes e `:q!`. Nada se perde fora
do que não foi salvo.

## 1. Os três modos

| Modo     | Como entrar            | Para que serve                        |
|----------|------------------------|---------------------------------------|
| Normal   | `Esc`                  | Mover, apagar, copiar, dar comandos   |
| Inserção | `i`, `a`, `o`          | Digitar texto                         |
| Visual   | `v`, `V`               | Selecionar texto                      |

- `i` insere antes do cursor; `a` depois; `o` abre linha nova embaixo.
- Comandos com `:` são digitados no modo normal: `:w` salva, `:q` sai,
  `:wq` salva e sai, `:q!` sai sem salvar.
- O LazyVim também salva com `Ctrl+s` em qualquer modo.

## 2. Mover

Setas funcionam. Em modo normal, mais rápido:

- `h j k l`: esquerda, baixo, cima, direita.
- `w` / `b`: próxima / anterior palavra.
- `0` / `$`: início / fim da linha.
- `gg` / `G`: início / fim do arquivo. `42G` vai à linha 42.
- `Ctrl+d` / `Ctrl+u`: meia tela para baixo / cima.
- `/texto` + `Enter`: busca. `n` próxima, `N` anterior.
- `%`: pula para o parêntese, colchete ou chave correspondente.

## 3. Editar

- `u` desfaz. `Ctrl+r` refaz.
- `x` apaga o caractere. `dd` apaga a linha. `dw` apaga a palavra.
- `yy` copia a linha. `p` cola embaixo. `P` cola em cima.
- `cc` apaga a linha e entra em inserção. `ciw` troca a palavra.
- `>>` / `<<`: indenta / desindenta a linha.
- `=` sobre uma seleção: reindenta.
- `.` repete a última edição.

Selecionar e agir: `V`, desce com `j` até cobrir o bloco, depois `d`
(apaga), `y` (copia), `c` (troca), `=` (reindenta) ou `>` (indenta).

Clipboard: `y` e `p` já usam o do sistema (o mesmo do `Ctrl+C` /
`Ctrl+V` do navegador). Por SSH não há clipboard; use o botão do meio
do terminal.

Indentação: PHP e `.phtml` com 4 espaços, o que o PSR-12 do
`phpcs.xml` exige (o `.editorconfig` do projeto diz 2 para tudo, mas
com 2 o `./run phpcs` reprova cada linha; a configuração ignora o
`.editorconfig` só na indentação de `.php` e `.phtml`). Lua, YAML,
JSON e Markdown com 2. A linha vertical marca a coluna 131 no PHP (o
limite do `phpcs.xml` é 130 caracteres) e a 81 no Markdown.

Ao salvar um `.php`, o `phpcbf` do projeto corrige sozinho o que o
phpcs reclamaria (só com PHP nativo, o do `scripts/09`). `Esp u f`
liga e desliga isso. `Esp c f` formata à mão.

## 4. Atalhos de IDE (LazyVim)

A tecla líder é o espaço (`Esp`). Aperte `Esp` e espere um segundo: o
menu mostra tudo. Tudo em modo normal. `Esp s k` busca qualquer
atalho pelo nome.

Arquivos e busca (o `vendor/` fica fora das buscas, mas o `gd` entra
nele):

- `Esp Esp`: abre arquivo por nome (digite `_navbar`, `routes`).
- `Esp /`: busca texto em todo o projeto.
- `Esp e`: explorador de arquivos. `Enter` abre, `a` cria, `d` apaga,
  `r` renomeia, `q` fecha.
- `Esp s s`: símbolos do arquivo (métodos, propriedades).
- `Esp s S`: símbolos do projeto (classes).
- `Esp ,`: lista de arquivos abertos (buffers).
- `Esp b d`: fecha o arquivo atual.
- `Shift+h` / `Shift+l`: arquivo anterior / próximo.

Código (LSP, intelephense):

- `gd`: vai à definição. `Ctrl+o` volta. `Ctrl+i` avança.
- `gr`: quem usa este símbolo (referências).
- `K`: documentação e assinatura do que está sob o cursor.
- `Esp c a`: ações de código (importar classe, etc.).
- `Esp c r`: renomeia o símbolo em todo o projeto.
- `]d` / `[d`: próximo / anterior erro ou aviso.
- `Esp x x`: lista todos os erros do projeto.
- Autocompletar aparece sozinho ao digitar. `Enter` ou `Ctrl+y`
  aceita, `Ctrl+n` / `Ctrl+p` navega, `Ctrl+e` fecha.

Terminal e git:

- `Ctrl+/`: abre e fecha um terminal dentro do editor.
- `:!comando`: roda um comando e mostra a saída.
- `Esp g g`: lazygit, se instalado. `Esp g d`: diff do arquivo.
- As margens mostram linhas adicionadas, alteradas e removidas.

## 5. Atalhos do framework

Funcionam em qualquer arquivo do projeto (a raiz é a pasta com
`composer.json` e `./run`). Cada um tem um comando `:` equivalente.

Navegação MVC (`Esp m`):

- `Esp m a` (`:FwAlt`): alterna conforme o arquivo (os itens abaixo).
- `Esp m v` (`:FwView`): no controller, abre a view da ação sob o
  cursor.
- `Esp m c` (`:FwController`): na view, abre o controller, na ação
  que faz `render` dela.
- `Esp m t` (`:FwTest`): no model, controller ou lib, abre o teste;
  no teste, o código.
- `Esp m r` (`:FwRoute`): na rota, abre o método; no método, a rota;
  sobre um `route('nome')`, a rota com esse nome.
- `Esp m o` (`:FwModel`): model do recurso (de controller, view ou
  teste).

Exemplos no Eventos do Câmpus:

- Cursor dentro de `edit()` em `app/Controllers/Admin/EventsController.php`,
  `Esp m v`: abre `app/views/admin/events/edit.phtml`. Em
  `_form.phtml`, `Esp m c` volta ao controller.
- `app/Models/Event.php`, `Esp m t`: abre
  `tests/Unit/Models/Events/EventTest.php`. De novo `Esp m t`: volta.
- No controller, `Esp m t`:
  `tests/Integration/Controllers/Admin/EventsControllerTest.php`.
- Em `config/routes.php`, cursor na linha da rota `admin.events.show`,
  `Esp m r`: abre o `show()` do controller. No `index()`, `Esp m r`
  acha as duas rotas (lista e paginação): vai para a primeira e `]q`
  para a próxima.

Pastas do projeto (`Esp j`; minúscula abre arquivo por nome,
maiúscula busca texto dentro da pasta):

| Atalho              | Faz                                      |
|---------------------|------------------------------------------|
| `Esp j a` / `j A`   | `app/`                                   |
| `Esp j v` / `j V`   | `app/views/`                             |
| `Esp j c` / `j C`   | `core/`                                  |
| `Esp j l` / `j L`   | `lib/`                                   |
| `Esp j t` / `j T`   | `tests/`                                 |
| `Esp j r`           | abre `config/routes.php`                 |
| `Esp j s`           | abre `database/schema.sql`               |
| `Esp j f`           | cenários do FVP 4.2 (`fvp.sh -c 4.2`)    |

`:Fvp -s P1` mostra um cenário inteiro; `:Fvp 4.2 logout` procura por
termo (os mesmos argumentos do `fvp.sh`).

## 6. Comandos do ./run

Rodam na raiz do projeto, num terminal flutuante, com a saída colorida.
`q` esconde a janela; o aviso no canto diz se terminou bem. Rodar o
mesmo atalho com ele ainda em execução só mostra a janela de novo.

| Atalho    | Roda                                                      |
|-----------|-----------------------------------------------------------|
| `Esp t u` | `./run test tests/Unit`                                   |
| `Esp t i` | `./run test tests/Integration`                            |
| `Esp t b` | `./run test:browser` (aceitação, selenium)                |
| `Esp t a` | `./run test` (todos do phpunit)                           |
| `Esp t f` | testes do arquivo atual (num model: o teste dele)         |
| `Esp t n` | só o teste sob o cursor (`--filter`, ou `Cest:método`)    |
| `Esp t l` | repete o último                                           |
| `Esp p c` | `./run phpcs`                                             |
| `Esp p f` | `./run phpcbf` (corrige o projeto todo)                   |
| `Esp p s` | `./run phpstan`                                           |
| `Esp r u` | `./run up -d`                                             |
| `Esp r d` | `./run down` (pede confirmação)                           |
| `Esp r p` | `./run db:populate`                                       |
| `Esp r r` | `./run db:reset` (pede confirmação)                       |
| `Esp r s` | `./run ps`                                                |
| `Esp r c` | `:Run ` para digitar qualquer comando (`Tab` completa)    |

`:Run test tests/Unit/Models` roda qualquer coisa do `./run`.

## 7. Erros de phpcs e phpstan no editor

Com PHP nativo (`scripts/09-install-php.sh`), os erros aparecem
sozinhos na margem, com as regras do `phpcs.xml` e do `phpstan.neon`
do projeto: phpcs ao abrir, ao sair da inserção e ao salvar; phpstan
ao salvar. `]d` vai ao próximo, `Esp x x` lista todos.

Sem PHP nativo nada roda sozinho (subir um container a cada tecla
seria lento demais). `Esp p l` (ou `:PhpLint`) roda os dois só no
arquivo atual, pelo container (`docker compose run --rm php`), e põe
os erros na margem do mesmo jeito.

## 8. Snippets do framework

Digite o prefixo num `.php` ou `.phtml` e aceite na lista do
autocompletar; `Tab` pula para o próximo campo. Todos começam com
`fw`; digite `fw` para ver a lista.

| Prefixo                  | Gera                                     |
|--------------------------|------------------------------------------|
| `fwcontroller`           | controller com as 7 ações RESTful        |
| `fwindex`, `fwnew`,      | uma ação RESTful                         |
| `fwcreate`, `fwshow`,    |                                          |
| `fwedit`, `fwupdate`,    |                                          |
| `fwdestroy`              |                                          |
| `fwmodel`                | model com `@property`, `$table`,         |
|                          | `$columns` e `validates()`               |
| `fwvalidates`            | `validates()` com `Validations::*`       |
| `fwvalida`               | uma regra `Validations::*`               |
| `fwbelongsto`            | associação `belongsTo`                   |
| `fwrota`                 | `Route::get(...)->name(...)`             |
| `fwrotas`                | as 8 rotas RESTful do recurso            |
| `fwteste`                | teste de controller (login de admin)     |
| `fwtget`, `fwtpost`      | método de teste com `get()` / `post()`   |
| `fwtesteunit`            | teste unitário de model                  |
| `fwcest`, `fwcenario`    | Cest de aceitação; um cenário            |
| `fwinvalid`              | `$invalid`/`$feedback` do `_form.phtml`  |
| `fwcampo`, `fwselect`    | campo e select com `$invalid`/`$feedback` |
| `fwform`                 | view `new`/`edit` que inclui o `_form`   |

## 9. Fluxo de um cenário do FVP

Exemplo com o A2 do `fvp/4.1/catalogo.md` (logout via
DELETE). Serve de molde para os outros.

1. Abra o editor na raiz do projeto:

   ```bash
   cd ~/eventos-do-campus && nvim
   ```

2. Leia o cenário sem sair: `:Fvp -s A2` (`q` fecha).

3. Ache a rota: `Esp j r` abre o `routes.php`; `/logout`, `Enter`.

4. Troque a linha: `0`, `cc`, digite o código novo, `Esc`, `:w`.

5. Abra a view: `Esp Esp`, `_navbar`, `Enter`. Ache o link: `/Sair`,
   `Enter`.

6. Substitua o bloco: `V`, `j` até o `</li>`, `c`, digite o
   formulário, `Esc`. Indentação torta: `V`, `j` sobre o bloco, `=`.
   `:w`.

7. Confira o controller: volte ao `routes.php` (`Esp j r`), cursor na
   rota do logout, `Esp m r`. Veja se o método faz o que o cenário
   espera. `Ctrl+o` volta.

8. Erros do LSP: `Esp x x`. Classe não importada: `Esp c a`, escolha
   o `use`.

9. Teste: `Esp t i` (Integration). Depois `Esp t b` (navegador).

10. Lint: `Esp p c` (phpcs) e `Esp p s` (phpstan). Corrija com
    `Esp p f` (phpcbf) ou à mão com `]d`.

11. Commit em inglês: `git add -A && git commit -m "feat: Logout via
    DELETE"`.

## 10. Problemas comuns

- **Digitei e nada aconteceu, ou aconteceu outra coisa.** Estava em
  modo normal. `u` desfaz, `i` entra em inserção.
- **Não consigo sair.** `Esc` duas vezes, `:q!`.
- **Apareceu `-- INSERT --` e não sai.** `Esc`.
- **Tela presa com `:`.** `Esc` ou `Ctrl+c`.
- **Sem autocompletar nem `gd`.** `:LspInfo`. Se o intelephense não
  aparecer: `:LspRestart`. Se não existir: `:MasonInstall
  intelephense`.
- **Sem cores no PHP.** `:TSInstall php`.
- **Primeira abertura lenta.** Normal: baixa plugins e parsers. Espere
  terminar antes de usar.
- **Erro `vendor/` não encontrado nos diagnósticos.** Rode `./run
  composer install` uma vez; o intelephense lê o `vendor/` para
  resolver classes.
- **`Esp t ...` diz "fora de um projeto do framework".** O arquivo
  aberto não está dentro do projeto e o editor não foi aberto na raiz.
  `:cd ~/eventos-do-campus`.
- **Testes falham com "no such service" ou conexão recusada.** A stack
  não está de pé: `Esp r u`.
- **`Esp p f` mudou muitos arquivos.** O phpcbf corrige o projeto
  todo; confira com `git diff` antes do commit.
- **Mouse.** Funciona para clicar e rolar, inclusive no explorador.
  Não substitui o teclado para editar.

## 11. Aprender mais

- `:Tutor`: tutorial interativo de 30 minutos, dentro do editor.
- `Esp` e esperar: todos os atalhos, agrupados.
- `:help palavra`: documentação de qualquer comando.
- https://www.lazyvim.org/keymaps : tabela completa dos atalhos.
- A configuração está em `scripts/files/nvim/` deste repositório
  (copiada para `~/.config/nvim`); cada atalho tem um comentário em
  `lua/config/keymaps.lua`.
