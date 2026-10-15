# Runbook: ordem de execução dos scripts

Máquina Ubuntu 24.04 do laboratório, usuário comum com sudo.
Detalhes de cada script: `scripts/README.md` ou `<script> -h`.

## Instalar

```
sudo apt install -y git               # só se `git --version` falhar
git clone <este repositório> ~/s50
cd ~/s50

scripts/install-tools.sh              # 1
scripts/install-tmux-conf.sh          # 2
scripts/install-ohmyzsh.sh            # 3
scripts/set-zsh-default.sh            # 4
scripts/check-requirements.sh         # 5
scripts/install-docker.sh             # 6 (pule se o check não acusou falta)
```

Logout e login (grupo docker e shell novo). Depois:

```
cd ~/s50
scripts/check-requirements.sh         # 7  deve terminar com FALHA: 0
scripts/test-framework.sh             # 8
scripts/install-eventos-do-campus.sh  # 9
```

Opcional, só se precisar de Docker rootless por outro motivo (o
framework não roda nele): `scripts/setup-docker-rootless.sh`.

## Desfazer (ordem inversa)

```
cd ~/s50
scripts/install-eventos-do-campus.sh -z
scripts/test-framework.sh -z          # só se uma execução foi interrompida
scripts/setup-docker-rootless.sh -z   # só se rodou
scripts/install-docker.sh -z
scripts/set-zsh-default.sh -z
scripts/install-ohmyzsh.sh -z
scripts/install-tmux-conf.sh -z
scripts/install-tools.sh -z
```
