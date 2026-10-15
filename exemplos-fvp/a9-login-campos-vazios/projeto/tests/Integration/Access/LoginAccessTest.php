<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class LoginAccessTest extends TestCase
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

    public function test_login_form_should_disable_html5_validation(): void
    {
        $response = $this->client->get('/login');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('novalidate', (string) $response->getBody());
    }

    public function test_post_login_without_params_should_redirect_to_login(): void
    {
        $response = $this->client->post('/login');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_post_login_with_empty_fields_should_redirect_to_login(): void
    {
        $response = $this->client->post('/login', [
            'form_params' => ['user' => ['email' => '', 'password' => '']]
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }
}
