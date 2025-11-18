<?php

declare( strict_types = 1 );

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

		$htmlConverter   = new HtmlConverter( [
			'strip_tags' => false,
			'hard_break' => true,
		] );
		$this->converter = new BlocksToMarkdown( $htmlConverter );
		$this->endpoint  = new ToMarkdownEndpoint( $this->converter );

		// Reset global mocks.
		global $registered_rest_routes, $mock_posts, $mock_parsed_blocks;
		$registered_rest_routes = [];
		$mock_posts             = [];
		$mock_parsed_blocks     = [];
	}

	// =========================
	// Route Configuration Tests
	// =========================

	#[Test]
	public function it_registers_correct_route(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount( 1, $registered_rest_routes );
		$this->assertEquals( 'agentic/v1', $registered_rest_routes[0]['namespace'] );
		$this->assertEquals( '/agentic-post', $registered_rest_routes[0]['route'] );
	}

	#[Test]
	public function it_uses_get_method(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertEquals( 'GET', $registered_rest_routes[0]['args']['methods'] );
	}

	#[Test]
	public function it_has_required_post_id_parameter(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayHasKey( 'post_id', $args );
		$this->assertEquals( 'integer', $args['post_id']['type'] );
		$this->assertTrue( $args['post_id']['required'] );
	}

	#[Test]
	public function it_does_not_have_content_parameter(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayNotHasKey( 'content', $args );
	}

	// =========================
	// Handle Method Tests - Success Cases
	// =========================

	#[Test]
	public function it_converts_post_content_to_markdown(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post                = new WP_Post( 123 );
		$post->post_content  = '<!-- wp:paragraph --><p>Post content</p><!-- /wp:paragraph -->';
		$post->post_title    = 'Test Post';
		$post->post_status   = 'publish';
		$post->post_name     = 'test-post';
		$post->post_date     = '2024-03-15 10:30:00';
		$post->post_modified = '2024-03-16 14:00:00';
		$mock_posts[123]     = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Post content</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 123 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Post content', $data['markdown'] );
	}

	#[Test]
	public function it_returns_all_post_metadata(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post                = new WP_Post( 456 );
		$post->post_content  = 'content';
		$post->post_title    = 'My Post Title';
		$post->post_status   = 'draft';
		$post->post_name     = 'my-post-title';
		$post->post_date     = '2024-06-01 08:00:00';
		$post->post_modified = '2024-06-02 12:30:00';
		$mock_posts[456]     = $post;

		$mock_parsed_blocks = [];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 456 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'post_id', $data );
		$this->assertArrayHasKey( 'post_title', $data );
		$this->assertArrayHasKey( 'post_status', $data );
		$this->assertArrayHasKey( 'post_name', $data );
		$this->assertArrayHasKey( 'post_date', $data );
		$this->assertArrayHasKey( 'post_modified', $data );
		$this->assertArrayHasKey( 'markdown', $data );
		$this->assertArrayHasKey( 'has_html_fallback', $data );

		$this->assertEquals( 456, $data['post_id'] );
		$this->assertEquals( 'My Post Title', $data['post_title'] );
		$this->assertEquals( 'draft', $data['post_status'] );
		$this->assertEquals( 'my-post-title', $data['post_name'] );
		$this->assertEquals( '2024-06-01 08:00:00', $data['post_date'] );
		$this->assertEquals( '2024-06-02 12:30:00', $data['post_modified'] );
	}

	#[Test]
	public function it_includes_has_html_fallback_flag(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 789 );
		$post->post_content = 'content';
		$post->post_title   = 'Test';
		$mock_posts[789]    = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/gallery',
				'attrs'       => [],
				'innerHTML'   => '<figure>Gallery</figure>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 789 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['has_html_fallback'] );
	}

	#[Test]
	public function it_returns_false_for_has_html_fallback_when_all_supported(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 111 );
		$post->post_content = 'content';
		$post->post_title   = 'Test';
		$mock_posts[111]    = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Supported</p>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/heading',
				'attrs'       => [ 'level' => 1 ],
				'innerHTML'   => '<h1>Also supported</h1>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 111 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['has_html_fallback'] );
	}

	// =========================
	// Handle Method Tests - Error Cases
	// =========================

	#[Test]
	public function it_returns_error_for_non_existent_post(): void {
		global $mock_posts;
		$mock_posts = []; // No posts.

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 999 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'post_not_found', $response->get_error_code() );
		$this->assertEquals( [ 'status' => 404 ], $response->get_error_data() );
	}

	// =========================
	// Edge Case Tests
	// =========================

	#[Test]
	public function it_handles_empty_post_content(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 222 );
		$post->post_content = '';
		$post->post_title   = 'Empty Post';
		$post->post_name    = 'empty-post';
		$mock_posts[222]    = $post;

		$mock_parsed_blocks = [];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 222 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( '', $data['markdown'] );
	}

	#[Test]
	public function it_handles_blocks_with_no_blockname(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 333 );
		$post->post_content = 'content';
		$post->post_title   = 'Test';
		$mock_posts[333]    = $post;

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
		$request->set_param( 'post_id', 333 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'Real content', $data['markdown'] );
	}

	#[Test]
	public function it_handles_complex_block_structure(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 444 );
		$post->post_content = 'content';
		$post->post_title   = 'Complex Post';
		$mock_posts[444]    = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => [ 'level' => 2 ],
				'innerHTML'   => '<h2>Heading</h2>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/list',
				'attrs'       => [ 'ordered' => true ],
				'innerHTML'   => '<ol><li>One</li><li>Two</li></ol>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/code',
				'attrs'       => [ 'language' => 'php' ],
				'innerHTML'   => '<pre class="wp-block-code"><code>$x = 1;</code></pre>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 444 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( '## Heading', $data['markdown'] );
		$this->assertStringContainsString( '1. One', $data['markdown'] );
		$this->assertStringContainsString( '```php', $data['markdown'] );
	}

	#[Test]
	public function it_handles_unicode_in_post_content(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 555 );
		$post->post_content = 'unicode';
		$post->post_title   = 'Unicode Post';
		$post->post_name    = 'unicode-post';
		$mock_posts[555]    = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Привет 你好 مرحبا</p>',
				'innerBlocks' => [],
			],
		];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 555 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Привет', $data['markdown'] );
		$this->assertStringContainsString( '你好', $data['markdown'] );
	}

	#[Test]
	public function it_handles_multiple_blocks(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 666 );
		$post->post_content = 'content';
		$post->post_title   = 'Multi Block';
		$mock_posts[666]    = $post;

		$mock_parsed_blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => [ 'level' => 1 ],
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
		$request->set_param( 'post_id', 666 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( '# Title', $data['markdown'] );
		$this->assertStringContainsString( 'Content', $data['markdown'] );
	}

	#[Test]
	public function it_handles_different_post_statuses(): void {
		global $mock_posts, $mock_parsed_blocks;

		$post              = new WP_Post( 777 );
		$post->post_title  = 'Private Post';
		$post->post_status = 'private';
		$post->post_name   = 'private-post';
		$mock_posts[777]   = $post;

		$mock_parsed_blocks = [];

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 777 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'private', $data['post_status'] );
	}
}
