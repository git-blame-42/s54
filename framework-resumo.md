# Framework da disciplina: resumo dos conceitos

Leitura de 10 minutos, sem código e sem exemplos. Define o vocabulário
que o `framework-completo.md` usa. Fonte: o código do framework
(SI-DABE/problem-track; o `core/` é o mesmo do template e do nosso
projeto).

O curso apresenta o framework como um framework MVC. É isso: um
mini-framework MVC em PHP 8 puro, escrito pelo professor, sem
bibliotecas de produção. Imita Rails e Laravel em escala pequena.

## 1. Framework web

- Programa que recebe toda requisição HTTP, decide quem responde e
  devolve HTML ou JSON. Você escreve o miolo: regras, telas, tabelas.
- O da disciplina é pequeno de propósito. Tudo cabe na cabeça, e é
  isso que o professor cobra no vídeo e no FVP.
- Não existe: injeção de dependência, query builder, migrations,
  template engine, CSRF, validação declarativa. Tudo é feito à mão.

## 2. Requisição e resposta

- Requisição: o que o navegador envia. Tem método (GET ler, POST
  criar, PUT alterar, DELETE remover), URI (`/eventos/3`), parâmetros
  (query string, campos de formulário, trechos da URI) e cabeçalhos
  (ex.: `Accept`).
- Resposta: status (200 ok, 302 redirecionar, 404 não achei, 500
  erro), cabeçalhos e corpo (HTML ou JSON).
- Formulário HTML só sabe GET e POST. PUT e DELETE são simulados por
  um campo oculto `_method`; o framework lê esse campo e trata o
  método como se fosse o informado.
- Redirecionamento: resposta 302 com cabeçalho `Location`; o navegador
  faz uma nova requisição GET sozinho.
- PRG (Post/Redirect/Get): depois de um POST com sucesso, sempre
  redirecione; nunca renderize direto. Evita reenvio ao atualizar.

## 3. Front Controller e bootstrap

- Front Controller: um único arquivo recebe todas as requisições
  (`public/index.php`). O nginx reescreve qualquer caminho para ele;
  só arquivos estáticos (css, js, imagens) escapam.
- Bootstrap: preparação feita antes de qualquer página, sempre na
  mesma ordem: abrir sessão, carregar classes (autoload), instalar
  tratador de erros, ler `.env`, carregar rotas, despachar.
- Autoload PSR-4: regra que liga nome de classe a caminho de arquivo
  (`App\Models\User` em `app/Models/User.php`). Namespace ou caminho
  errado = classe "não existe". O CI confere.
- `.env`: variáveis de ambiente (banco, senha, ambiente). Variável do
  sistema operacional tem prioridade sobre o arquivo; é assim que o
  container de testes usa outro banco.

## 4. MVC

Divisão em três responsabilidades. Cada coisa mora em um só lugar.

- Model: que dados existem e quais regras valem para eles.
  Fica em `app/Models/`.
- View: como isso aparece na tela. Fica em `app/views/`.
- Controller: o que fazer com esta requisição. Fica em
  `app/Controllers/`.

Regras:

- Controller magro: lê parâmetros, chama model, escolhe view ou
  redirecionamento. Regra de negócio fica no model (ou num service,
  quando não é de uma tabela só).
- View burra: só exibe o que recebeu. Nada de consulta a banco.
- Model gordo: validações, relacionamentos, consultas específicas.

## 5. Rotas e roteador

- Rota: declaração "método + URI -> controller + ação". Todas ficam em
  `config/routes.php`.
- Roteador: percorre a lista na ordem declarada e usa a primeira rota
  que casa. Casar = mesmo método e mesma quantidade de segmentos;
  `{id}` aceita qualquer valor. Ordem errada captura a URI errada.
- Nome de rota: apelido (`events.show`). Gere URLs sempre pelo nome,
  nunca escreva `/events/3` à mão.
- RESTful: convenção de sete ações por recurso. Cobrada na rubrica
  da 4.2 (nomes, não só verbos).

  - index    GET    /x            lista
  - show     GET    /x/{id}       exibe um
  - new      GET    /x/new        formulário de criação
  - create   POST   /x            grava novo
  - edit     GET    /x/{id}/edit  formulário de edição
  - update   PUT    /x/{id}       grava alteração
  - destroy  DELETE /x/{id}       remove

- Paginação: GET `/x/page/{page}`, nome de rota `x.paginate`.

## 6. Middleware

- Código que roda entre o roteador e o controller. Deixa passar ou
  barra (redireciona e encerra).
- Uso principal: autorização (está logado? é admin?). Sempre aqui,
  nunca dentro da ação. Rubrica penaliza `if (não logado)` espalhado.
- Aplica-se a grupos de rotas. Grupos aninhados: o middleware do grupo
  interno roda primeiro. Cada middleware deve funcionar sozinho, sem
  depender da ordem.
- Registro: cada middleware tem um apelido (`auth`, `admin`) em
  `config/App.php`; a rota usa o apelido.

## 7. Controller e ação

- Controller: classe por recurso. Ação: método público, um por rota.
- Toda ação termina de um destes três jeitos: renderizar HTML,
  renderizar JSON ou redirecionar. Nunca dois.
- O controller base já sabe quem é o usuário atual (lido da sessão a
  cada requisição) e fornece os helpers de renderização e
  redirecionamento.
- Parâmetros vêm só do objeto Request (`getParam`, `getParams`),
  nunca de `$_POST`/`$_GET` direto. Rubrica cobra.

## 8. View, layout e parcial

- View: arquivo `.phtml` (HTML com PHP embutido) para uma ação.
  Pasta = recurso, arquivo = ação (`events/show.phtml`).
- Layout: moldura comum (cabeçalho, navbar, mensagens, `<h1>`) que
  inclui a view no meio. Toda view exige um `$title`.
- Parcial: pedaço reutilizável de view; nome começa com `_`.
- Variáveis chegam à view por extração: cada chave do array passado
  vira variável local. Dentro da view, `$this` é o controller.
- View JSON: arquivo `.json.php` que preenche um array `$json`; o
  framework serializa. Escolhida quando o cabeçalho `Accept` pede
  JSON (usada no Ajax).
- Formulário: campos nomeados `recurso[campo]` chegam ao controller
  como um array só. Erros de validação voltam para o mesmo formulário,
  com os valores preenchidos.

## 9. Model e Active Record

- Active Record: padrão em que uma classe = uma tabela e um objeto =
  uma linha. O objeto sabe se salvar, se apagar e se buscar.
- O model declara só o nome da tabela e a lista de colunas (sem `id`).
  Essa lista é a única fonte de verdade: coluna fora dela não existe
  para o framework.
- Operações: salvar (insere ou atualiza, validando antes), atualizar
  campos pontuais (sem validar), destruir, buscar por id, listar tudo,
  filtrar por igualdade, paginar.
- Registro novo vs existente: definido por ter `id` ou não. Muda o
  comportamento de salvar e de algumas validações.
- Atributo virtual: propriedade do objeto que não é coluna (ex.: senha
  em texto puro, usada só para gerar o hash).
- O filtro (`where`) só faz igualdade ligada por AND. Busca por
  trecho (LIKE), ordenação ou intervalo exige SQL próprio no model.

## 10. Validação e erros

- Cada model implementa `validates()`: chama regras prontas (não
  vazio, único, confirmação de senha) ou novas, que você adiciona em
  `lib/Validations.php`.
- Regra que falha registra um erro por campo no objeto; salvar
  retorna falso.
- A view lê os erros do mesmo objeto e mostra ao lado de cada campo.
- Uma mensagem por campo: a última regra que falhar sobrescreve.

## 11. Relacionamentos

- BelongsTo (pertence a): o filho aponta para o pai. A chave fica na
  coluna `pai_id` do filho.
- HasMany (tem muitos): o pai enxerga seus filhos. Mesma coluna,
  vista do lado do pai.
- BelongsToMany (N para N): dois lados, muitos para muitos. Tabela
  pivô com as duas chaves. O framework só lê e conta; inserir ou
  remover na pivô é feito por um model próprio da pivô.
- Declarado como método no model; acessado como propriedade
  (snake_case) para obter os registros, ou como método para operações
  escopadas (criar filho já ligado ao pai, buscar filho só daquele
  pai, paginar, contar).
- Escopo pelo dono: buscar o filho através do pai garante que um
  usuário não acessa registro de outro. É a autorização em dados.
- Nomes: tabelas em inglês, plural (`events`); pivô singular, em
  ordem alfabética (`event_user`); chaves `id` e `tabela_id`.

## 12. Banco de dados sem migrations

- Schema: um único arquivo SQL com DROP + CREATE de todas as tabelas.
  É a verdade absoluta.
- Mudou uma coluna: edite o schema, recrie o banco (perde os dados),
  repopule.
- Populate: script que recria o banco e insere dados de demonstração
  usando os próprios models (logo, validados e com senha em hash).
  Rubrica da 4.1 exige instruções de populate e credenciais.
- Os testes recriam o banco de teste a cada teste. Schema e model são
  conferidos o tempo todo.

## 13. Sessão, autenticação e autorização

- Sessão: memória do servidor ligada a um visitante por um cookie
  (`PHPSESSID`). Guarda só o `id` do usuário logado. Nunca senha,
  nunca o objeto inteiro.
- Autenticação: provar quem é (e-mail + senha). Senha nunca é
  armazenada; guarda-se um hash e compara-se com `password_verify`.
- Autorização: decidir o que o autenticado pode fazer. No framework é
  papel do middleware (por área) e do escopo pelo dono (por registro).
- Login = gravar o `id` na sessão. Logout = apagá-lo. No nosso
  projeto o login também grava a data em `last_login_at`.
- Perfil (`role`): coluna que diferencia administrador de
  participante (`admin` e `participant` no nosso projeto); define a
  área que o middleware libera.

## 14. Flash message e paginação

- Flash: mensagem guardada na sessão para aparecer uma vez, na
  próxima página. Combina com o PRG: grava, redireciona, a próxima
  tela exibe e apaga. Dois tipos prontos: sucesso e perigo; um texto
  por tipo.
- Paginador: objeto que sabe total de registros, página atual,
  anterior e próxima, e desenha a navegação. Depende de uma rota
  nomeada `x.paginate` (ou do nome que você informar).

## 15. Tratamento de erros

- Qualquer exceção vira uma página com a mensagem e o stack trace.
  Exceções HTTP carregam o status (404).
- Avisos do PHP (variável indefinida, arquivo não encontrado) também
  viram página de erro 500. Não há falha silenciosa: leia a mensagem.
- `dd()` imprime variáveis e para a execução. Ferramenta de depuração;
  nunca commitar.

## 16. Ambiente: Docker e o script run

- Cinco containers: servidor web (nginx), PHP da aplicação, banco
  (MySQL), PHP de testes (aponta para outro banco) e navegador
  headless (Selenium) para testes de aceitação.
- `./run <tarefa>`: atalho único para subir containers, rodar testes,
  lint, análise estática, recriar e popular o banco.
- Portas: 80 aplicação, 8080 aplicação em modo teste.

## 17. Testes: quatro camadas

- Unitário: model, validações, utilitários. Sem HTTP.
- Controller: ação chamada direto, HTML e redirecionamento capturados.
  Sem HTTP.
- Acesso: rota protegida redireciona, rota pública abre. HTTP real,
  via cliente HTTP.
- Aceitação: cenário completo em navegador real (Selenium).

Regras da rubrica: cada cenário demonstrado no vídeo tem um teste de
aceitação; cada model tem teste unitário; cada área protegida tem
teste de acesso. Nomes descrevem comportamento
(`..._should_redirect_to_login`). Testes pesam mais na nota do que
qualquer outra seção do código.

## 18. Qualidade e CI

- PHPCS: estilo PSR-12 (indentação, chaves, linha até 130 colunas).
  Corrigível automaticamente na maioria dos casos.
- PHPStan: análise estática de tipos, nível 6. Exige tipos em
  parâmetros e retornos e anotações de array.
- Checador PSR-4: nome da classe bate com o caminho.
- CI: executa testes, lint e análise a cada push. PR com CI vermelho
  perde nota.

## 19. Processo da disciplina

- Entrega: avaliação (4.1 autenticação, 4.2 CRUD, 4.3 arquivos 1xN,
  4.4 NxN com Ajax). Cada uma é uma epic.
- Epic: branch da entrega, criada da `main`. A PR final dela para
  `main` é o link entregue.
- Feature: branch de um membro dentro da epic, integrada por PR
  interna.
- Commits em inglês, formato `type: Title`. PR com título, descrição
  e CI verde.
- FVP (Fator de Validação Prática): cada membro, sozinho, implementa
  uma alteração pedida na hora. Multiplica a nota da entrega de 1,0
  até 0,0. Todo mundo precisa saber onde tudo mora.

## 20. Padrões de projeto a reconhecer

- MVC: separação model / view / controller.
- Front Controller: ponto de entrada único.
- Active Record: model que se persiste.
- Singleton: o roteador tem uma única instância.
- Template View: HTML com PHP embutido.
- Intercepting Filter: middleware.
- Post/Redirect/Get: redirecionar após gravar.

Referências de livro para cada um: seção 10 do `framework-completo.md`.

## Como seguir daqui

1. Leia o `framework-completo.md`, seções 2 e 3 (pastas e fluxo).
2. Suba o projeto e faça login com os usuários do populate.
3. Siga uma requisição no código: rota, middleware, controller,
   model, view.
4. Faça um item do `fvp-possiveis-modificacoes.md`, com teste.
