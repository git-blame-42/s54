# C7. Flash message: escape, ícone por tipo e `<?php endforeach; ?>`

Pedido, como o professor faria na hora: "Centralize a renderização das
mensagens no layout, revise o escape das mensagens e a apresentação
visual dos tipos." Ou, na versão curta: "as flash messages estão sem
formatação adequada".

Chance: média-alta. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção 9 (complemento 2, issues da
Entrega 4.1), caso C7 / issues ice-cream-manager #6 (item 4: "centralize
a renderização no layout... revise o escape das mensagens e a
apresentação visual dos tipos") e Campaign #11 (item 4: "flash messages
sem formatação adequada").

Branch no clone: `fvp/c7-flash-escape-tipos`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `94e69f5` | `fix: Escape flash messages and style them by type` |

## O que mudou

- `app/views/layouts/_flash_message.phtml` (arquivo inteiro trocado):
  - `<?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>` no lugar de
    `<?= $msg ?>`.
  - mapa `$icons` (`success` → `bi-check-circle-fill`, `danger` →
    `bi-exclamation-triangle-fill`, `warning` → `bi-exclamation-circle-fill`,
    `info` → `bi-info-circle-fill`); tipo fora do mapa usa `bi-bell`. O
    ícone fica antes do texto, dentro do `<div>` do alerta.
  - a classe continua `alert-<?= $type ?>` (cor do Bootstrap por tipo).
  - `<?php endforeach; ?>` no lugar de `<? endforeach; ?>`.
- `tests/Integration/Controllers/HomeControllerTest.php`: dois testes
  novos (escape + `alert-danger`; `alert-success` + ícone).

`lib/FlashMessage.php` não mudou: continua só com `success` e `danger`.
O mapa já tem `warning` e `info`, prontos para quando C1 criar esses tipos.

Item (1) do professor, "renderização centralizada no layout": já estava
certo na base. `app/views/layouts/application.phtml` faz
`<?php require '_flash_message.phtml'; ?>` uma vez, antes do `<h1>`, e
nenhuma view inclui a parcial (`grep -rn _flash_message app/views/` só
acha o layout). Mostre isso se perguntarem.

## Por que assim

- Escapar na saída, não na entrada: o mesmo texto pode ir para HTML,
  JSON ou log, e cada destino tem seu escape. Escapar ao gravar a
  mensagem estraga as outras saídas e abre caminho para escape duplo
  (`&amp;lt;` na tela). Por isso o escape fica na view e não em
  `FlashMessage::message()`: a lib não sabe se a saída é HTML. Se o
  professor quiser na lib, faça num lugar só e tire da view.
- Hoje as mensagens são textos fixos dos controllers, mas basta uma com
  dado do usuário ("Bem-vindo, {$user->name}", "Usuário X removido" em
  D2) para um nome `<b>Fulano</b>` ou `<script>` sair renderizado.
  `ENT_QUOTES` cobre aspas simples e duplas; `'UTF-8'` deixa acentos
  intactos.
- `$type` não precisa de escape nem de mapa de classes: a chave só é
  escrita por `FlashMessage` (`success`/`danger`, métodos fixos), nunca
  por dado do usuário. Um mapa tipo → classe com `alert-secondary` como
  padrão seria mais defensivo, mas o catálogo prefere o mínimo: o
  Bootstrap já tem `alert-success`, `-danger`, `-warning` e `-info`.
- O ícone deixa a diferença entre os tipos visível sem depender só da
  cor (daltonismo, impressão em preto e branco). `bootstrap-icons` já é
  carregado no `<head>` do layout; sem internet o ícone só não aparece,
  nada quebra.
- Tag curta `<?` vs `<?php`: `<?` só é aceita com `short_open_tag=On`.
  Funciona aqui porque a imagem `php:8.3.4-fpm` vem sem `php.ini` e o
  padrão compilado é ligado; `php.ini-production` e
  `php.ini-development` desligam. Quem copiar um deles para a imagem vê
  o layout quebrar ("unexpected end of file") em todas as páginas.
  `<?php` funciona sempre. `<?=` não depende dessa opção (desde o PHP
  5.4) e continua valendo.
- Relação com outros cenários:
  - C1 (`c1-novo-tipo-flash`) cria `FlashMessage::warning`/`info`; com
    este commit eles já saem com cor e ícone próprios, sem mexer na view.
  - C3 (`c3-trocar-textos-mensagens`) troca os textos das mensagens; o
    escape vale para qualquer texto, e os `see()` dos Cests continuam
    comparando o texto renderizado.
  - C5 (`c5-escapar-saida-xss`) cria o helper `e()` em `lib/helpers.php`
    e escapa várias views, inclusive esta parcial (`e($type)`, `e($msg)`).
    Aqui não há helper global: o cenário é só a parcial. Se os dois forem
    aplicados, veja "Se der conflito".

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
./run composer dump-autoload -o --strict-psr
grep -rn '<? ' app/views/      # não deve achar nada
```

Resultado obtido: Unit 71 testes, Integration 11, Acceptance 9 (eram
71 / 9 / 9 na base). PHPCS e PHPStan sem erros; `composer
dump-autoload -o --strict-psr` ok (sem `-o` o composer recusa o
`--strict-psr`). Schema não mudou. `grep -rn '<? ' app/views/`: na base
achava só `app/views/layouts/_flash_message.phtml:6`; depois do commit
não acha nada.

Testes novos:

- `HomeControllerTest::test_index_should_escape_flash_message`
- `HomeControllerTest::test_index_should_show_success_flash_message_with_icon`

O login errado já mostra o alerta vermelho no navegador, coberto por
`LoginCest::loginWithInvalidCredentials` (`see('E-mail ou senha
inválidos!')`), que passa sem alteração.

No navegador: `http://localhost/login`, senha errada. Aparece o alerta
vermelho com o triângulo e "E-mail ou senha inválidos!". Com
`user@example.com` / `123456`, alerta verde com o ícone de check.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/c7-flash-escape-tipos`.

O cherry-pick cria um commit novo, com SHA diferente de `94e69f5`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c7 s50/exemplos-fvp/c7-flash-escape-tipos/projeto
git fetch fvp-c7 fvp/c7-flash-escape-tipos
git cherry-pick 94e69f5
git remote remove fvp-c7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/c7-flash-escape-tipos/solucao.bundle fvp/c7-flash-escape-tipos:fvp-c7
git cherry-pick 94e69f5        # o bundle traz o objeto; o cherry-pick cria commit novo
git branch -D fvp-c7           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/c7-flash-escape-tipos/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/c7-flash-escape-tipos/solucao.patch   # só o diff, sem commit
```

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/views/layouts/_flash_message.phtml`: C5 troca `$type` por
  `e($type)` e `$msg` por `e($msg)` nas mesmas linhas. Fique com a
  versão deste cenário e escolha um escape só para `$msg`: ou
  `htmlspecialchars(...)` ou `e($msg)` (o helper do C5 faz o mesmo
  `htmlspecialchars` com `ENT_QUOTES | ENT_SUBSTITUTE` e UTF-8). Nunca os dois juntos,
  senão aparece `&amp;lt;` na tela. Se mantiver `e($type)`, tudo bem;
  não muda nada para `success`/`danger`. Confira que o
  `<?php endforeach; ?>` ficou (o C5 manteve a tag curta).
- `tests/Integration/Controllers/HomeControllerTest.php`: outros
  cenários que testam a home podem acrescentar testes no fim da classe
  e outro `use` no topo; fique com os dois.
- `lib/FlashMessage.php` não é tocado aqui, então C1 aplica sem conflito
  antes ou depois.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (não há
mudança de schema).

## Armadilhas

- Tag curta `<? endforeach; ?>`, herdada do problem-track: funciona só
  com `short_open_tag` ligado (padrão compilado da imagem
  `php:8.3.4-fpm`, que vem sem `php.ini`). Com `php.ini-production` ou
  `php.ini-development` a view inteira quebra. Confira o resto com
  `grep -rn '<? ' app/views/`.
- PHPCS só lê `.php` e o PHPStan exclui `app/views`. Erro em `.phtml`
  só aparece no teste ou no navegador; rode o teste de controller.
- Teste de controller compara HTML cru: uma mensagem com apóstrofo vira
  `&#039;` na resposta (efeito do `ENT_QUOTES`). Os `see()` dos Cests
  comparam o texto renderizado e não mudam (`LoginCest`,
  `AdminDashboardCest`).
- Não escape duas vezes nem abra exceção "esta mensagem tem link".
  Regra única: flash é texto.
- `FlashMessage` guarda uma mensagem por tipo
  (`$_SESSION['flash'][$type] = $value`): dois `danger` seguidos, só o
  último aparece. Se pedirem várias, troque por `[] = $value`, faça um
  laço interno na parcial e ajuste `FlashMessageTest` (`$flash['success']`
  vira array).
- As variáveis da parcial (`$icons`, `$type`, `$msg`) vazam para o
  escopo do layout, porque `require` não cria escopo novo. Hoje não há
  colisão; não reutilize esses nomes no layout.
