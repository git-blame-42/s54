<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class LogoutAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'allow_redirects' => false,
            'base_uri' => 'http://web:8080',
            'http_errors' => false
        ]);
    }

    public function test_logout_via_get_should_not_be_found(): void
    {
        $response = $this->client->get('/logout');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_logout_via_delete_should_redirect_to_login(): void
    {
        $response = $this->client->delete('/logout');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_logout_via_post_with_method_param_should_redirect_to_login(): void
    {
        $response = $this->client->post('/logout', ['form_params' => ['_method' => 'DELETE']]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }
}
