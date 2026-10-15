<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventsAccessTest extends TestCase
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'index' => ['GET', '/events'],
            'paginate' => ['GET', '/events/page/2'],
            'new' => ['GET', '/events/new'],
            'create' => ['POST', '/events'],
            'show' => ['GET', '/events/1'],
            'edit' => ['GET', '/events/1/edit'],
            'update' => ['PUT', '/events/1'],
            'destroy' => ['DELETE', '/events/1'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_events_routes_should_redirect_to_login_without_session(string $method, string $uri): void
    {
        $response = $this->client->request($method, $uri);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }
}
