<?php

namespace Tests\Unit\App\Middleware;

use Core\Http\Request;
use Lib\Csrf;
use PHPUnit\Framework\TestCase;

class VerifyCsrfTest extends TestCase
{
    public function setUp(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/events';
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        unset($_SESSION['_token']);
    }

    public function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SESSION['_token']);
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     */
    private function handle(string $method, array $post = [], array $get = []): VerifyCsrfDouble
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = $post;
        $_GET = $get;
        $_REQUEST = array_merge($get, $post);

        $middleware = new VerifyCsrfDouble();
        $middleware->handle(new Request());

        return $middleware;
    }

    public function test_get_should_pass_without_token(): void
    {
        $this->assertFalse($this->handle('GET')->denied);
    }

    public function test_post_without_token_should_be_denied(): void
    {
        Csrf::token();

        $this->assertTrue($this->handle('POST')->denied);
    }

    public function test_post_with_wrong_token_should_be_denied(): void
    {
        Csrf::token();

        $this->assertTrue($this->handle('POST', ['_token' => 'errado'])->denied);
    }

    public function test_post_with_session_token_should_pass(): void
    {
        $this->assertFalse($this->handle('POST', ['_token' => Csrf::token()])->denied);
    }

    public function test_put_and_delete_via_method_override_should_require_the_token(): void
    {
        $token = Csrf::token();

        $this->assertTrue($this->handle('POST', ['_method' => 'PUT'])->denied);
        $this->assertTrue($this->handle('POST', ['_method' => 'DELETE'])->denied);
        $this->assertFalse($this->handle('POST', ['_method' => 'DELETE', '_token' => $token])->denied);
    }

    public function test_token_only_in_query_string_should_be_denied(): void
    {
        $this->assertTrue($this->handle('POST', [], ['_token' => Csrf::token()])->denied);
    }
}
