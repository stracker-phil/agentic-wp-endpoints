<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
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

	public function setRoute( string $route ): void {
		$this->route = $route;
	}

	public function setMethod( string $method ): void {
		$this->method = $method;
	}

	public function setArgs( array $args ): void {
		$this->args = $args;
	}

	public function register(): void {
		$this->register_route();
	}

	protected function get_route(): string {
		return $this->route;
	}

	protected function get_methods(): string {
		return $this->method;
	}

	protected function get_args(): array {
		return $this->args;
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->success( [ 'test' => 'data' ] );
	}

	// Expose protected methods for testing.
	public function publicSuccess( $data, int $status = 200 ): WP_REST_Response {
		return $this->success( $data, $status );
	}

	public function publicError( string $code, string $message, int $status = 400 ): WP_Error {
		return $this->error( $code, $message, $status );
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

		// Reset global mocks.
		global $mock_current_user_can, $registered_rest_routes;
		$mock_current_user_can  = null;
		$registered_rest_routes = [];
	}

	// =========================
	// Namespace Tests
	// =========================

	/**
	 * GIVEN an endpoint instance
	 * WHEN checking the namespace
	 * THEN it returns the agentic/v1 namespace
	 */
	#[Test]
	public function it_has_correct_namespace(): void {
		$this->assertEquals( 'agentic/v1', $this->endpoint->getNamespace() );
	}

	// =========================
	// Success Response Tests
	// =========================

	/**
	 * GIVEN various data types for success response
	 * WHEN creating a success response
	 * THEN the response contains the correct data and status
	 *
	 * @dataProvider success_response_provider
	 */
	#[Test]
	#[DataProvider( 'success_response_provider' )]
	public function it_creates_success_response( $data, int $status, $expected_data ): void {
		$response = $this->endpoint->publicSuccess( $data, $status );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( $expected_data, $response->get_data() );
		$this->assertEquals( $status, $response->get_status() );
	}

	public static function success_response_provider(): array {
		return [
			'array data with default status'  => [ [ 'key' => 'value' ], 200, [ 'key' => 'value' ] ],
			'array data with custom status'   => [ [ 'created' => true ], 201, [ 'created' => true ] ],
			'empty array'                     => [ [], 200, [] ],
			'null data'                       => [ null, 200, null ],
			'string data'                     => [ 'string data', 200, 'string data' ],
		];
	}

	// =========================
	// Error Response Tests
	// =========================

	/**
	 * GIVEN an error code, message, and status
	 * WHEN creating an error response
	 * THEN the WP_Error contains the correct information
	 *
	 * @dataProvider error_response_provider
	 */
	#[Test]
	#[DataProvider( 'error_response_provider' )]
	public function it_creates_error_response( string $code, string $message, int $status ): void {
		$error = $this->endpoint->publicError( $code, $message, $status );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( $code, $error->get_error_code() );
		$this->assertEquals( $message, $error->get_error_message() );
		$this->assertEquals( [ 'status' => $status ], $error->get_error_data() );
	}

	public static function error_response_provider(): array {
		return [
			'default bad request'  => [ 'test_error', 'Test message', 400 ],
			'not found'            => [ 'not_found', 'Resource not found', 404 ],
			'server error'         => [ 'server_error', 'Internal error', 500 ],
		];
	}

	// =========================
	// Permission Check Tests
	// =========================

	/**
	 * GIVEN a user with or without edit_posts capability
	 * WHEN checking permission
	 * THEN access is granted or denied appropriately
	 *
	 * @dataProvider permission_provider
	 */
	#[Test]
	#[DataProvider( 'permission_provider' )]
	public function it_checks_user_permission( bool $can_edit, bool $expected_allowed ): void {
		global $mock_current_user_can;
		$mock_current_user_can = $can_edit;

		$request = new WP_REST_Request();
		$result  = $this->endpoint->check_permission( $request );

		if ( $expected_allowed ) {
			$this->assertTrue( $result );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
			$this->assertEquals( [ 'status' => 403 ], $result->get_error_data() );
		}
	}

	public static function permission_provider(): array {
		return [
			'user can edit posts'    => [ true, true ],
			'user cannot edit posts' => [ false, false ],
		];
	}

	// =========================
	// Route Registration Tests
	// =========================

	/**
	 * GIVEN an endpoint with specific configuration
	 * WHEN registering the route
	 * THEN the route is registered with correct parameters
	 *
	 * @dataProvider route_registration_provider
	 */
	#[Test]
	#[DataProvider( 'route_registration_provider' )]
	public function it_registers_route_correctly(
		?string $route,
		?string $method,
		?array $args,
		string $expected_route,
		string $expected_method,
		array $expected_args
	): void {
		global $registered_rest_routes;

		if ( $route !== null ) {
			$this->endpoint->setRoute( $route );
		}
		if ( $method !== null ) {
			$this->endpoint->setMethod( $method );
		}
		if ( $args !== null ) {
			$this->endpoint->setArgs( $args );
		}

		$this->endpoint->register();

		$this->assertCount( 1, $registered_rest_routes );
		$this->assertEquals( 'agentic/v1', $registered_rest_routes[0]['namespace'] );
		$this->assertEquals( $expected_route, $registered_rest_routes[0]['route'] );
		$this->assertEquals( $expected_method, $registered_rest_routes[0]['args']['methods'] );
		$this->assertEquals( $expected_args, $registered_rest_routes[0]['args']['args'] );
		$this->assertEquals( [ $this->endpoint, 'handle' ], $registered_rest_routes[0]['args']['callback'] );
		$this->assertEquals( [ $this->endpoint, 'check_permission' ], $registered_rest_routes[0]['args']['permission_callback'] );
	}

	public static function route_registration_provider(): array {
		return [
			'default configuration'        => [
				null,
				null,
				null,
				'/test',
				'GET',
				[],
			],
			'custom route'                 => [
				'/custom/path',
				null,
				null,
				'/custom/path',
				'GET',
				[],
			],
			'custom method'                => [
				null,
				'POST',
				null,
				'/test',
				'POST',
				[],
			],
			'custom args'                  => [
				null,
				null,
				[
					'param' => [
						'type'     => 'string',
						'required' => true,
					],
				],
				'/test',
				'GET',
				[
					'param' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			],
		];
	}

	// =========================
	// Handle Method Tests
	// =========================

	/**
	 * GIVEN a valid request
	 * WHEN the handle method is called
	 * THEN a success response is returned
	 */
	#[Test]
	public function it_handles_request(): void {
		$request  = new WP_REST_Request();
		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( [ 'test' => 'data' ], $response->get_data() );
	}
}
