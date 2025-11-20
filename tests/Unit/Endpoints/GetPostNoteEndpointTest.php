<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use AgenticEndpoints\Endpoints\GetPostNoteEndpoint;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post;

/**
 * Unit tests for GetPostNoteEndpoint.
 */
class GetPostNoteEndpointTest extends TestCase {

	private GetPostNoteEndpoint $endpoint;

	protected function setUp(): void {
		parent::setUp();

		$this->endpoint = new GetPostNoteEndpoint();

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
	 * GIVEN a GetPostNoteEndpoint instance
	 * WHEN registering the route
	 * THEN it registers with correct namespace, route, and method
	 */
	#[Test]
	public function it_registers_correct_route_configuration(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount( 1, $registered_rest_routes );
		$this->assertEquals( 'agentic/v1', $registered_rest_routes[0]['namespace'] );
		$this->assertEquals( '/agentic-post/(?P<id>\d+)/notes', $registered_rest_routes[0]['route'] );
		$this->assertEquals( 'GET', $registered_rest_routes[0]['args']['methods'] );
	}

	// =========================
	// Handle Method Tests - Success Cases
	// =========================

	/**
	 * GIVEN a post with agent notes
	 * WHEN calling the endpoint
	 * THEN the notes are returned as a raw string
	 */
	#[Test]
	public function it_returns_agent_notes_as_string(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 123 );
		$mock_posts[123] = $post;

		$mock_post_meta[123]['_agent_notes'] = 'Initial draft. Next step: proofread.';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 123 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Initial draft. Next step: proofread.', $response->get_data() );
	}

	/**
	 * GIVEN a post without agent notes
	 * WHEN calling the endpoint
	 * THEN an empty string is returned
	 */
	#[Test]
	public function it_returns_empty_string_when_no_notes(): void {
		global $mock_posts;

		$post            = new WP_Post( 456 );
		$mock_posts[456] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 456 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( '', $response->get_data() );
	}

	/**
	 * GIVEN a post with multiline notes
	 * WHEN calling the endpoint
	 * THEN all content is returned including newlines
	 */
	#[Test]
	public function it_preserves_multiline_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 789 );
		$mock_posts[789] = $post;

		$multiline_notes = "Line 1: Initial draft\nLine 2: Needs review\nLine 3: Todo - add images";
		$mock_post_meta[789]['_agent_notes'] = $multiline_notes;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 789 );

		$response = $this->endpoint->handle( $request );

		$this->assertEquals( $multiline_notes, $response->get_data() );
	}

	/**
	 * GIVEN a post with unicode notes
	 * WHEN calling the endpoint
	 * THEN unicode characters are preserved
	 */
	#[Test]
	public function it_preserves_unicode_in_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 111 );
		$mock_posts[111] = $post;

		$unicode_notes = 'Notes with unicode: Привет мир 你好世界 🎉';
		$mock_post_meta[111]['_agent_notes'] = $unicode_notes;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 111 );

		$response = $this->endpoint->handle( $request );

		$this->assertEquals( $unicode_notes, $response->get_data() );
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

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'post_not_found', $response->get_error_code() );
		$this->assertEquals( [ 'status' => 404 ], $response->get_error_data() );
	}

	// =========================
	// Edge Case Tests
	// =========================

	/**
	 * GIVEN a post with empty string notes
	 * WHEN calling the endpoint
	 * THEN empty string is returned (not null)
	 */
	#[Test]
	public function it_returns_empty_string_for_empty_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 222 );
		$mock_posts[222] = $post;

		$mock_post_meta[222]['_agent_notes'] = '';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 222 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( '', $response->get_data() );
	}

	/**
	 * GIVEN a post with very long notes
	 * WHEN calling the endpoint
	 * THEN all content is returned
	 */
	#[Test]
	public function it_handles_very_long_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 333 );
		$mock_posts[333] = $post;

		$long_notes = str_repeat( 'This is a long note. ', 1000 );
		$mock_post_meta[333]['_agent_notes'] = $long_notes;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 333 );

		$response = $this->endpoint->handle( $request );

		$this->assertEquals( $long_notes, $response->get_data() );
	}
}
