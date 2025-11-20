<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use AgenticEndpoints\Endpoints\ReplacePostNoteEndpoint;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post;

/**
 * Unit tests for ReplacePostNoteEndpoint.
 */
class ReplacePostNoteEndpointTest extends TestCase {

	private ReplacePostNoteEndpoint $endpoint;

	protected function setUp(): void {
		parent::setUp();

		$this->endpoint = new ReplacePostNoteEndpoint();

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
	 * GIVEN a ReplacePostNoteEndpoint instance
	 * WHEN registering the route
	 * THEN it registers with correct namespace, route, method, and args
	 */
	#[Test]
	public function it_registers_correct_route_configuration(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount( 1, $registered_rest_routes );
		$this->assertEquals( 'agentic/v1', $registered_rest_routes[0]['namespace'] );
		$this->assertEquals( '/agentic-post/(?P<id>\d+)/notes', $registered_rest_routes[0]['route'] );
		$this->assertEquals( 'POST', $registered_rest_routes[0]['args']['methods'] );

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayHasKey( 'notes', $args );
		$this->assertEquals( 'string', $args['notes']['type'] );
		$this->assertTrue( $args['notes']['required'] );
	}

	// =========================
	// Handle Method Tests - Success Cases
	// =========================

	/**
	 * GIVEN valid notes content
	 * WHEN calling the endpoint
	 * THEN notes are stored and success response returned
	 */
	#[Test]
	public function it_stores_agent_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 123 );
		$mock_posts[123] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 123 );
		$request->set_param( 'notes', 'Draft complete. Next: review and publish.' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 123, $data['post_id'] );
		$this->assertEquals( 'Draft complete. Next: review and publish.', $data['notes'] );

		// Verify notes were stored.
		$this->assertEquals( 'Draft complete. Next: review and publish.', $mock_post_meta[123]['_agent_notes'] );
	}

	/**
	 * GIVEN new notes content
	 * WHEN calling the endpoint on a post with existing notes
	 * THEN existing notes are replaced
	 */
	#[Test]
	public function it_replaces_existing_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 456 );
		$mock_posts[456] = $post;

		// Set existing notes.
		$mock_post_meta[456]['_agent_notes'] = 'Old notes';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 456 );
		$request->set_param( 'notes', 'New notes' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 'New notes', $mock_post_meta[456]['_agent_notes'] );
	}

	/**
	 * GIVEN empty string notes
	 * WHEN calling the endpoint
	 * THEN notes are set to empty string
	 */
	#[Test]
	public function it_allows_empty_string_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 789 );
		$mock_posts[789] = $post;

		// Set existing notes.
		$mock_post_meta[789]['_agent_notes'] = 'Has notes';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 789 );
		$request->set_param( 'notes', '' );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( '', $data['notes'] );
		$this->assertEquals( '', $mock_post_meta[789]['_agent_notes'] );
	}

	/**
	 * GIVEN multiline notes
	 * WHEN calling the endpoint
	 * THEN all content including newlines is stored
	 */
	#[Test]
	public function it_stores_multiline_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 111 );
		$mock_posts[111] = $post;

		$multiline_notes = "Step 1: Research\nStep 2: Draft\nStep 3: Review\nStep 4: Publish";

		$request = new WP_REST_Request();
		$request->set_param( 'id', 111 );
		$request->set_param( 'notes', $multiline_notes );

		$response = $this->endpoint->handle( $request );
		$data     = $response->get_data();

		$this->assertEquals( $multiline_notes, $data['notes'] );
		$this->assertEquals( $multiline_notes, $mock_post_meta[111]['_agent_notes'] );
	}

	/**
	 * GIVEN unicode notes
	 * WHEN calling the endpoint
	 * THEN unicode characters are preserved
	 */
	#[Test]
	public function it_preserves_unicode_in_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 222 );
		$mock_posts[222] = $post;

		$unicode_notes = 'Notes: Привет мир 你好世界 🎉';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 222 );
		$request->set_param( 'notes', $unicode_notes );

		$response = $this->endpoint->handle( $request );

		$this->assertEquals( $unicode_notes, $mock_post_meta[222]['_agent_notes'] );
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
		$request->set_param( 'notes', 'Some notes' );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'post_not_found', $response->get_error_code() );
		$this->assertEquals( [ 'status' => 404 ], $response->get_error_data() );
	}

	// =========================
	// Edge Case Tests
	// =========================

	/**
	 * GIVEN very long notes
	 * WHEN calling the endpoint
	 * THEN all content is stored
	 */
	#[Test]
	public function it_handles_very_long_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 333 );
		$mock_posts[333] = $post;

		$long_notes = str_repeat( 'This is a long note. ', 1000 );

		$request = new WP_REST_Request();
		$request->set_param( 'id', 333 );
		$request->set_param( 'notes', $long_notes );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( $long_notes, $mock_post_meta[333]['_agent_notes'] );
	}

	/**
	 * GIVEN notes with special characters
	 * WHEN calling the endpoint
	 * THEN special characters are preserved
	 */
	#[Test]
	public function it_preserves_special_characters(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 444 );
		$mock_posts[444] = $post;

		$special_notes = 'Notes with <html> & "quotes" and \'apostrophes\'';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 444 );
		$request->set_param( 'notes', $special_notes );

		$response = $this->endpoint->handle( $request );

		$this->assertEquals( $special_notes, $mock_post_meta[444]['_agent_notes'] );
	}
}
