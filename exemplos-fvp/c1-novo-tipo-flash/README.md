# C1. Novo tipo de flash (warning, info)

Pedido, como o professor faria na hora: "Hoje só existe flash de
sucesso e de erro. Acrescente um aviso (`warning`) e um informativo
(`info`), com teste."

Chance: média. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção C1.

Branch no clone: `fvp/c1-novo-tipo-flash`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `0dd5970` | `feat: Add warning and info flash message types` |

## O que mudou

- `lib/FlashMessage.php`: dois métodos novos, `warning(string $value)`
  e `info(string $value)`, cada um chamando `self::message('<tipo>',
  $value)`, igual a `success` e `danger`.
- `tests/Unit/Lib/FlashMessageTest.php`: `test_warning` e `test_info`
  (cópia de `test_danger`) e `test_get` passa a conferir os quatro
  tipos de uma vez.

Nada muda na view: `app/views/layouts/_flash_message.phtml` já imprime
`alert-<?= $type ?>` para qualquer chave que `FlashMessage::get()`
devolver, e `alert-warning` e `alert-info` existem no Bootstrap 5.3
carregado pelo layout.

Uso em um controller, quando o professor pedir para "aparecer na
tela":

```php
FlashMessage::warning('Sua senha expira em 3 dias.');
FlashMessage::info('Nenhum evento cadastrado ainda.');
```

## Por que assim

- Segue o padrão já existente em `lib/FlashMessage.php` (um método
  estático por tipo, `message()` privado centraliza a gravação na
  sessão): o professor cobra conhecimento de `FlashMessage` na rubrica
  4.1 (`grading-path.md`, "Framework: FlashMessage").
- Teste unitário na mesma classe de teste que já cobre `success`,
  `danger` e `get`, com nome descritivo (`test_warning`, `test_info`),
  como o feedback 2025-2 pede.
- Sem mudança de view ou de texto: nenhum teste de aceitação quebra.
- PSR-12 e PHPStan nível 6 limpos; identificadores em inglês; commit
  em inglês no formato `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 73 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos: `test_warning` e `test_info` em
`tests/Unit/Lib/FlashMessageTest.php`; `test_get` ganhou 4 asserções.

No navegador (`http://localhost`): nada muda até algum controller
chamar `warning`/`info`. Para ver, coloque temporariamente
`FlashMessage::info('Olá!');` antes do `render` em
`DashboardController::index`, logue como `user@example.com` / `123456`
e abra `/dashboard`: aparece um alerta azul-claro (`alert-info`).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/c1-novo-tipo-flash`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c1 s50/exemplos-fvp/c1-novo-tipo-flash/projeto
git fetch fvp-c1 fvp/c1-novo-tipo-flash
git cherry-pick 0dd5970
git remote remove fvp-c1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/c1-novo-tipo-flash/solucao.bundle fvp/c1-novo-tipo-flash:fvp-c1
git cherry-pick 0dd5970        # mesmo SHA; o bundle preserva o objeto
git branch -D fvp-c1           # opcional, limpa a ref
```

Testado em um clone limpo de `86c81ca`: aplica sem conflito e gera o
commit `d885293` (SHA novo porque o pai e a data mudam; o conteúdo é
o mesmo).

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/c1-novo-tipo-flash/solucao.patch        # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/c1-novo-tipo-flash/solucao.patch   # só o diff, sem commit
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

- `lib/FlashMessage.php`: se o alvo já acrescentou outro método logo
  depois de `danger()` (o diff insere exatamente ali). Resolução:
  manter os dois blocos.
- `tests/Unit/Lib/FlashMessageTest.php`: se o alvo mudou `test_get`.
  Resolução: manter as asserções de `success`/`danger` do alvo e
  acrescentar as de `warning`/`info`.

Depois de aplicar: `./run test tests/Unit/Lib/FlashMessageTest.php` e
`./run phpcs`.

## Armadilhas

- O nome do tipo vira classe CSS (`alert-<tipo>`). Só funciona com os
  nomes do Bootstrap: `primary`, `secondary`, `success`, `danger`,
  `warning`, `info`, `light`, `dark`. `FlashMessage::aviso(...)` geraria
  `alert-aviso`, sem estilo.
- `message()` guarda uma mensagem por tipo (`$_SESSION['flash'][$type]`).
  Dois `warning` seguidos: só o último aparece.
- `FlashMessage::get()` apaga a sessão ao ler: no teste, chame `get()`
  uma vez e guarde em variável, como em `test_get`.
