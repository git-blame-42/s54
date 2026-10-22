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
scripts/02-install-tmux-conf.sh          # ~/.tmux.conf dos dotfiles
scripts/03-install-ohmyzsh.sh            # oh-my-zsh + plugin tmux
scripts/04-set-zsh-default.sh            # zsh em todo terminal
scripts/05-check-requirements.sh         # o que falta para o framework
scripts/06-install-docker.sh             # Docker CE + compose v2 (se faltar)
#   -> logout/login (grupo docker)
scripts/05-check-requirements.sh         # deve ficar sem FALHA
scripts/07-test-framework.sh             # prova que o framework roda aqui
scripts/08-install-eventos-do-campus.sh  # nosso projeto em localhost
```

Para desfazer, a ordem inversa (`04-set-zsh-default.sh -z` antes de
`01-install-tools.sh -z`, por exemplo; os scripts avisam se a ordem
estiver errada).

## Scripts

| Script                            | Faz                               | sudo |
|-----------------------------------|-----------------------------------|------|
| `01-install-tools.sh`             | apt: git, curl, tmux, zsh, micro  | sim  |
| `02-install-tmux-conf.sh`         | baixa `~/.tmux.conf` dos dotfiles | não  |
| `03-install-ohmyzsh.sh`           | oh-my-zsh + plugin tmux, `.zshrc` | não  |
| `04-set-zsh-default.sh`           | chsh p/ zsh ou bloco no `.bashrc` | não  |
| `05-check-requirements.sh`        | só verifica; sem `-z`             | não  |
| `06-install-docker.sh`            | Docker CE + compose v2 + grupo    | sim  |
| `07-test-framework.sh`            | template em /tmp: sobe e testa    | não  |
| `08-install-eventos-do-campus.sh` | clona e sobe o projeto da equipe  | não* |
| `09-setup-docker-rootless.sh`     | opcional: daemon rootless         | sim  |

`*` usa sudo, se houver, só para o dono de `public/assets/uploads`.

### 01-install-tools.sh

Instala com apt o que falta entre git, curl, tmux, zsh, micro e
ncdu (git e curl porque os outros scripts dependem deles). O `-z`
remove só os pacotes que o script instalou (e as dependências que
vieram junto) e se recusa a remover o zsh enquanto ele for o shell
padrão.

### 02-install-tmux-conf.sh

Baixa o `.tmux.conf` de
`github.com/VitorThorvi/zsh-dot-files` (branch main). Um `.tmux.conf`
que já existia é guardado e volta no `-z`. Não usa plugins externos.

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
cgroup v2); portas 80, 8080, 4444 e 5900 livres; acesso a GitHub,
Docker Hub e ao repositório apt da Docker. Sai com 0 se não houver
FALHA.

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
`./run composer install`, `./run up -d`, espera o MySQL, `./run
db:reset`, `./run db:populate`, faz GET em `http://localhost` e roda
`./run test tests/Unit`. No fim derruba containers, volumes, as
imagens que não existiam antes e o diretório. Imprime um resumo por
etapa com tempo. O `-z` limpa os restos de uma execução interrompida.

### 08-install-eventos-do-campus.sh

Clona `github.com/VitorThorvi/tsi34d-backend` em
`~/eventos-do-campus` (ou `EVENTOS_DIR=/outro/lugar`), sobe tudo como
o 07-test-framework.sh, roda `./run codecept build` e ajusta o dono de
`public/assets/uploads` se houver sudo. Para se o diretório já
existir. Mostra URL e credenciais do populate
(`admin@example.com` / `user@example.com`, senha `123456`). O `-z`
derruba os containers, apaga volumes, imagens que não existiam antes
e o diretório.

### 09-setup-docker-rootless.sh

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
não deixou containers, imagens nem pasta.
