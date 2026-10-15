<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class SignUpAccessTest extends TestCase
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

    public function test_sign_up_form_should_be_accessible_without_session(): void
    {
        $response = $this->client->get('/users/new');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Cadastro', (string) $response->getBody());
    }

    public function test_login_page_should_link_to_sign_up(): void
    {
        $response = $this->client->get('/login');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('href="/users/new"', (string) $response->getBody());
    }
}
