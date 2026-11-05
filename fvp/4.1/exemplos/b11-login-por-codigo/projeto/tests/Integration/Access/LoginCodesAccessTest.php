<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Rotas do login por código (FVP B11) por HTTP real (nginx -> php_test -> Router):
 * são públicas (200), e a verificação sem pedido pendente volta para o formulário
 * de e-mail (302).
 * Próximo passo: tests/Acceptance/authentications/LoginByCodeCest.php.
 */
class LoginCodesAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'allow_redirects' => false,
            'base_uri' => 'http://web:8080'
        ]);
    }

    public function test_login_code_form_should_be_public(): void
    {
        $response = $this->client->get('/login/code');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Receber código', (string) $response->getBody());
    }

    public function test_verify_form_without_a_pending_request_should_redirect_to_the_email_form(): void
    {
        $response = $this->client->get('/login/code/verify');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login/code', $response->getHeaderLine('Location'));
    }
}
