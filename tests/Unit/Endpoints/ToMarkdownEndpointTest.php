<?php

declare(strict_types=1);

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use AgenticEndpoints\Endpoints\ToMarkdownEndpoint;
use AgenticEndpoints\Converter\BlocksToMarkdown;
use League\HTMLToMarkdown\HtmlConverter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post;

/**
 * Unit tests for ToMarkdownEndpoint.
 */
class ToMarkdownEndpointTest extends TestCase {

	private ToMarkdownEndpoint $endpoint;
	private BlocksToMarkdown $converter;

	protected function setUp(): void {
		parent::setUp();

		$htmlConverter = new HtmlConverter([
			'strip_tags' => false,
			'hard_break' => true,
		]);
		$this->converter = new BlocksToMarkdown($htmlConverter);
		$this->endpoint = new ToMarkdownEndpoint($this->converter);

		// Reset global mocks
		global $registered_rest_routes, $mock_posts, $mock_parsed_blocks;
		$registered_rest_routes = [];
		$mock_posts = [];
		$mock_parsed_blocks = [];
	}

	// =========================
	// Route Configuration Tests
	// =========================

	#[Test]
	public function it_registers_correct_route(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount(1, $registered_rest_routes);
		$this->assertEquals('agentic/v1', $registered_rest_routes[0]['namespace']);
		$this->assertEquals('/convert/to-markdown', $registered_rest_routes[0]['route']);
	}

	#[Test]
	public function it_uses_get_method(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertEquals('GET', $registered_rest_routes[0]['args']['methods']);
	}

	#[Test]
	public function it_has_post_id_parameter_in_args(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayHasKey('post_id', $args);
		$this->assertEquals('integer', $args['post_id']['type']);
		$this->assertFalse($args['post_id']['required']);
	}

	#[Test]
	public function it_has_content_parameter_in_args(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayHasKey('content', $args);
		$this->assertEquals('string', $args['content']['type']);
		$this->assertFalse($args['content']['required']);
	}

	// =========================
	// Handle Method Tests - Content Parameter
	// =========================

	#[Test]
	public function it_converts_content_to_markdown(): void {
		global $mock_parsed_blocks;
		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Test content</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('content', '<!-- wp:paragraph --><p>Test content</p><!-- /wp:paragraph -->');

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals(200, $response->get_status());

		$data = $response->get_data();
		$this->assertArrayHasKey('markdown', $data);
		$this->assertArrayHasKey('has_html_fallback', $data);
		$this->assertEquals('Test content', $data['markdown']);
	}

	#[Test]
	public function it_includes_fallback_flag_in_response(): void {
		global $mock_parsed_blocks;
		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/gallery',
				'attrs'       => [],
				'innerHTML'   => '<figure>Gallery</figure>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('content', 'test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertTrue($data['has_html_fallback']);
	}

	#[Test]
	public function it_converts_multiple_blocks_from_content(): void {
		global $mock_parsed_blocks;
		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => ['level' => 1],
				'innerHTML'   => '<h1>Title</h1>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Content</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('content', 'test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertStringContainsString('# Title', $data['markdown']);
		$this->assertStringContainsString('Content', $data['markdown']);
	}

	// =========================
	// Handle Method Tests - Post ID Parameter
	// =========================

	#[Test]
	public function it_converts_post_content_to_markdown(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post = new WP_Post(123);
		$post->post_content = '<!-- wp:paragraph --><p>Post content</p><!-- /wp:paragraph -->';
		$post->post_title = 'Test Post';
		$mock_posts[123] = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Post content</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('post_id', 123);

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals('Post content', $data['markdown']);
		$this->assertEquals(123, $data['post_id']);
		$this->assertEquals('Test Post', $data['post_title']);
	}

	#[Test]
	public function it_returns_error_for_non_existent_post(): void {
		global $mock_posts;
		$mock_posts = []; // No posts

		$request = new WP_REST_Request();
		$request->set_param('post_id', 999);

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_Error::class, $response);
		$this->assertEquals('post_not_found', $response->get_error_code());
		$this->assertEquals(['status' => 404], $response->get_error_data());
	}

	#[Test]
	public function it_includes_post_metadata_in_response(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post = new WP_Post(456);
		$post->post_content = 'content';
		$post->post_title = 'My Post Title';
		$mock_posts[456] = $post;

		$mock_parsed_blocks = [];

		$request = new WP_REST_Request();
		$request->set_param('post_id', 456);

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertArrayHasKey('post_id', $data);
		$this->assertArrayHasKey('post_title', $data);
		$this->assertEquals(456, $data['post_id']);
		$this->assertEquals('My Post Title', $data['post_title']);
	}

	#[Test]
	public function it_does_not_include_post_metadata_when_using_content(): void {
		global $mock_parsed_blocks;
		$mock_parsed_blocks = [];

		$request = new WP_REST_Request();
		$request->set_param('content', 'test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertArrayNotHasKey('post_id', $data);
		$this->assertArrayNotHasKey('post_title', $data);
	}

	// =========================
	// Handle Method Tests - Error Cases
	// =========================

	#[Test]
	public function it_returns_error_when_no_parameters_provided(): void {
		$request = new WP_REST_Request();
		// Not setting any parameters

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_Error::class, $response);
		$this->assertEquals('missing_parameter', $response->get_error_code());
		$this->assertEquals(['status' => 400], $response->get_error_data());
	}

	#[Test]
	public function it_returns_error_for_empty_post_id(): void {
		$request = new WP_REST_Request();
		$request->set_param('post_id', 0);
		// 0 is considered empty

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_Error::class, $response);
		$this->assertEquals('missing_parameter', $response->get_error_code());
	}

	#[Test]
	public function it_returns_error_for_empty_content(): void {
		$request = new WP_REST_Request();
		$request->set_param('content', '');

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_Error::class, $response);
		$this->assertEquals('missing_parameter', $response->get_error_code());
	}

	// =========================
	// Priority Tests
	// =========================

	#[Test]
	public function it_prefers_post_id_over_content_when_both_provided(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post = new WP_Post(789);
		$post->post_content = 'post content';
		$post->post_title = 'Post Title';
		$mock_posts[789] = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>From post</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('post_id', 789);
		$request->set_param('content', 'different content');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		// Should use post content, not the content parameter
		$this->assertEquals('From post', $data['markdown']);
		$this->assertArrayHasKey('post_id', $data);
	}

	// =========================
	// Edge Case Tests
	// =========================

	#[Test]
	public function it_handles_empty_post_content(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post = new WP_Post(111);
		$post->post_content = '';
		$post->post_title = 'Empty Post';
		$mock_posts[111] = $post;

		$mock_parsed_blocks = [];

		$request = new WP_REST_Request();
		$request->set_param('post_id', 111);

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals('', $data['markdown']);
	}

	#[Test]
	public function it_handles_blocks_with_no_blockname(): void {
		global $mock_parsed_blocks;
		$mock_parsed_blocks = [
			[
				'blockName'   => null,
				'attrs'       => [],
				'innerHTML'   => '  ',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Real content</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('content', 'test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertEquals('Real content', $data['markdown']);
	}

	#[Test]
	public function it_handles_complex_block_structure(): void {
		global $mock_parsed_blocks;
		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => ['level' => 2],
				'innerHTML'   => '<h2>Heading</h2>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/list',
				'attrs'       => ['ordered' => true],
				'innerHTML'   => '<ol><li>One</li><li>Two</li></ol>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/code',
				'attrs'       => ['language' => 'php'],
				'innerHTML'   => '<pre class="wp-block-code"><code>$x = 1;</code></pre>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('content', 'test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertStringContainsString('## Heading', $data['markdown']);
		$this->assertStringContainsString('1. One', $data['markdown']);
		$this->assertStringContainsString('```php', $data['markdown']);
	}

	#[Test]
	public function it_handles_unicode_in_post_content(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post = new WP_Post(222);
		$post->post_content = 'unicode';
		$post->post_title = 'Unicode Post';
		$mock_posts[222] = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Привет 你好 مرحبا</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('post_id', 222);

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertStringContainsString('Привет', $data['markdown']);
		$this->assertStringContainsString('你好', $data['markdown']);
	}

	#[Test]
	public function it_returns_false_for_has_html_fallback_when_all_supported(): void {
		global $mock_parsed_blocks;
		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Supported</p>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/heading',
				'attrs'       => ['level' => 1],
				'innerHTML'   => '<h1>Also supported</h1>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param('content', 'test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertFalse($data['has_html_fallback']);
	}
}
