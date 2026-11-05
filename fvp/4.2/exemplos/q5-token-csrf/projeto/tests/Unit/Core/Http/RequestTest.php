<?php

namespace Tests\Unit\Core\Http;

use Core\Constants\Constants;
use Core\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once Constants::rootPath()->join('tests/Unit/Core/Http/header_mock.php');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
    }

    public function tearDown(): void
    {
        $_REQUEST = [];
        $_POST = [];
        $_GET = [];
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function test_should_return_method(): void
    {
        $request = new Request();
        $this->assertEquals('GET', $request->getMethod());
    }

    public function test_get_with_method_param_should_stay_get(): void
    {
        // GET /admin/events/1?_method=DELETE: não pode cair no destroy.
        $_GET = ['_method' => 'DELETE'];
        $_REQUEST = $_GET;

        $this->assertEquals('GET', (new Request())->getMethod());
    }

    public function test_post_with_method_delete_in_body_should_become_delete(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_method' => 'DELETE'];
        $_REQUEST = $_POST;

        $this->assertEquals('DELETE', (new Request())->getMethod());
    }

    public function test_post_with_method_put_should_be_overridden_case_insensitive(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = ['_method' => 'PUT'];
        $this->assertEquals('PUT', (new Request())->getMethod());

        $_POST = ['_method' => 'put'];
        $this->assertEquals('PUT', (new Request())->getMethod());

        $_POST = ['_method' => 'delete'];
        $this->assertEquals('DELETE', (new Request())->getMethod());
    }

    public function test_post_with_method_only_in_query_string_should_stay_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = ['_method' => 'DELETE'];
        $_REQUEST = $_GET;

        $this->assertEquals('POST', (new Request())->getMethod());
    }

    public function test_post_with_invalid_method_should_be_ignored(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // PATCH também: o Router não tem rota PATCH.
        foreach (['GET', 'HEAD', 'OPTIONS', 'TRACE', 'PATCH', 'FOO', ''] as $value) {
            $_POST = ['_method' => $value];
            $this->assertEquals('POST', (new Request())->getMethod(), "_method={$value}");
        }

        $_POST = ['_method' => ['DELETE']];
        $this->assertEquals('POST', (new Request())->getMethod(), '_method como array');
    }

    public function test_real_put_and_delete_should_be_kept(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->assertEquals('DELETE', (new Request())->getMethod());

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_POST = ['_method' => 'DELETE'];
        $this->assertEquals('PUT', (new Request())->getMethod());
    }

    public function test_should_return_uri(): void
    {
        $request = new Request();
        $this->assertEquals('/test', $request->getUri());
    }

    public function test_should_return_params(): void
    {
        $_REQUEST = ['name' => 'John Doe'];
        $request = new Request();
        $this->assertEquals(['name' => 'John Doe'], $request->getParams());
    }

    public function test_should_return_headers(): void
    {
        $request = new Request();
        $this->assertEquals(getallheaders(), $request->getHeaders());
    }

    public function test_add_params_should_add_the_params(): void
    {
        $request = new Request();
        $params = ['id' => 1];

        $this->assertEmpty($request->getParams());
        $request->addParams($params);

        $this->assertEquals($params, $request->getParams());

        $otherParams = ['user_id' => 1];
        $request->addParams($otherParams);

        $this->assertEquals(array_merge($params, $otherParams), $request->getParams());
    }

    public function test_accept_json_should_return_true_when_accept_Json(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $request = new Request();

        $this->assertTrue($request->acceptJson());

        $_SERVER['HTTP_ACCEPT'] = 'application/html';
        $this->assertFalse($request->acceptJson());
    }

    public function test_get_param_should_return_the_param(): void
    {
        $_REQUEST = ['name' => 'John Doe'];
        $request = new Request();

        $this->assertEquals('John Doe', $request->getParam('name'));
        $this->assertNull($request->getParam('age'));
    }

    public function test_get_param_should_return_the_default_value_when_param_not_found(): void
    {
        $_REQUEST = ['name' => 'John Doe'];
        $request = new Request();

        $this->assertEquals('John Doe', $request->getParam('name', 'Jane Doe'));
        $this->assertEquals('Jane Doe', $request->getParam('age', 'Jane Doe'));
    }
}
