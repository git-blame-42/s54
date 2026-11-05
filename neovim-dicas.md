# Neovim com LazyVim: dicas para o FVP

Para quem vem do IntelliJ e vai editar o projeto no terminal do
laboratório. Pressupõe o `scripts/10-install-neovim.sh` rodado (Neovim,
LazyVim, intelephense, parsers de PHP). Testado em Ubuntu 24.04.

Depois do script, abra um terminal novo (ou saia e entre de novo): o
`nvim` fica em `~/.local/bin`, que só entra no PATH na próxima sessão
do shell.

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

Colar do sistema: `"+p`. Copiar para o sistema: seleciona e `"+y`.
Em SSH sem clipboard, use o botão do meio do terminal.

## 4. Atalhos de IDE (LazyVim)

A tecla líder é o espaço (`Esp`). Aperte `Esp` e espere um segundo: o
menu mostra tudo. Tudo em modo normal.

Arquivos e busca:

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
- `Esp c f`: formata o arquivo.
- `]d` / `[d`: próximo / anterior erro ou aviso.
- `Esp x x`: lista todos os erros do projeto.
- Autocompletar aparece sozinho ao digitar. `Enter` ou `Ctrl+y`
  aceita, `Ctrl+n` / `Ctrl+p` navega, `Ctrl+e` fecha.

Terminal e git:

- `Ctrl+/`: abre e fecha um terminal dentro do editor. Rode `./run
  test`, `./run phpcs`, `git status` sem sair.
- `:!comando`: roda um comando e mostra a saída.
- `Esp g g`: lazygit, se instalado. `Esp g d`: diff do arquivo.
- As margens mostram linhas adicionadas, alteradas e removidas.

## 5. Fluxo de um cenário do FVP

Exemplo com o A2 do `fvp-possiveis-modificacoes.md` (logout via
DELETE). Serve de molde para os outros.

1. Abra o editor na raiz do projeto:

   ```bash
   cd ~/eventos-do-campus && nvim
   ```

2. Leia o cenário sem sair: `Ctrl+/`, `~/s50/fvp.sh -s A2`, `Ctrl+/`.

3. Ache a rota: `Esp /`, digite `logout`, `Enter` no resultado em
   `routes.php`.

4. Troque a linha: `0`, `cc`, digite o código novo, `Esc`, `:w`.

5. Abra a view: `Esp Esp`, `_navbar`, `Enter`. Ache o link: `/Sair`,
   `Enter`.

6. Substitua o bloco: `V`, `j` até o `</li>`, `c`, digite o
   formulário, `Esc`. Indentação torta: `V`, `j` sobre o bloco, `=`.
   `:w`.

7. Confira o controller: cursor sobre `destroy` na rota, `gd`. Veja
   se o método faz o que o cenário espera. `Ctrl+o` volta.

8. Erros do LSP: `Esp x x`. Classe não importada: `Esp c a`, escolha
   o `use`.

9. Teste: `Ctrl+/`, `./run test tests/Integration`, `Ctrl+/`.

10. Lint: `./run phpcs` no terminal embutido. Corrija com `./run
    phpcbf` ou à mão com `]d`.

11. Commit em inglês: `git add -A && git commit -m "feat: Logout via
    DELETE"`.

## 6. Problemas comuns

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
- **Mouse.** Funciona para clicar e rolar, inclusive no explorador.
  Não substitui o teclado para editar.

## 7. Aprender mais

- `:Tutor`: tutorial interativo de 30 minutos, dentro do editor.
- `Esp` e esperar: todos os atalhos, agrupados.
- `:help palavra`: documentação de qualquer comando.
- https://www.lazyvim.org/keymaps : tabela completa dos atalhos.
