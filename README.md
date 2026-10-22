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
template `d280222` e o projeto da equipe na Entrega 4.1 (`86c81ca`).
Quando o texto diz "nosso projeto", fala do projeto da equipe
(Eventos do Câmpus), não do framework.

## Arquivos

- `framework-resumo.md`
  Conceitos do framework, sem código e sem exemplos. 10 minutos.
  Para o primeiro contato e para revisar antes do vídeo ou do FVP.

- `framework-completo.md`
  Como o framework funciona por dentro, peça por peça, com código do
  projeto e do problem-track. Para quem vai programar ou explicar.

- `fvp-possiveis-modificacoes.md`
  Catálogo das alterações que o professor pode pedir na validação
  prática (FVP): arquivos, passos, código e testes de cada uma.

- `fvp.sh`
  Busca no catálogo: `./fvp.sh senha` lista os cenários que citam o
  termo (título vale mais) e o exemplo pronto em `exemplos-fvp/`;
  `./fvp.sh -s B10` mostra o cenário inteiro; `./fvp.sh -l` lista os
  títulos; `./fvp.sh -c` ordena por chance (alta primeiro) com o tempo.
  Só bash, awk, grep e sort; roda no Ubuntu do laboratório.

- `diretorios-do-framework.md`
  Para que serve cada diretório e arquivo da raiz: dono (framework,
  equipe, infra, gerado), o que entra, o que não entra, e um índice
  reverso "onde vai um X novo".

- `php-sintaxe-e-sobrevivencia-offline.md`
  Sintaxe PHP 8 que o projeto usa (classes, arrays, views, PSR-12,
  erros comuns) e como achar documentação e exemplos sem internet:
  `php --rf`, `vendor/`, arquivos-modelo do projeto, grep e git.

- `exemplos-fvp/`
  Uma solução pronta e testada para cada cenário do catálogo: cópia
  do projeto já modificada (sem `vendor/`), `solucao.patch`,
  `solucao.bundle` para `fetch` + `cherry-pick` com o mesmo SHA, e
  `recriar.sh` para recriar o clone completo. Ver
  `exemplos-fvp/README.md`.

Ordem sugerida: resumo, completo, catálogo do FVP com o projeto
aberto. O de PHP é consulta: abra quando travar numa sintaxe ou
quando estiver sem rede.

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
