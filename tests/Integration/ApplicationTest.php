<?php

declare(strict_types=1);

namespace AgenticEndpoints\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use AgenticEndpoints\Application;
use AgenticEndpoints\Endpoints\ToBlocksEndpoint;
use AgenticEndpoints\Endpoints\ToMarkdownEndpoint;
use AgenticEndpoints\Converter\MarkdownToBlocks;
use AgenticEndpoints\Converter\BlocksToMarkdown;
use Parsedown;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Integration tests for Application class.
 */
class ApplicationTest extends TestCase {

	private Application $application;
	private ToBlocksEndpoint $toBlocksEndpoint;
	private ToMarkdownEndpoint $toMarkdownEndpoint;

	protected function setUp(): void {
		parent::setUp();

		// Create real dependencies
		$parsedown = new Parsedown();
		$parsedown->setSafeMode(true);
		$markdownToBlocks = new MarkdownToBlocks($parsedown);

		$htmlConverter = new HtmlConverter([
			'strip_tags' => false,
			'hard_break' => true,
		]);
		$blocksToMarkdown = new BlocksToMarkdown($htmlConverter);

		$this->toBlocksEndpoint = new ToBlocksEndpoint($markdownToBlocks);
		$this->toMarkdownEndpoint = new ToMarkdownEndpoint($blocksToMarkdown);
		$this->application = new Application($this->toBlocksEndpoint, $this->toMarkdownEndpoint);

		// Reset global mocks
		global $added_actions, $registered_rest_routes;
		$added_actions = [];
		$registered_rest_routes = [];
	}

	// =========================
	// Constructor Tests
	// =========================

	#[Test]
	public function it_accepts_endpoints_via_constructor(): void {
		$application = new Application($this->toBlocksEndpoint, $this->toMarkdownEndpoint);

		$this->assertInstanceOf(Application::class, $application);
	}

	// =========================
	// Run Method Tests
	// =========================

	#[Test]
	public function it_hooks_into_rest_api_init(): void {
		global $added_actions;

		$this->application->run();

		$this->assertCount(1, $added_actions);
		$this->assertEquals('rest_api_init', $added_actions[0]['hook']);
	}

	#[Test]
	public function it_registers_callback_for_rest_api_init(): void {
		global $added_actions;

		$this->application->run();

		$this->assertEquals([$this->application, 'register_rest_routes'], $added_actions[0]['callback']);
	}

	#[Test]
	public function it_uses_default_priority_for_hook(): void {
		global $added_actions;

		$this->application->run();

		$this->assertEquals(10, $added_actions[0]['priority']);
	}

	#[Test]
	public function it_uses_default_accepted_args_for_hook(): void {
		global $added_actions;

		$this->application->run();

		$this->assertEquals(1, $added_actions[0]['accepted_args']);
	}

	// =========================
	// Register Rest Routes Tests
	// =========================

	#[Test]
	public function it_registers_all_endpoints(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$this->assertCount(2, $registered_rest_routes);
	}

	#[Test]
	public function it_registers_agentic_post_endpoint(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$routes = array_column($registered_rest_routes, 'route');
		$this->assertContains('/agentic-post', $routes);
	}

	#[Test]
	public function it_registers_both_methods_on_same_route(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$routes = array_column($registered_rest_routes, 'route');
		$this->assertEquals(['/agentic-post', '/agentic-post'], $routes);
	}

	#[Test]
	public function it_registers_endpoints_with_correct_namespace(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		foreach ($registered_rest_routes as $route) {
			$this->assertEquals('agentic/v1', $route['namespace']);
		}
	}

	#[Test]
	public function it_registers_post_method_for_to_blocks(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$postRoute = array_filter($registered_rest_routes, function ($route) {
			return $route['args']['methods'] === 'POST';
		});

		$this->assertCount(1, $postRoute);
		$postRoute = array_values($postRoute)[0];
		$this->assertEquals('/agentic-post', $postRoute['route']);
	}

	#[Test]
	public function it_registers_get_method_for_to_markdown(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$getRoute = array_filter($registered_rest_routes, function ($route) {
			return $route['args']['methods'] === 'GET';
		});

		$this->assertCount(1, $getRoute);
		$getRoute = array_values($getRoute)[0];
		$this->assertEquals('/agentic-post', $getRoute['route']);
	}

	// =========================
	// Integration Tests
	// =========================

	#[Test]
	public function it_completes_full_initialization_cycle(): void {
		global $added_actions, $registered_rest_routes;

		// Run the application
		$this->application->run();

		// Verify action was added
		$this->assertCount(1, $added_actions);

		// Simulate WordPress calling the callback
		$this->application->register_rest_routes();

		// Verify routes were registered
		$this->assertCount(2, $registered_rest_routes);
	}

	#[Test]
	public function it_can_be_run_multiple_times_safely(): void {
		global $added_actions;

		$this->application->run();
		$this->application->run();

		// Each run adds another action (WordPress allows this)
		$this->assertCount(2, $added_actions);
	}

	#[Test]
	public function it_registers_routes_multiple_times_when_called(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();
		$this->application->register_rest_routes();

		// Each call registers routes again
		$this->assertCount(4, $registered_rest_routes);
	}

	// =========================
	// Endpoint Callback Tests
	// =========================

	#[Test]
	public function it_registers_endpoints_with_valid_callbacks(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		foreach ($registered_rest_routes as $route) {
			$this->assertIsCallable($route['args']['callback']);
		}
	}

	#[Test]
	public function it_registers_endpoints_with_valid_permission_callbacks(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		foreach ($registered_rest_routes as $route) {
			$this->assertIsCallable($route['args']['permission_callback']);
		}
	}

	// =========================
	// Edge Case Tests
	// =========================

	#[Test]
	public function it_handles_endpoints_with_same_namespace(): void {
		global $registered_rest_routes;

		$this->application->register_rest_routes();

		$namespaces = array_unique(array_column($registered_rest_routes, 'namespace'));
		$this->assertCount(1, $namespaces);
		$this->assertEquals('agentic/v1', $namespaces[0]);
	}
}
