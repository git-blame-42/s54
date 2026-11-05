<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class ProfileAccessTest extends TestCase
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

    public function test_edit_profile_should_redirect_to_login_without_session(): void
    {
        $response = $this->client->get('/profile/edit');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_update_profile_should_redirect_to_login_without_session(): void
    {
        $response = $this->client->put('/profile', [
            'form_params' => ['user' => ['name' => 'Invasor', 'email' => 'invasor@example.com']]
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }
}
