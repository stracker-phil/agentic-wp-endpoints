<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use AgenticEndpoints\Application;
use AgenticEndpoints\Endpoints\GetPostEndpoint;
use AgenticEndpoints\Endpoints\ReplacePostEndpoint;
use AgenticEndpoints\Endpoints\GetPostNoteEndpoint;
use AgenticEndpoints\Endpoints\ReplacePostNoteEndpoint;
use AgenticEndpoints\Endpoints\ClearPostNoteEndpoint;
use AgenticEndpoints\Converter\MarkdownToBlocks;
use AgenticEndpoints\Converter\BlocksToMarkdown;
use Parsedown;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Integration tests for Application class.
 */
class ApplicationTest extends TestCase {

	private Application $application;
	private ReplacePostEndpoint $replacePostEndpoint;
	private GetPostEndpoint $getPostEndpoint;
	private GetPostNoteEndpoint $getPostNoteEndpoint;
	private ReplacePostNoteEndpoint $replacePostNoteEndpoint;
	private ClearPostNoteEndpoint $clearPostNoteEndpoint;

	protected function setUp(): void {
		parent::setUp();

		// Create real dependencies.
		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true );
		$markdownToBlocks = new MarkdownToBlocks( $parsedown );

		$htmlConverter    = new HtmlConverter( [
			'strip_tags' => false,
			'hard_break' => true,
		] );
		$blocksToMarkdown = new BlocksToMarkdown( $htmlConverter );

		$this->replacePostEndpoint     = new ReplacePostEndpoint( $markdownToBlocks );
		$this->getPostEndpoint         = new GetPostEndpoint( $blocksToMarkdown );
		$this->getPostNoteEndpoint     = new GetPostNoteEndpoint();
		$this->replacePostNoteEndpoint = new ReplacePostNoteEndpoint();
		$this->clearPostNoteEndpoint   = new ClearPostNoteEndpoint();
		$this->application             = new Application(
			$this->replacePostEndpoint,
			$this->getPostEndpoint,
			$this->getPostNoteEndpoint,
			$this->replacePostNoteEndpoint,
			$this->clearPostNoteEndpoint
		);

		// Reset global mocks.
		global $added_actions, $registered_rest_routes;
		$added_actions          = [];
		$registered_rest_routes = [];
	}

	// =========================
	// Constructor Tests
	// =========================

	/**
	 * GIVEN endpoint instances
	 * WHEN constructing an Application
	 * THEN the Application is created successfully
	 */
	#[Test]
	public function it_accepts_endpoints_via_constructor(): void {
		$application = new Application(
			$this->replacePostEndpoint,
			$this->getPostEndpoint,
			$this->getPostNoteEndpoint,
			$this->replacePostNoteEndpoint,
			$this->clearPostNoteEndpoint
		);

		$this->assertInstanceOf( Application::class, $application );
	}

	// =========================
	// Run Method Tests
	// =========================

	/**
	 * GIVEN an Application instance
	 * WHEN run() is called
	 * THEN the rest_api_init hook is registered with correct parameters
	 */
	#[Test]
	public function it_hooks_into_rest_api_init_with_correct_configuration(): void {
		global $added_actions;

		$this->application->run();

		$this->assertCount( 1, $added_actions );
		$this->assertEquals( 'rest_api_init', $added_actions[0]['hook'] );
		$this->assertIsCallable( $added_actions[0]['callback'] );
		$this->assertEquals( $this->application->register_rest_routes( ... ), $added_actions[0]['callback'] );
		$this->assertEquals( 10, $added_actions[0]['priority'] );
		$this->assertEquals( 1, $added_actions[0]['accepted_args'] );
	}

	// =========================
	// Register Rest Routes Tests
	// =========================

	/**
	 * GIVEN an Application with five endpoints
	 * WHEN register_rest_routes() is called
	 * THEN all endpoints are registered with correct routes
	 */
	#[Test]
	public function it_registers_all_endpoints_on_agentic_post_route(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$this->assertCount( 5, $registered_rest_routes );

		$routes = array_column( $registered_rest_routes, 'route' );
		$this->assertContains( '/agentic-post/(?P<id>\d+)', $routes );
		$this->assertContains( '/agentic-post/(?P<id>\d+)/notes', $routes );

		// All use same namespace.
		$namespaces = array_unique( array_column( $registered_rest_routes, 'namespace' ) );
		$this->assertCount( 1, $namespaces );
		$this->assertEquals( 'agentic/v1', $namespaces[0] );
	}

	/**
	 * GIVEN registered endpoints
	 * WHEN checking HTTP methods
	 * THEN each endpoint has correct method and route combination
	 *
	 * @dataProvider http_method_provider
	 */
	#[Test]
	#[DataProvider( 'http_method_provider' )]
	public function it_registers_correct_http_methods( string $method, string $expected_route ): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$filtered = array_filter( $registered_rest_routes, function ( $route ) use ( $method, $expected_route ) {
			return $route['args']['methods'] === $method && $route['route'] === $expected_route;
		} );

		$this->assertCount( 1, $filtered, "Expected one route with method {$method} and route {$expected_route}" );
	}

	public static function http_method_provider(): array {
		return [
			'POST for replace-post'   => [ 'POST', '/agentic-post/(?P<id>\d+)' ],
			'GET for get-post'        => [ 'GET', '/agentic-post/(?P<id>\d+)' ],
			'GET for get-note'        => [ 'GET', '/agentic-post/(?P<id>\d+)/notes' ],
			'POST for replace-note'   => [ 'POST', '/agentic-post/(?P<id>\d+)/notes' ],
			'DELETE for clear-note'   => [ 'DELETE', '/agentic-post/(?P<id>\d+)/notes' ],
		];
	}

	/**
	 * GIVEN registered endpoints
	 * WHEN checking callbacks
	 * THEN all callbacks are callable
	 */
	#[Test]
	public function it_registers_endpoints_with_valid_callbacks(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		foreach ( $registered_rest_routes as $route ) {
			$this->assertIsCallable( $route['args']['callback'] );
			$this->assertIsCallable( $route['args']['permission_callback'] );
		}
	}

	// =========================
	// Integration Tests
	// =========================

	/**
	 * GIVEN an Application instance
	 * WHEN run() is called followed by register_rest_routes()
	 * THEN the full initialization cycle completes successfully
	 */
	#[Test]
	public function it_completes_full_initialization_cycle(): void {
		global $added_actions, $registered_rest_routes;

		// Run the application.
		$this->application->run();

		// Verify action was added.
		$this->assertCount( 1, $added_actions );

		// Simulate WordPress calling the callback.
		$this->application->register_rest_routes();

		// Verify routes were registered.
		$this->assertCount( 5, $registered_rest_routes );
	}

	/**
	 * GIVEN an Application instance
	 * WHEN methods are called multiple times
	 * THEN they handle repeated calls appropriately
	 *
	 * @dataProvider multiple_calls_provider
	 */
	#[Test]
	#[DataProvider( 'multiple_calls_provider' )]
	public function it_handles_multiple_calls( string $method, string $global_var, int $expected_count ): void {
		global $added_actions, $registered_rest_routes;

		$this->application->$method();
		$this->application->$method();

		$global = $method === 'run' ? $added_actions : $registered_rest_routes;
		$this->assertCount( $expected_count, $global );
	}

	public static function multiple_calls_provider(): array {
		return [
			'run() adds action each time'                => [ 'run', 'added_actions', 2 ],
			'register_rest_routes() registers each time' => [
				'register_rest_routes',
				'registered_rest_routes',
				10,
			],
		];
	}
}
