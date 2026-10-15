# Framework da disciplina TSI34D (Back-End)

Informações e tutoriais sobre o mini-framework MVC em PHP usado na
disciplina TSI34D (Tecnologia em Sistemas para Internet, UTFPR, 2026-2).

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

- `php-sintaxe-e-sobrevivencia-offline.md`
  Sintaxe PHP 8 que o projeto usa (classes, arrays, views, PSR-12,
  erros comuns) e como achar documentação e exemplos sem internet:
  `php --rf`, `vendor/`, arquivos-modelo do projeto, grep e git.

Ordem sugerida: resumo, completo, catálogo do FVP com o projeto
aberto. O de PHP é consulta: abra quando travar numa sintaxe ou
quando estiver sem rede.

## Convenções

- Texto em português. Identificadores de código, tabelas e mensagens
  de commit em inglês, como a disciplina exige.
- Caminhos relativos à raiz do projeto (`app/`, `core/`, `lib/`,
  `config/`, `tests/`).
- "Rubrica" = planilhas "Itens Avaliados" 2026-2 de cada entrega.
- Formatado para leitura em terminal (micro): linhas de 80 colunas,
  sem diagramas que dependem de renderização.
