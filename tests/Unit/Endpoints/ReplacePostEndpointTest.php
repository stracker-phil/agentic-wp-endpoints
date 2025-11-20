<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use AgenticEndpoints\Endpoints\ReplacePostEndpoint;
use AgenticEndpoints\Converter\MarkdownToBlocks;
use Parsedown;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post;

/**
 * Unit tests for ReplacePostEndpoint.
 */
class ReplacePostEndpointTest extends TestCase {

	private ReplacePostEndpoint $endpoint;
	private MarkdownToBlocks $converter;

	protected function setUp(): void {
		parent::setUp();

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true );
		$this->converter = new MarkdownToBlocks( $parsedown );
		$this->endpoint  = new ReplacePostEndpoint( $this->converter );

		// Reset global mocks.
		global $registered_rest_routes, $mock_posts, $mock_post_meta;
		$registered_rest_routes = [];
		$mock_posts             = [];
		$mock_post_meta         = [];
	}

	// =========================
	// Route Configuration Tests
	// =========================

	/**
	 * GIVEN a ReplacePostEndpoint instance
	 * WHEN registering the route
	 * THEN it registers with correct namespace, route, method, and args
	 */
	#[Test]
	public function it_registers_correct_route_configuration(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount( 1, $registered_rest_routes );
		$this->assertEquals( 'agentic/v1', $registered_rest_routes[0]['namespace'] );
		$this->assertEquals( '/agentic-post/(?P<id>\d+)', $registered_rest_routes[0]['route'] );
		$this->assertEquals( 'POST', $registered_rest_routes[0]['args']['methods'] );

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayHasKey( 'markdown', $args );
		$this->assertEquals( 'string', $args['markdown']['type'] );
		$this->assertTrue( $args['markdown']['required'] );
		$this->assertArrayHasKey( 'agent_notes', $args );
		$this->assertFalse( $args['agent_notes']['required'] );
	}

	// =========================
	// Handle Method Tests - Success Cases
	// =========================

	/**
	 * GIVEN valid markdown input and existing post
	 * WHEN calling the endpoint handle method
	 * THEN a success response is returned with blocks data
	 */
	#[Test]
	public function it_converts_simple_markdown_to_blocks(): void {
		global $mock_posts;

		$post               = new WP_Post( 123 );
		$post->post_content = '';
		$post->post_title   = 'Test';
		$mock_posts[123]    = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 123 );
		$request->set_param( 'markdown', '# Hello World' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'post_id', $data );
		$this->assertArrayHasKey( 'blocks', $data );
		$this->assertArrayHasKey( 'block_content', $data );
		$this->assertArrayHasKey( 'block_count', $data );
		$this->assertEquals( 123, $data['post_id'] );
	}

	/**
	 * GIVEN markdown with multiple elements
	 * WHEN calling the endpoint
	 * THEN the correct number of blocks is returned
	 */
	#[Test]
	public function it_returns_correct_block_count(): void {
		global $mock_posts;

		$post            = new WP_Post( 456 );
		$mock_posts[456] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 456 );
		$request->set_param( 'markdown', "# Title\n\nParagraph\n\n## Subtitle" );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertEquals( 3, $data['block_count'] );
		$this->assertCount( 3, $data['blocks'] );
	}

	/**
	 * GIVEN markdown input
	 * WHEN converting to blocks
	 * THEN each block has the required structure keys
	 */
	#[Test]
	public function it_returns_valid_block_structure(): void {
		global $mock_posts;

		$post            = new WP_Post( 111 );
		$mock_posts[111] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 111 );
		$request->set_param( 'markdown', '# Test' );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();
		$block    = $data['blocks'][0];

		$this->assertArrayHasKey( 'blockName', $block );
		$this->assertArrayHasKey( 'attrs', $block );
		$this->assertArrayHasKey( 'innerBlocks', $block );
		$this->assertArrayHasKey( 'innerHTML', $block );
		$this->assertArrayHasKey( 'innerContent', $block );
	}

	/**
	 * GIVEN markdown input
	 * WHEN converting to blocks
	 * THEN serialized block content contains proper WordPress block comments
	 *
	 * @dataProvider serialization_provider
	 */
	#[Test]
	#[DataProvider( 'serialization_provider' )]
	public function it_serializes_blocks_correctly( string $markdown, string $expected_block_type, ?string $expected_attr ): void {
		global $mock_posts;

		$post            = new WP_Post( 222 );
		$mock_posts[222] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 222 );
		$request->set_param( 'markdown', $markdown );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( "<!-- wp:{$expected_block_type}", $data['block_content'] );
		$this->assertStringContainsString( "<!-- /wp:{$expected_block_type} -->", $data['block_content'] );

		if ( $expected_attr !== null ) {
			$this->assertStringContainsString( $expected_attr, $data['block_content'] );
		}

		// Should not have trailing whitespace.
		$this->assertEquals( $data['block_content'], trim( $data['block_content'] ) );
	}

	public static function serialization_provider(): array {
		return [
			'heading with level'      => [ '## Level 2 Heading', 'core/heading', '"level":2' ],
			'paragraph without attrs' => [ 'Simple paragraph', 'core/paragraph', null ],
			'paragraph with html'     => [
				'Test paragraph',
				'core/paragraph',
				'<p>Test paragraph</p>',
			],
		];
	}

	/**
	 * GIVEN markdown input and existing post
	 * WHEN calling the endpoint
	 * THEN the post content is updated with serialized blocks
	 */
	#[Test]
	public function it_updates_post_content(): void {
		global $mock_posts;

		$post               = new WP_Post( 333 );
		$post->post_content = 'Old content';
		$mock_posts[333]    = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 333 );
		$request->set_param( 'markdown', '# New Title' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		// Post content should be updated via wp_update_post mock.
		$this->assertStringContainsString( 'wp:core/heading', $mock_posts[333]->post_content );
	}

	/**
	 * GIVEN complex markdown with multiple block types
	 * WHEN converting to blocks
	 * THEN all block types are correctly identified
	 */
	#[Test]
	public function it_converts_complex_markdown(): void {
		global $mock_posts;

		$post            = new WP_Post( 444 );
		$mock_posts[444] = $post;

		$markdown = <<<MD
# Title

Introduction paragraph.

```php
echo "code";
```

- Item 1
- Item 2
MD;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 444 );
		$request->set_param( 'markdown', $markdown );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertEquals( 4, $data['block_count'] );

		$block_names = array_column( $data['blocks'], 'blockName' );
		$this->assertContains( 'core/heading', $block_names );
		$this->assertContains( 'core/paragraph', $block_names );
		$this->assertContains( 'core/code', $block_names );
		$this->assertContains( 'core/list', $block_names );
	}

	// =========================
	// Agent Notes Tests
	// =========================

	/**
	 * GIVEN markdown and agent_notes
	 * WHEN calling the endpoint
	 * THEN agent notes are stored in post meta
	 */
	#[Test]
	public function it_stores_agent_notes_when_provided(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 555 );
		$mock_posts[555] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 555 );
		$request->set_param( 'markdown', '# Test' );
		$request->set_param( 'agent_notes', 'Initial draft. Next step: review.' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 'Initial draft. Next step: review.', $mock_post_meta[555]['_agent_notes'] );
	}

	/**
	 * GIVEN markdown without agent_notes
	 * WHEN calling the endpoint on a post with existing notes
	 * THEN existing notes are preserved
	 */
	#[Test]
	public function it_preserves_existing_notes_when_not_provided(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 666 );
		$mock_posts[666] = $post;

		// Set existing notes.
		$mock_post_meta[666]['_agent_notes'] = 'Existing notes';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 666 );
		$request->set_param( 'markdown', '# Updated content' );
		// Note: agent_notes not set.

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		// Notes should remain unchanged.
		$this->assertEquals( 'Existing notes', $mock_post_meta[666]['_agent_notes'] );
	}

	/**
	 * GIVEN markdown and empty agent_notes
	 * WHEN calling the endpoint
	 * THEN notes are updated to empty string
	 */
	#[Test]
	public function it_clears_notes_when_empty_string_provided(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 777 );
		$mock_posts[777] = $post;

		// Set existing notes.
		$mock_post_meta[777]['_agent_notes'] = 'Old notes';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 777 );
		$request->set_param( 'markdown', '# Test' );
		$request->set_param( 'agent_notes', '' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( '', $mock_post_meta[777]['_agent_notes'] );
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
		$request->set_param( 'id', 999 );
		$request->set_param( 'markdown', '# Test' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'post_not_found', $response->get_error_code() );
		$this->assertEquals( [ 'status' => 404 ], $response->get_error_data() );
	}

	/**
	 * GIVEN empty or null markdown input
	 * WHEN calling the endpoint
	 * THEN an error response is returned
	 *
	 * @dataProvider empty_markdown_provider
	 */
	#[Test]
	#[DataProvider( 'empty_markdown_provider' )]
	public function it_returns_error_for_empty_markdown( ?string $markdown ): void {
		global $mock_posts;

		$post            = new WP_Post( 888 );
		$mock_posts[888] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 888 );
		if ( $markdown !== null ) {
			$request->set_param( 'markdown', $markdown );
		}

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'empty_markdown', $response->get_error_code() );
		$this->assertEquals( [ 'status' => 400 ], $response->get_error_data() );
	}

	public static function empty_markdown_provider(): array {
		return [
			'empty string'   => [ '' ],
			'null (not set)' => [ null ],
		];
	}

	/**
	 * GIVEN whitespace-only or newlines-only markdown
	 * WHEN calling the endpoint
	 * THEN a success response with zero blocks is returned
	 *
	 * @dataProvider whitespace_markdown_provider
	 */
	#[Test]
	#[DataProvider( 'whitespace_markdown_provider' )]
	public function it_returns_zero_blocks_for_whitespace_only_markdown( string $markdown ): void {
		global $mock_posts;

		$post            = new WP_Post( 999 );
		$mock_posts[999] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 999 );
		$request->set_param( 'markdown', $markdown );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertEquals( 0, $data['block_count'] );
	}

	public static function whitespace_markdown_provider(): array {
		return [
			'spaces only'             => [ '   ' ],
			'newlines only'           => [ "\n\n" ],
			'whitespace and newlines' => [ "\n\n   \n\n" ],
		];
	}

	// =========================
	// Edge Case Tests
	// =========================

	/**
	 * GIVEN markdown with special content
	 * WHEN converting to blocks
	 * THEN content is handled correctly
	 *
	 * @dataProvider special_content_provider
	 */
	#[Test]
	#[DataProvider( 'special_content_provider' )]
	public function it_handles_special_content( string $markdown, int $expected_count ): void {
		global $mock_posts;

		$post              = new WP_Post( 1000 );
		$mock_posts[1000] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 1000 );
		$request->set_param( 'markdown', $markdown );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertEquals( $expected_count, $data['block_count'] );
	}

	public static function special_content_provider(): array {
		return [
			'special characters' => [ 'Text with <special> & "chars"', 1 ],
			'unicode content'    => [ '# Привет мир 你好世界', 1 ],
			'very long content'  => [ str_repeat( "Paragraph\n\n", 100 ), 100 ],
		];
	}

	/**
	 * GIVEN markdown with unicode content
	 * WHEN converting to blocks
	 * THEN unicode characters are preserved in HTML
	 */
	#[Test]
	public function it_preserves_unicode_in_blocks(): void {
		global $mock_posts;

		$post              = new WP_Post( 1001 );
		$mock_posts[1001] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 1001 );
		$request->set_param( 'markdown', '# Привет мир 你好世界' );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Привет', $data['blocks'][0]['innerHTML'] );
		$this->assertStringContainsString( '你好', $data['blocks'][0]['innerHTML'] );
	}
}
