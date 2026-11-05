<?php

namespace Core\Router;

/**
 * Prefixo de URI para um grupo de rotas:
 * Route::prefix('/admin')->group(fn () => Route::get('/events', ...))
 * registra '/admin/events'.
 *
 * Os prefixos ficam numa pilha estática enquanto o callback roda;
 * Route::get/post/put/delete concatenam a pilha na URI (apply).
 * Grupos aninhados somam os prefixos na ordem.
 */
class RoutePrefix
{
    /** @var list<string> */
    private static array $stack = [];

    public function __construct(
        private string $prefix
    ) {
    }

    public function group(callable $callback): void
    {
        self::$stack[] = rtrim($this->prefix, '/');

        try {
            $callback();
        } finally {
            // Desempilha mesmo se o callback lançar exceção, senão o prefixo
            // grudaria nas rotas registradas depois.
            array_pop(self::$stack);
        }
    }

    /**
     * URI com os prefixos ativos. A rota '/' dentro de '/admin' vira
     * '/admin', não '/admin/' (que o Router não casaria com '/admin').
     * Sem prefixo efetivo (pilha vazia ou prefix('/'), que o rtrim deixa
     * vazio), a URI fica como está: '/' nunca pode virar ''.
     */
    public static function apply(string $uri): string
    {
        $prefix = implode('', self::$stack);

        if ($prefix === '') {
            return $uri;
        }

        return $uri === '/' ? $prefix : $prefix . $uri;
    }
}
