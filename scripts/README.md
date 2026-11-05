# Scripts de setup da máquina (Ubuntu 24.04)

Preparam uma máquina do laboratório para a disciplina: ferramentas de
terminal, Docker e o framework. Um script por tarefa, nomes em
inglês, mensagens em português. Nenhum script guarda segredo.
Só a ordem de execução, sem explicações: `scripts/runbook.md`.

Todos aceitam só duas opções:

- `-h`  mostra a ajuda e sai.
- `-z`  desfaz o que **este** script fez. Cada script anota o que
  fez em `~/.local/state/s50-setup/<script>.state`; o `-z` lê isso e
  reverte só o registrado. O que já existia na máquina (um docker já
  instalado, um `.zshrc` próprio, um pacote já presente) não é tocado.
  Pacotes removidos incluem as dependências que o apt puxou junto;
  antes de remover, o script simula o purge e mantém qualquer pacote
  cuja remoção arrastaria algo de fora da lista. Sem arquivo de
  estado, o `-z` diz que não há nada a desfazer.

Rodar de novo um script que já concluiu não faz nada (ele avisa).
Se uma execução foi interrompida, rodar de novo continua de onde
parou.

## Ordem sugerida

A imagem minimal do Ubuntu 24.04 não traz git. Se `git --version`
falhar, antes de tudo: `sudo apt install -y git` (ou baixe o zip do
repositório pelo GitHub e descompacte em `~/s50`).

```
git clone <este repositório> ~/s50 && cd ~/s50
scripts/01-install-tools.sh              # git, curl, tmux, zsh, micro, ncdu
scripts/02-install-tmux-conf.sh          # ~/.tmux.conf (scripts/files/)
scripts/03-install-ohmyzsh.sh            # oh-my-zsh + plugin tmux
scripts/04-set-zsh-default.sh            # zsh em todo terminal
scripts/05-check-requirements.sh         # o que falta para o framework
scripts/06-install-docker.sh             # Docker CE + compose v2 (se faltar)
#   -> logout/login (grupo docker)
scripts/05-check-requirements.sh         # deve ficar sem FALHA
scripts/07-test-framework.sh             # prova que o framework roda aqui
scripts/08-install-eventos-do-campus.sh  # nosso projeto em localhost
scripts/14-fvp-prep.sh                   # soluções FVP como branches
scripts/09-install-php.sh                # PHP nativo + phpcs/phpstan
scripts/10-install-neovim.sh             # Neovim + LazyVim + PHP
```

Tudo de uma vez: `scripts/00-run-all.sh` roda do 01 ao 10 e o 14
(logo depois do 08), sem os opcionais 11 a 13 e sem o 15. Depois do
06 ele roda o 05, o 07 e o 08 dentro de `sg docker`, então não
precisa relogar no meio;
faça logout/login no fim. Se um script falhar, ele para; rodar de
novo continua de onde parou. O `-z` dele desfaz, na ordem inversa,
só o que ele rodou.

Para desfazer, a ordem inversa (`04-set-zsh-default.sh -z` antes de
`01-install-tools.sh -z`, por exemplo; os scripts avisam se a ordem
estiver errada).

## Scripts

| Script                            | Faz                               | sudo |
|-----------------------------------|-----------------------------------|------|
| `00-run-all.sh`                   | roda 01 a 10 e 14 em ordem        | sim  |
| `01-install-tools.sh`             | apt: git, curl, tmux, zsh, micro  | sim  |
| `02-install-tmux-conf.sh`         | `~/.tmux.conf` de `files/`        | não  |
| `03-install-ohmyzsh.sh`           | oh-my-zsh + plugin tmux, `.zshrc` | não  |
| `04-set-zsh-default.sh`           | chsh p/ zsh ou bloco no `.bashrc` | não  |
| `05-check-requirements.sh`        | só verifica; sem `-z`             | não  |
| `06-install-docker.sh`            | Docker CE + compose v2 + grupo    | sim  |
| `07-test-framework.sh`            | template em /tmp: sobe e testa    | não  |
| `08-install-eventos-do-campus.sh` | clona e sobe o projeto da equipe  | não* |
| `09-install-php.sh`               | PHP nativo, composer, phpcs/stan  | sim  |
| `10-install-neovim.sh`            | Neovim + LazyVim + PHP (LSP)      | sim  |
| `11-install-vscode.sh`            | opcional: VS Code + Intelephense  | sim  |
| `12-install-intellij.sh`          | opcional: IntelliJ Community snap | sim  |
| `13-setup-docker-rootless.sh`     | opcional: daemon rootless         | sim  |
| `14-fvp-prep.sh`                  | soluções FVP como branches; `fvp` | não  |
| `15-fvp-check.sh`                 | só verifica: o que aplica no main | não  |

`*` usa sudo, se houver, só para o dono de `public/assets/uploads`.

### 00-run-all.sh

Roda 01, 02, 03, 04, 05, 06, 05 de novo, 07, 08, 14, 09 e 10, nessa
ordem, cada um com as próprias regras (sudo, senha do chsh, recusa se
já concluiu). Não roda os opcionais 11, 12 e 13. Um script que já
estava concluído antes é pulado e fica fora do `-z` deste. O primeiro
05 só informa (acusa FALHA se o docker ainda não existe); o segundo
tem de ficar sem FALHA, senão ele para. Depois do 06 a sessão ainda
não está no grupo docker: o 05, o 07 e o 08 rodam via `sg docker`, e
o logout/login fica para o fim. O `-z` chama o `-z` de cada script
que ele rodou, na ordem inversa, e lista os que já estavam feitos
antes (esses ficam).

### 01-install-tools.sh

Instala com apt o que falta entre git, curl, tmux, zsh, micro e
ncdu (git e curl porque os outros scripts dependem deles). O `-z`
remove só os pacotes que o script instalou (e as dependências que
vieram junto) e se recusa a remover o zsh enquanto ele for o shell
padrão.

### 02-install-tmux-conf.sh

Copia `scripts/files/tmux.conf` (mouse ligado; prefixo+a liga e
desliga o `synchronize-panes`) para `~/.tmux.conf`. Um `.tmux.conf`
que já existia é guardado e volta no `-z`. Não usa plugins externos
nem baixa nada.

### 03-install-ohmyzsh.sh

Instalador oficial em modo `--unattended` (não troca o shell, não
abre o zsh). O `.zshrc` anterior, se havia, é guardado e volta no
`-z`. O plugin tmux entra num bloco marcado logo antes de
`source $ZSH/oh-my-zsh.sh`, então funciona mesmo com `plugins=(...)`
em várias linhas. Se o oh-my-zsh já existia, só o bloco é adicionado
(e só ele é removido no `-z`).

### 04-set-zsh-default.sh

Tenta `chsh -s $(command -v zsh)`; o chsh pede a senha do usuário no
terminal. Em conta de rede (ou usuário sem senha) o chsh falha; aí
entra um bloco no `~/.bashrc` que faz `exec zsh -l` em terminal
interativo (não dispara em `bash -c`, nem sem tty). O `-z` volta o
shell anterior (pede a senha de novo) ou remove o bloco.

### 05-check-requirements.sh

Confere, sem alterar nada: Ubuntu 24.04, memória, disco, sudo; git,
curl, bash; docker (cliente, daemon sem sudo, compose v2, modo
rootful/rootless); se o Docker rootless é viável (uidmap, subuid,
systemd de usuário, user namespaces, AppArmor do 24.04, porta 80,
cgroup v2); portas 80, 8080, 4444 e 5900 (ocupada é AVISO, com o
container ou processo dono: o 07 e o 08 liberam); acesso a GitHub,
Docker Hub e ao repositório apt da Docker. Sai com 0 se não houver
FALHA. O que impede só o rootless conta como AVISO quando o docker
rootful já funciona, porque o framework não usa o rootless.

### 06-install-docker.sh

Sem docker na máquina: adiciona o repositório apt da Docker e instala
docker-ce, cli, containerd.io, buildx, compose plugin e
rootless-extras. Com docker já presente: não mexe nele, só garante o
`docker compose` v2 (plugin certo para Docker CE ou docker.io). Nos
dois casos põe o usuário no grupo docker; é preciso logout/login.

O `-z` tira do grupo, remove só os pacotes e arquivos de repositório
que o script criou e, se o docker não existia antes, pergunta se
apaga `/var/lib/docker` e `/var/lib/containerd`. O grupo docker sai
junto se não existia antes e ficou vazio. Um docker que já estava
instalado nunca é removido.

### 07-test-framework.sh

Clona `SI-DABE/tsi34d-framework-template` sem alterações em
`/tmp/tsi34d-framework.XXXXXX`, roda `cp .env.example .env`,
`./run composer install`, `./run up -d --build`, espera o MySQL, `./run
db:reset`, `./run db:populate`, faz GET em `http://localhost` e roda
`./run test tests/Unit`. No fim derruba containers, volumes, as
imagens que não existiam antes e o diretório. Imprime um resumo por
etapa com tempo. O `-z` limpa os restos de uma execução interrompida.

Portas: antes do `up`, lê do `docker compose config` as portas que o
compose publica no host (hoje 80, 8080, 4444 e 5900) e libera cada
uma que estiver em uso. Se o dono é um container, `docker stop` nele
(outro compose de pé, por exemplo); se é um processo, mostra nome e
PID, manda SIGTERM, espera 3 s e manda SIGKILL se ele não sair (com
sudo quando o processo é de outro usuário, como um servidor web do
sistema). Sem permissão para encerrar, para com a mensagem de como
fazer à mão. Cada porta liberada aparece na saída. O mesmo vale para
o 08, que também anota o que parou (`freed_ports` no estado).

### 08-install-eventos-do-campus.sh

Clona `https://github.com/VitorThorvi/tsi34d-backend` (outro repositório:
`REPO_URL=<url>`) em
`~/eventos-do-campus` (ou `EVENTOS_DIR=/outro/lugar`), sobe tudo como
o 07-test-framework.sh, roda `./run codecept build` e `./run test
tests/Unit` (tem de passar) e ajusta o dono de
`public/assets/uploads` se houver sudo. Para se o diretório já
existir. Libera as portas ocupadas como o 07 (veja acima). Mostra URL e
credenciais do populate
(`admin@example.com` / `user@example.com`, senha `123456`). O `-z`
derruba os containers, apaga volumes, imagens que não existiam antes
e o diretório.

Com `FULL=1` na frente, roda também `./run test tests/Integration` e
`./run test:browser` (o selenium já sobe com o `./run up -d`); leva
cerca de 1 minuto a mais. No fim, além dos comandos do projeto, lista
os próximos passos da FVP (14, `fvp`, 15 e `fvp-aplicar`).

### 09-install-php.sh

PHP fora do Docker, para lint, LSP e linha de comando. Instala com
apt o que falta entre php-cli, php-mysql, php-zip, php-xml,
php-mbstring, php-curl (as extensões que o projeto usa), unzip e
composer. Com o composer, instala em `~/.config/composer` o phpcs e
phpcbf (squizlabs/php_codesniffer) e o phpstan, os dois do
require-dev do projeto, e põe `~/.config/composer/vendor/bin` no
PATH com um bloco em `~/.bashrc` e `~/.zshrc`. Testa `php -l`,
`phpcs --version` e `phpstan --version`. O `-z` remove só as
ferramentas que adicionou (ou a pasta toda, se não existia), o
cache, o bloco e os pacotes que instalou.

### 10-install-neovim.sh

Neovim para o terminal, com LazyVim e a configuração da disciplina.
Precisa de php no PATH (o 09). Instala com apt o que falta entre git,
curl, build-essential, unzip, ripgrep, fd-find, nodejs, npm, xclip e
wl-clipboard (clipboard do sistema no X11 e no Wayland). O apt do
24.04 traz o Neovim 0.9, velho demais para o LazyVim; o script baixa
o AppImage mais recente, extrai em `~/.local/share/nvim-appimage` (sem
depender de FUSE) e linka em `~/.local/bin/nvim`, com um bloco de PATH
em `~/.bashrc` e `~/.zshrc`. Um nvim >= 0.10 já presente é usado no
lugar.

Copia `scripts/files/nvim/` para `~/.config/nvim` (um
`~/.config/nvim` que já existia é guardado e volta no `-z`) e grava
em `~/.config/nvim/s50-repo` o caminho deste repositório, para o
`Esp ?` abrir o `neovim-dicas.md` e o `:Fvp` achar o `fvp.sh`. A
configuração, sobre o LazyVim com o extra de PHP: intelephense (o
phpactor falhou em definição e autocompletar) com a raiz do projeto
(pasta com `composer.json` e `./run`); `.phtml` como PHP com HTML
injetado; `.env`, compose, `phpunit.xml`, `codeception.yml` e
`phpstan.neon` reconhecidos; `vendor/` fora das buscas e indexado pelo
intelephense; 4 espaços em PHP (PSR-12; o `.editorconfig` do projeto
diz 2, ignorado só na indentação de `.php` e `.phtml`) e 2 no resto;
guia nas colunas 131 (PHP, limite 130 do `phpcs.xml`) e 81
(Markdown); navegação controller, view, teste, rota e model
(`Esp m`), busca em `app/`, `core/`, `lib/`, `tests/` (`Esp j`);
`./run` em terminal flutuante (`Esp t`, `Esp p`, `Esp r`, `:Run`);
phpcs e phpstan como diagnósticos (nativos, automáticos; pelo
container só com `Esp p l`); phpcbf ao salvar; snippets `fw*` do
framework. Detalhes em `neovim-dicas.md`.

Em headless, instala os plugins nos commits do `lazy-lock.json`
(`:Lazy! restore`), o intelephense e o tree-sitter-cli pelo mason e
compila os parsers treesitter (php, html, sql e os do LazyVim), para
a primeira abertura não esperar. O `-z` remove nvim, configuração,
`~/.local/share/nvim`, `~/.local/state/nvim`, `~/.cache/nvim` e
`~/.npm` (só os que não existiam antes) e os pacotes que instalou.

### 11-install-vscode.sh

Opcional, interface gráfica, fora do 00. Se não houver `code`,
instala o VS Code pelo repositório apt da Microsoft (keyring em
`/etc/apt/keyrings/packages.microsoft.gpg`, source em
`/etc/apt/sources.list.d/vscode.list`, pacote `code`), tudo
rastreado. Instala a extensão `bmewburn.vscode-intelephense-client`
pela linha de comando (não abre janela) e, em
`~/.config/Code/User/settings.json`, desliga o PHP embutido do VS
Code (`php.suggest.basic` e `php.validate.enable` = false) só se as
chaves não existirem. Precisa de php no PATH (o 09). Um VS Code que
já existia não é tocado: só a extensão entra, e o `-z` remove só ela
(se não existia antes, apagando a pasta e o VSIX em cache, que o
`code --uninstall-extension` deixa), as chaves que inseriu, as pastas
de usuário que a CLI do code criou por causa dele e, quando o script
instalou o `code`, o pacote, o repositório e o keyring. Num VS Code
que já existia, ficam só logs e caches do próprio code.

### 12-install-intellij.sh

Opcional, interface gráfica, fora do 00. Instala o IntelliJ IDEA
Community com `snap install intellij-idea-community --classic` (e o
snapd, se faltar, rastreado). Se já houver `idea`, o snap, ou o
JetBrains Toolbox em `~/.local/share/JetBrains/Toolbox`, não instala
nada e o `-z` não faz nada. A edição Community não tem o plugin de
PHP (só PhpStorm ou Ultimate): serve para Java/Kotlin; para PHP use
o 11 ou o 10. Anota os snaps que já existiam; o `-z` remove o snap
do IntelliJ e os que vieram junto com ele (`core22`, `snapd`...), na
ordem que o snapd aceita, e o pacote snapd, tudo só se o script
instalou.

### 13-setup-docker-rootless.sh

Opcional. Instala uidmap, dbus-user-session e rootless-extras se
faltarem, cria faixa de subuid/subgid se o usuário não tiver, cria o
perfil AppArmor do rootlesskit quando o Ubuntu 24.04 exige e ele não
veio no pacote, roda `dockerd-rootless-setuptool.sh install`, libera
a porta 80 para processos sem root (`net.ipv4.ip_unprivileged_port_start=0`,
porque o compose do framework publica `80:80`), ativa linger e
exporta `DOCKER_HOST` no `~/.bashrc` e `~/.zshrc`. O `-z` desfaz cada
um desses itens, só os que o script fez.

Atenção (testado na VM): com o Docker rootless, `./run composer
install` do framework falha (`/app/vendor could not be created`),
porque o `./run` usa `docker run -u $(id -u)` e em rootless esse uid
não é o dono do bind mount. O framework, sem modificação, só roda no
Docker rootful (usuário no grupo docker). Este script existe para
quem precisar do rootless por outro motivo; `05-check-requirements.sh`
avisa quando o rootless está ativo.

### 14-fvp-prep.sh

Prepara a FVP sem rede. Importa cada
`fvp/<escopo>/exemplos/<cenario>/solucao.bundle` como o branch
`fvp/<escopo>/<cenario>` no clone do projeto (o que o 08 criou; outro:
`EVENTOS_DIR=/caminho`) e põe no `~/.zshrc` e no `~/.bashrc` os
atalhos `fvp` (o `fvp.sh` da raiz) e `fvp-aplicar` (o
`fvp/aplicar.sh`). Não troca o branch atual nem mexe na árvore. Um
branch que já existia com o mesmo nome fica como está e fora do
`-z`. O `-z` apaga só os branches que ele criou (menos o branch
atual, com aviso) e tira os atalhos. Para ver a solução de um
cenário: `git log --oneline -p main..fvp/4.2/p1-codigo-checkin-aleatorio`.

### 15-fvp-check.sh

Só lê. Para cada cenário de chance alta ou média-alta do escopo 4.2
(`fvp.sh -c 4.2`), simula o cherry-pick da solução contra o `main`
ATUAL do clone do projeto e diz se aplica, conflita (com os
arquivos), já está aplicado ou é só de README. Usa
`fvp/aplicar.sh --dry-run`, que simula com `git merge-tree` (git 2.38
ou mais novo; o Ubuntu 24.04 traz o 2.43): não toca branch, árvore
nem índice. Outro escopo ou ref: `FVP_ESCOPO=4.1`, `FVP_BASE=<ref>`.
O `-z` não tem o que desfazer.

### fvp/aplicar.sh (fora de scripts/)

Aplica, no branch atual do clone do projeto, a solução pronta de um
cenário. Rode de dentro do clone: `fvp-aplicar P1` (ou
`~/s50/fvp/aplicar.sh P1`).

1. acha o exemplo do ID (`fvp/<escopo>/exemplos/<id>-...`; `--escopo`
   se precisar escolher);
2. lê o `solucao.bundle` (offline) e lista os commits do cenário,
   marcando os que o branch já tem (mesmo patch, como a base de um
   cenário empilhado), que são pulados;
3. faz o cherry-pick dos que faltam; em conflito, desfaz tudo
   (`--abort`), lista os arquivos e aponta a seção "Se der conflito"
   do README do cenário;
4. roda `./run phpcs`, `./run phpstan` e os comandos `./run` do bloco
   "Como verificar" do README do cenário (testes, `db:populate`,
   selenium), e mostra um resumo OK/FALHA.

`--dry-run` só diz se aplica (não muda nada; `--base <ref>` testa
contra outro ref); `--sem-testes` aplica e pula o passo 4. Precisa de
`git config user.name` e `user.email` e de árvore sem alterações não
commitadas. Para desfazer uma aplicação, ele imprime o
`git reset --hard <sha>` certo.

## Como foram testados

VM Ubuntu 24.04.5 minimal (clone limpo, 4 vCPU, 6 GB), usuário comum
com sudo, sem docker, sem git. Cada script foi rodado, rodado de novo
(recusa), desfeito com `-z` e desfeito de novo (nada a desfazer).
Depois do `-z` de todos, a lista de pacotes (`dpkg -l`) e o `$HOME`
voltaram iguais ao snapshot inicial. Também em container Ubuntu
24.04: pacote já instalado, `.tmux.conf` e oh-my-zsh já existentes
ficam intactos no `-z`. O `chsh` foi testado com senha (pty) e sem
senha (fallback no `.bashrc`). `07-test-framework.sh` passou 9/9 na VM
(rootful) e no macOS; em rootless falha no `composer install`, como
documentado. `08-install-eventos-do-campus.sh` passou 10/10 e o `-z`
não deixou containers, imagens nem pasta. Com o `--build` e o `./run
test tests/Unit`, o 08 foi refeito contra o main da Entrega 4.2
(`f31006b`) em container Ubuntu 24.04 usando o docker do host: 11/11
etapas em 32 s, 159 testes verdes, fuso `America/Sao_Paulo` nos
containers php e php_test, HTTP 200 em `/` e `/login`; o `-z`
removeu containers, imagens, volume, rede e pasta em 6 s.

`00-run-all.sh` passou em outra VM limpa: 01 a 08 em 3m45s sem
relogar (o segundo 05 ficou sem FALHA via `sg docker`), 5 containers
de pé, HTTP 200 em `/` e `/login`; rodar de novo recusa; o `-z`
desfez tudo em 24 s e dpkg, `$HOME`, shell e grupo docker voltaram
iguais ao snapshot inicial.

Os scripts 09 a 12, e o 00 com 01 a 10, passaram em outra VM limpa
(x86_64): 00 em 9m41s (PHP 8.3 com phpcs 4.0.4 e phpstan 2.3.1;
Neovim 0.12.6 com 32 plugins, intelephense e parser de PHP); 11 em
48 s e 12 em 1m49s, cada um desfeito com `-z` sem resto; `00 -z` em
1m39s devolveu dpkg, `$HOME`, shell e grupo docker ao snapshot. Em
container Ubuntu 24.04 (arm64): 09, 10 e 11 com retomada simulada e
com nvim 0.9, `~/.config/nvim`, dados do nvim, `~/.npm`,
`~/.config/composer` e VS Code já existentes; o `-z` devolve o que
era do usuário e mantém, avisando, as pastas que já existiam.

Os scripts da FVP (14, 15, `fvp/aplicar.sh` e o 08 com `FULL=1`)
passaram numa VM Ubuntu 24.04 (git 2.43) com o projeto em `f31006b`:
o 14 importou os 154 bundles em 11 s; o 15 listou os 29 cenários
(28 aplicam, T2 só de README) em 2 s e, contra um branch com o N1,
apontou os 6 que conflitam (N3, N4, N10, O5, P16, Q1) com os arquivos;
`fvp-aplicar P1` aplicou e passou phpcs, phpstan, Unit, Integration e
navegador; `fvp-aplicar N2` sobre o N1 conflitou e voltou o HEAD; o
`-z` do 14 apagou os 154 branches e devolveu o `.bashrc` byte a byte.
`FULL=1` no 08: 13/13 etapas (Integration 16 s, navegador 42 s).

A liberação de portas do 07 e do 08 foi testada na mesma VM: com a
porta 80 presa por um `python3 -m http.server` do root, a 8080 por um
do usuário e a 4444 por um container nginx, o 08 encerrou os dois
processos (o do root via sudo), parou o container e seguiu até o fim
(12/12); com o compose do projeto de pé, o 07 parou os containers web
e selenium dele e passou.
