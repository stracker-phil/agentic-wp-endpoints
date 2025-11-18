<?php

declare(strict_types=1);

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use AgenticEndpoints\Endpoints\AbstractEndpoint;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Concrete implementation of AbstractEndpoint for testing.
 */
class TestableEndpoint extends AbstractEndpoint {

	private string $route = '/test';
	private string $method = 'GET';
	private array $args = [];

	public function setRoute(string $route): void {
		$this->route = $route;
	}

	public function setMethod(string $method): void {
		$this->method = $method;
	}

	public function setArgs(array $args): void {
		$this->args = $args;
	}

	public function register(): void {
		$this->register_route();
	}

	protected function get_route(): string {
		return $this->route;
	}

	protected function get_methods() {
		return $this->method;
	}

	protected function get_args(): array {
		return $this->args;
	}

	public function handle(WP_REST_Request $request) {
		return $this->success(['test' => 'data']);
	}

	// Expose protected methods for testing
	public function publicSuccess($data, int $status = 200): WP_REST_Response {
		return $this->success($data, $status);
	}

	public function publicError(string $code, string $message, int $status = 400): WP_Error {
		return $this->error($code, $message, $status);
	}

	public function getNamespace(): string {
		return $this->namespace;
	}
}

/**
 * Unit tests for AbstractEndpoint.
 */
class AbstractEndpointTest extends TestCase {

	private TestableEndpoint $endpoint;

	protected function setUp(): void {
		parent::setUp();
		$this->endpoint = new TestableEndpoint();

		// Reset global mocks
		global $mock_current_user_can, $registered_rest_routes;
		$mock_current_user_can = null;
		$registered_rest_routes = [];
	}

	// =========================
	// Namespace Tests
	// =========================

	#[Test]
	public function it_has_correct_namespace(): void {
		$this->assertEquals('agentic/v1', $this->endpoint->getNamespace());
	}

	// =========================
	// Success Response Tests
	// =========================

	#[Test]
	public function it_creates_success_response_with_default_status(): void {
		$data = ['key' => 'value'];
		$response = $this->endpoint->publicSuccess($data);

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals($data, $response->get_data());
		$this->assertEquals(200, $response->get_status());
	}

	#[Test]
	public function it_creates_success_response_with_custom_status(): void {
		$data = ['created' => true];
		$response = $this->endpoint->publicSuccess($data, 201);

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals($data, $response->get_data());
		$this->assertEquals(201, $response->get_status());
	}

	#[Test]
	public function it_creates_success_response_with_empty_data(): void {
		$response = $this->endpoint->publicSuccess([]);

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals([], $response->get_data());
	}

	#[Test]
	public function it_creates_success_response_with_null_data(): void {
		$response = $this->endpoint->publicSuccess(null);

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertNull($response->get_data());
	}

	#[Test]
	public function it_creates_success_response_with_string_data(): void {
		$response = $this->endpoint->publicSuccess('string data');

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals('string data', $response->get_data());
	}

	// =========================
	// Error Response Tests
	// =========================

	#[Test]
	public function it_creates_error_response_with_default_status(): void {
		$error = $this->endpoint->publicError('test_error', 'Test message');

		$this->assertInstanceOf(WP_Error::class, $error);
		$this->assertEquals('test_error', $error->get_error_code());
		$this->assertEquals('Test message', $error->get_error_message());
		$this->assertEquals(['status' => 400], $error->get_error_data());
	}

	#[Test]
	public function it_creates_error_response_with_custom_status(): void {
		$error = $this->endpoint->publicError('not_found', 'Resource not found', 404);

		$this->assertInstanceOf(WP_Error::class, $error);
		$this->assertEquals('not_found', $error->get_error_code());
		$this->assertEquals('Resource not found', $error->get_error_message());
		$this->assertEquals(['status' => 404], $error->get_error_data());
	}

	#[Test]
	public function it_creates_error_response_with_server_error_status(): void {
		$error = $this->endpoint->publicError('server_error', 'Internal error', 500);

		$this->assertEquals(['status' => 500], $error->get_error_data());
	}

	// =========================
	// Permission Check Tests
	// =========================

	#[Test]
	public function it_allows_access_when_user_can_edit_posts(): void {
		global $mock_current_user_can;
		$mock_current_user_can = true;

		$request = new WP_REST_Request();
		$result = $this->endpoint->check_permission($request);

		$this->assertTrue($result);
	}

	#[Test]
	public function it_denies_access_when_user_cannot_edit_posts(): void {
		global $mock_current_user_can;
		$mock_current_user_can = false;

		$request = new WP_REST_Request();
		$result = $this->endpoint->check_permission($request);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('rest_forbidden', $result->get_error_code());
		$this->assertEquals(['status' => 403], $result->get_error_data());
	}

	// =========================
	// Route Registration Tests
	// =========================

	#[Test]
	public function it_registers_route_with_correct_namespace(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount(1, $registered_rest_routes);
		$this->assertEquals('agentic/v1', $registered_rest_routes[0]['namespace']);
	}

	#[Test]
	public function it_registers_route_with_correct_path(): void {
		global $registered_rest_routes;

		$this->endpoint->setRoute('/custom/path');
		$this->endpoint->register();

		$this->assertEquals('/custom/path', $registered_rest_routes[0]['route']);
	}

	#[Test]
	public function it_registers_route_with_correct_method(): void {
		global $registered_rest_routes;

		$this->endpoint->setMethod('POST');
		$this->endpoint->register();

		$this->assertEquals('POST', $registered_rest_routes[0]['args']['methods']);
	}

	#[Test]
	public function it_registers_route_with_callback(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertEquals([$this->endpoint, 'handle'], $registered_rest_routes[0]['args']['callback']);
	}

	#[Test]
	public function it_registers_route_with_permission_callback(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertEquals([$this->endpoint, 'check_permission'], $registered_rest_routes[0]['args']['permission_callback']);
	}

	#[Test]
	public function it_registers_route_with_args(): void {
		global $registered_rest_routes;

		$args = [
			'param' => [
				'type'     => 'string',
				'required' => true,
			],
		];
		$this->endpoint->setArgs($args);
		$this->endpoint->register();

		$this->assertEquals($args, $registered_rest_routes[0]['args']['args']);
	}

	#[Test]
	public function it_registers_route_with_empty_args_by_default(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertEquals([], $registered_rest_routes[0]['args']['args']);
	}

	// =========================
	// Handle Method Tests
	// =========================

	#[Test]
	public function it_handles_request(): void {
		$request = new WP_REST_Request();
		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals(['test' => 'data'], $response->get_data());
	}
}
