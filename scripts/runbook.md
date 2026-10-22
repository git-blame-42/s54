# Runbook: ordem de execução dos scripts

Máquina Ubuntu 24.04 do laboratório, usuário comum com sudo.
Detalhes de cada script: `scripts/README.md` ou `<script> -h`.

## Instalar

```
sudo apt install -y git               # só se `git --version` falhar
git clone <este repositório> ~/s50
cd ~/s50
```

Tudo de uma vez (01 a 08, sem o 09; logout e login no fim):

```
scripts/00-run-all.sh
```

Um por um:

```
scripts/01-install-tools.sh
scripts/02-install-tmux-conf.sh
scripts/03-install-ohmyzsh.sh
scripts/04-set-zsh-default.sh
scripts/05-check-requirements.sh
scripts/06-install-docker.sh        # pule se o check não acusou falta
```

Logout e login (grupo docker e shell novo). Depois:

```
cd ~/s50
scripts/05-check-requirements.sh    # de novo: deve terminar com FALHA: 0
scripts/07-test-framework.sh
scripts/08-install-eventos-do-campus.sh
```

Opcional, só se precisar de Docker rootless por outro motivo (o
framework não roda nele): `scripts/09-setup-docker-rootless.sh`.

## Desfazer (ordem inversa)

Se instalou com o 00: `scripts/00-run-all.sh -z` (desfaz, na ordem
inversa, só o que ele rodou). Senão, um por um:

```
cd ~/s50
scripts/08-install-eventos-do-campus.sh -z
scripts/07-test-framework.sh -z     # só se uma execução foi interrompida
scripts/09-setup-docker-rootless.sh -z   # só se rodou
scripts/06-install-docker.sh -z
scripts/04-set-zsh-default.sh -z
scripts/03-install-ohmyzsh.sh -z
scripts/02-install-tmux-conf.sh -z
scripts/01-install-tools.sh -z
```
