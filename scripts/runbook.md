# Runbook: ordem de execução dos scripts

Máquina Ubuntu 24.04 do laboratório, usuário comum com sudo.
Detalhes de cada script: `scripts/README.md` ou `<script> -h`.

## Instalar

```
sudo apt install -y git               # só se `git --version` falhar
git clone <este repositório> ~/s50
cd ~/s50
```

Tudo de uma vez (01 a 10; logout e login no fim). O 08 clona
`https://github.com/VitorThorvi/tsi34d-backend`; outro repositório:
`REPO_URL=<url>` na frente.

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
scripts/08-install-eventos-do-campus.sh  # FULL=1 na frente: suíte inteira
scripts/14-fvp-prep.sh              # soluções FVP como branches; fvp
scripts/09-install-php.sh
scripts/10-install-neovim.sh
```

Opcionais, fora do 00: `scripts/11-install-vscode.sh` (VS Code +
Intelephense), `scripts/12-install-intellij.sh` (IntelliJ Community,
sem PHP) e, só se precisar de Docker rootless por outro motivo (o
framework não roda nele), `scripts/13-setup-docker-rootless.sh`.

## Dia da FVP

```
cd ~/eventos-do-campus && git switch main && git pull
~/s50/scripts/15-fvp-check.sh       # o que aplica no main de hoje
fvp -c 4.2                          # cenários por chance; fvp <termo>
fvp -s P1                           # o pedido inteiro e as armadilhas
git switch -c <branch-pedida>       # nome que o professor pedir
fvp-aplicar --dry-run P1            # aplica sem conflito?
fvp-aplicar P1                      # aplica, phpcs, phpstan e testes
git log --oneline -3; git push -u origin <branch-pedida>
```

Em conflito: `fvp -s <ID>` e a seção "Se der conflito" do README do
exemplo (`~/s50/fvp/4.2/exemplos/<id>-.../README.md`).

## Desfazer (ordem inversa)

Se instalou com o 00: `scripts/00-run-all.sh -z` (desfaz, na ordem
inversa, só o que ele rodou). Senão, um por um:

```
cd ~/s50
scripts/10-install-neovim.sh -z
scripts/09-install-php.sh -z
scripts/14-fvp-prep.sh -z
scripts/08-install-eventos-do-campus.sh -z
scripts/07-test-framework.sh -z     # só se uma execução foi interrompida
scripts/13-setup-docker-rootless.sh -z   # só se rodou
scripts/12-install-intellij.sh -z        # só se rodou
scripts/11-install-vscode.sh -z          # só se rodou
scripts/06-install-docker.sh -z
scripts/04-set-zsh-default.sh -z
scripts/03-install-ohmyzsh.sh -z
scripts/02-install-tmux-conf.sh -z
scripts/01-install-tools.sh -z
```
