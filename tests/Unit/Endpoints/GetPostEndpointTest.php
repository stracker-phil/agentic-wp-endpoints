<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use AgenticEndpoints\Endpoints\GetPostEndpoint;
use AgenticEndpoints\Converter\BlocksToMarkdown;
use League\HTMLToMarkdown\HtmlConverter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post;

/**
 * Unit tests for GetPostEndpoint.
 */
class GetPostEndpointTest extends TestCase {

	private GetPostEndpoint $endpoint;
	private BlocksToMarkdown $converter;

	protected function setUp(): void {
		parent::setUp();

		$htmlConverter   = new HtmlConverter( [
			'strip_tags' => false,
			'hard_break' => true,
		] );
		$this->converter = new BlocksToMarkdown( $htmlConverter );
		$this->endpoint  = new GetPostEndpoint( $this->converter );

		// Reset global mocks.
		global $registered_rest_routes, $mock_posts, $mock_parsed_blocks;
		$registered_rest_routes = [];
		$mock_posts             = [];
		$mock_parsed_blocks     = [];
	}

	// =========================
	// Route Configuration Tests
	// =========================

	/**
	 * GIVEN a GetPostEndpoint instance
	 * WHEN registering the route
	 * THEN it registers with correct namespace, route, method, and args
	 */
	#[Test]
	public function it_registers_correct_route_configuration(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount( 1, $registered_rest_routes );
		$this->assertEquals( 'agentic/v1', $registered_rest_routes[0]['namespace'] );
		$this->assertEquals( '/agentic-post', $registered_rest_routes[0]['route'] );
		$this->assertEquals( 'GET', $registered_rest_routes[0]['args']['methods'] );

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayHasKey( 'post_id', $args );
		$this->assertEquals( 'integer', $args['post_id']['type'] );
		$this->assertTrue( $args['post_id']['required'] );
		$this->assertArrayNotHasKey( 'content', $args );
	}

	// =========================
	// Handle Method Tests - Success Cases
	// =========================

	/**
	 * GIVEN a valid post with content
	 * WHEN calling the endpoint
	 * THEN post content is converted to markdown with all metadata
	 */
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

	/**
	 * GIVEN a valid post
	 * WHEN calling the endpoint
	 * THEN all expected metadata fields are returned
	 */
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

	/**
	 * GIVEN posts with supported or unsupported blocks
	 * WHEN converting to markdown
	 * THEN the has_html_fallback flag is set correctly
	 *
	 * @dataProvider html_fallback_provider
	 */
	#[Test]
	#[DataProvider( 'html_fallback_provider' )]
	public function it_sets_html_fallback_flag_correctly( array $blocks, bool $expected_fallback ): void {
		global $mock_posts, $mock_parsed_blocks;

		$post               = new WP_Post( 789 );
		$post->post_content = 'content';
		$post->post_title   = 'Test';
		$mock_posts[789]    = $post;

		$mock_parsed_blocks = $blocks;

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', 789 );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertEquals( $expected_fallback, $data['has_html_fallback'] );
	}

	public static function html_fallback_provider(): array {
		return [
			'unsupported block'    => [
				[
					[
						'blockName'   => 'core/gallery',
						'attrs'       => [],
						'innerHTML'   => '<figure>Gallery</figure>',
						'innerBlocks' => [],
					],
				],
				true,
			],
			'all supported blocks' => [
				[
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
				],
				false,
			],
		];
	}

	// =========================
	// Handle Method Tests - Error Cases
	// =========================

	/**
	 * GIVEN a non-existent post ID
	 * WHEN calling the endpoint
	 * THEN a not found error is returned
	 */
	#[Test]
	public function it_returns_error_for_non_existent_post(): void {
		global $mock_posts;
		$mock_posts = [];

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

	/**
	 * GIVEN a post with empty content
	 * WHEN calling the endpoint
	 * THEN empty markdown is returned
	 */
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

	/**
	 * GIVEN a post with blocks containing null blockName
	 * WHEN converting to markdown
	 * THEN those blocks are skipped
	 */
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

	/**
	 * GIVEN a post with complex block structure
	 * WHEN converting to markdown
	 * THEN all blocks are correctly converted
	 */
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

	/**
	 * GIVEN a post with unicode content
	 * WHEN converting to markdown
	 * THEN unicode characters are preserved
	 */
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

	/**
	 * GIVEN a post with multiple blocks
	 * WHEN converting to markdown
	 * THEN all blocks are included
	 */
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

	/**
	 * GIVEN a post with different status
	 * WHEN calling the endpoint
	 * THEN the status is correctly returned
	 */
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
