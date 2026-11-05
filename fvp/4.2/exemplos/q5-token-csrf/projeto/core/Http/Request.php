<?php

namespace Core\Http;

class Request
{
    /**
     * Métodos que um formulário pode simular com o campo oculto `_method` (HTML só envia GET e POST).
     * São os únicos além de GET/POST que o Router conhece (Route::put e Route::delete; não há patch).
     */
    private const OVERRIDABLE_METHODS = ['PUT', 'DELETE'];

    private string $method;
    private string $uri;

    /** @var mixed[] */
    private array $params;

    /** @var array<string, string> */
    private array $headers;

    public function __construct()
    {
        $this->method = $this->resolveMethod();
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->params = $_REQUEST;
        $this->headers = function_exists('getallheaders') ? getallheaders() : [];
    }

    /**
     * Método HTTP da requisição. O `_method` só troca o método quando a requisição real é POST e o valor
     * vem do corpo ($_POST), nunca da query string: assim um GET (link, <img>, prefetch) não vira DELETE.
     * Valores fora de OVERRIDABLE_METHODS são ignorados.
     */
    private function resolveMethod(): string
    {
        $method = strtoupper((string) $_SERVER['REQUEST_METHOD']);
        $override = $_POST['_method'] ?? null;

        if ($method !== 'POST' || !is_string($override)) {
            return $method;
        }

        $override = strtoupper($override);

        return in_array($override, self::OVERRIDABLE_METHODS, true) ? $override : $method;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /** @return mixed[] */
    public function getParams(): array
    {
        return $this->params;
    }

    /** @return array<string, string>*/
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @param mixed[] $params*/
    public function addParams(array $params): void
    {
        $this->params = array_merge($this->params, $params);
    }

    public function acceptJson(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json');
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
