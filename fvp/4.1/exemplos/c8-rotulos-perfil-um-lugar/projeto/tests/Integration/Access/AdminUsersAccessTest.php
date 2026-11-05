<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class AdminUsersAccessTest extends TestCase
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

    public function test_users_index_should_redirect_to_login_without_session(): void
    {
        $response = $this->client->get('/admin/users');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_users_paginate_should_redirect_to_login_without_session(): void
    {
        $response = $this->client->get('/admin/users/page/2');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_users_update_should_redirect_to_login_without_session(): void
    {
        $response = $this->client->put('/admin/users/1', ['form_params' => ['user' => ['role' => 'admin']]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }
}
