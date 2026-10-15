# H3. Como justificar a escolha em A1

Este cenário não tem código: é a resposta oral para a pergunta que o
professor pode fazer depois de ver o A1 ("por que classe abstrata e não
trait?"). Catálogo: `fvp-possiveis-modificacoes.md`, seção H3. O código
a que ele se refere está em
`s50/exemplos-fvp/a1-centralizar-redirect-middlewares`
(commit `432adce`, `app/Middleware/BaseMiddleware.php`).

Por isso não há `projeto/`, `solucao.patch` nem `solucao.bundle` aqui.

## A resposta, em três frases

1. Classe abstrata diz que `Authenticate` e `Admin` **são** middlewares
   que negam acesso: há uma hierarquia, um lugar natural para um
   `handle` abstrato ou um gancho comum no futuro, e o PHPStan enxerga
   o tipo `BaseMiddleware` nos dois.
2. Trait só **empresta** código; não cria relação entre as classes e não
   pode ser usado como tipo. Resolve a duplicação, mas não diz nada
   sobre o desenho.
3. As duas atendem ao pedido da issue #9 ("centralizar"). A terceira
   opção, mover o helper para `core/Http/Middleware/`, conta como
   mudança no framework (bônus da rubrica), mas `core/` é do professor:
   combine antes de mexer.

## Se ele insistir em trait

Mostre que a troca é mecânica e cabe em 5 minutos sobre o A1:

```php
<?php
// app/Middleware/DeniesAccess.php

namespace App\Middleware;

use Lib\FlashMessage;

trait DeniesAccess
{
    protected function deny(string $message, string $routeName): void
    {
        FlashMessage::danger($message);
        $this->redirectTo(route($routeName));
    }

    protected function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
```

E nos middlewares: `class Authenticate implements Middleware { use
DeniesAccess; ... }`. Os testes do A1 (nenhum novo) continuam iguais;
`./run phpcs` e `./run phpstan` continuam limpos. PSR-4: o arquivo se
chama `DeniesAccess.php`.

## Palavras que o professor espera ouvir

Reutilização, encapsulamento, herança versus composição, princípio da
responsabilidade única (cada middleware decide *quando* negar; a base
decide *como*), e "sem duplicação" (DRY). A issue #9 fala em "OO", então
nomeie o conceito antes de mostrar o código.
