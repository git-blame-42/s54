<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Rota inexistente pela porta 8080 (php_test, APP_ENV=testing, ou seja, fora de development):
 * 404 com a página amigável, sem stack trace nem caminho de arquivo.
 */
class NotFoundAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'allow_redirects' => false,
            'http_errors' => false,
            'base_uri' => 'http://web:8080'
        ]);
    }

    public function test_unknown_route_returns_friendly_404_page(): void
    {
        $response = $this->client->get('/rota-que-nao-existe');
        $body = (string) $response->getBody();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
        $this->assertStringContainsString('Página não encontrada', $body);
        $this->assertStringContainsString('Voltar ao início', $body);
        $this->assertStringNotContainsString('Stack Trace', $body);
        $this->assertStringNotContainsString('rota-que-nao-existe', $body);
        $this->assertStringNotContainsString('/var/www', $body);
    }
}
