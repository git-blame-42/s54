# Framework da disciplina TSI34D (Back-End)

Informações e tutoriais sobre o mini-framework MVC em PHP usado na
disciplina TSI34D (Tecnologia em Sistemas para Internet, UTFPR, 2026-2).

Repositório: https://github.com/git-blame-42/s54

Origem do framework: https://github.com/SI-DABE/problem-track
(aplicação de referência do professor; o diretório `core/` dela é o
framework). Os projetos da turma partem do template
https://github.com/SI-DABE/tsi34d-framework-template, que é o
problem-track sem as funcionalidades de exemplo. O `core/` dos dois é
idêntico, byte a byte, ao do projeto da equipe.

Tudo aqui foi conferido contra o código: problem-track `8130d35`,
template `d280222` e o projeto da equipe nas Entregas 4.1 (`86c81ca`)
e 4.2 (`f31006b`).
Quando o texto diz "nosso projeto", fala do projeto da equipe
(Eventos do Câmpus), não do framework.

## Arquivos

- `framework-resumo.md`
  Conceitos do framework, sem código e sem exemplos. 10 minutos.
  Para o primeiro contato e para revisar antes do vídeo ou do FVP.

- `framework-completo.md`
  Como o framework funciona por dentro, peça por peça, com código do
  projeto e do problem-track. Para quem vai programar ou explicar.

- `diretorios-do-framework.md`
  Para que serve cada diretório e arquivo da raiz: dono (framework,
  equipe, infra, gerado), o que entra, o que não entra, e um índice
  reverso "onde vai um X novo".

- `php-sintaxe-e-sobrevivencia-offline.md`
  Sintaxe PHP 8 que o projeto usa (classes, arrays, views, PSR-12,
  erros comuns) e como achar documentação e exemplos sem internet:
  `php --rf`, `vendor/`, arquivos-modelo do projeto, grep e git.

- `neovim-dicas.md`
  Neovim com LazyVim para quem vem do IntelliJ: modos, atalhos de
  IDE (buscar, ir à definição, erros, terminal embutido) e o passo
  a passo de um cenário do FVP no editor. Pressupõe o
  `scripts/10-install-neovim.sh`.

Ordem sugerida: resumo, completo, catálogo do FVP da entrega com o
projeto aberto. O de PHP é consulta: abra quando travar numa sintaxe ou
quando estiver sem rede.

## FVP

A validação prática (FVP) de cada entrega tem o seu escopo. O diretório
`fvp/` separa por entrega; os docs do framework acima valem para todas.

- `fvp/4.1/catalogo.md`
  Catálogo das alterações que o professor pode pedir na FVP da
  Entrega 4.1 (login, perfis, middlewares, sessão): arquivos, passos,
  código e testes de cada uma.

- `fvp/4.1/exemplos/`
  Uma solução pronta e testada para cada cenário do catálogo 4.1:
  cópia do projeto já modificada (sem `vendor/`), `solucao.patch` e
  `solucao.bundle` para `fetch` + `cherry-pick` com o mesmo SHA. Ver
  `fvp/4.1/exemplos/README.md`.

- `fvp/4.2/catalogo.md` e `fvp/4.2/exemplos/`
  O mesmo para a Entrega 4.2 (CRUD de Eventos: rotas RESTful,
  `where`, validações, paginação), no mesmo formato.

- `fvp/recriar.sh`
  Devolve o `.git` ao `projeto/` de um cenário a partir do bundle,
  offline: `fvp/recriar.sh fvp/4.1/exemplos/<cenario>` (caminho
  relativo ou absoluto); `FVP_REPO_URL=<url>` define o `origin`;
  `--todos` recria todos os cenários.

- `fvp.sh`
  Busca nos catálogos: `./fvp.sh` lista todos os cenários; `./fvp.sh
  4.2` só os da 4.2; `./fvp.sh 4.2 termo` filtra pelo termo (título
  vale mais) e aponta o exemplo pronto; frase entre aspas conta como
  um só termo; `./fvp.sh -s B10` mostra o cenário inteiro; `./fvp.sh
  -h` lista as opções. Só bash, awk, grep e sort; roda no Ubuntu do
  laboratório.

## Scripts

`scripts/` prepara uma máquina Ubuntu 24.04 do laboratório: git,
curl, tmux, zsh, micro, ncdu, `.tmux.conf`, oh-my-zsh com plugin
tmux, zsh como shell padrão, verificação do que o framework precisa
(Docker, Compose v2, rootless viável ou não), instalação do Docker,
teste do framework sem modificações num diretório temporário,
instalação do projeto Eventos do Câmpus, PHP nativo com phpcs e
phpstan, Neovim com LazyVim e, opcionais, VS Code e IntelliJ. Um
script por tarefa; só
`-h` e `-z` (desfaz o que aquele script fez, sem tocar no que já
existia). `scripts/00-run-all.sh` roda tudo em ordem, menos o
rootless. Ordem e detalhes em `scripts/README.md`.

## Convenções

- Texto em português. Identificadores de código, tabelas e mensagens
  de commit em inglês, como a disciplina exige.
- Caminhos relativos à raiz do projeto (`app/`, `core/`, `lib/`,
  `config/`, `tests/`).
- "Rubrica" = planilhas "Itens Avaliados" 2026-2 de cada entrega.
- Formatado para leitura em terminal (micro): linhas de 80 colunas,
  sem diagramas que dependem de renderização.
