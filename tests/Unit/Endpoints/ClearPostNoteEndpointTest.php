<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use AgenticEndpoints\Endpoints\ClearPostNoteEndpoint;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post;

/**
 * Unit tests for ClearPostNoteEndpoint.
 */
class ClearPostNoteEndpointTest extends TestCase {

	private ClearPostNoteEndpoint $endpoint;

	protected function setUp(): void {
		parent::setUp();

		$this->endpoint = new ClearPostNoteEndpoint();

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
	 * GIVEN a ClearPostNoteEndpoint instance
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
		$this->assertEquals( 'DELETE', $registered_rest_routes[0]['args']['methods'] );
	}

	// =========================
	// Handle Method Tests - Success Cases
	// =========================

	/**
	 * GIVEN a post with agent notes
	 * WHEN calling the endpoint
	 * THEN notes are deleted and success response returned
	 */
	#[Test]
	public function it_deletes_agent_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 123 );
		$mock_posts[123] = $post;

		// Set existing notes.
		$mock_post_meta[123]['_agent_notes'] = 'Some notes to delete';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 123 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 123, $data['post_id'] );
		$this->assertTrue( $data['deleted'] );

		// Verify notes were deleted.
		$this->assertArrayNotHasKey( '_agent_notes', $mock_post_meta[123] ?? [] );
	}

	/**
	 * GIVEN a post without agent notes
	 * WHEN calling the endpoint
	 * THEN success response is returned anyway
	 */
	#[Test]
	public function it_succeeds_when_no_notes_exist(): void {
		global $mock_posts;

		$post            = new WP_Post( 456 );
		$mock_posts[456] = $post;

		$request = new WP_REST_Request();
		$request->set_param( 'id', 456 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 456, $data['post_id'] );
		$this->assertTrue( $data['deleted'] );
	}

	/**
	 * GIVEN a post with empty string notes
	 * WHEN calling the endpoint
	 * THEN the meta key is deleted
	 */
	#[Test]
	public function it_deletes_empty_string_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 789 );
		$mock_posts[789] = $post;

		// Set empty notes.
		$mock_post_meta[789]['_agent_notes'] = '';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 789 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertTrue( $response->get_data()['deleted'] );
		$this->assertArrayNotHasKey( '_agent_notes', $mock_post_meta[789] ?? [] );
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
	 * GIVEN a post with very long notes
	 * WHEN calling the endpoint
	 * THEN all content is deleted successfully
	 */
	#[Test]
	public function it_deletes_very_long_notes(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 111 );
		$mock_posts[111] = $post;

		// Set very long notes.
		$mock_post_meta[111]['_agent_notes'] = str_repeat( 'Long notes. ', 10000 );

		$request = new WP_REST_Request();
		$request->set_param( 'id', 111 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertTrue( $response->get_data()['deleted'] );
		$this->assertArrayNotHasKey( '_agent_notes', $mock_post_meta[111] ?? [] );
	}

	/**
	 * GIVEN a post that has other meta values
	 * WHEN calling the endpoint
	 * THEN only agent_notes is deleted, other meta remains
	 */
	#[Test]
	public function it_only_deletes_agent_notes_meta(): void {
		global $mock_posts, $mock_post_meta;

		$post            = new WP_Post( 222 );
		$mock_posts[222] = $post;

		// Set multiple meta values.
		$mock_post_meta[222]['_agent_notes'] = 'Notes to delete';
		$mock_post_meta[222]['other_meta']   = 'Should remain';

		$request = new WP_REST_Request();
		$request->set_param( 'id', 222 );

		$response = $this->endpoint->handle( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertArrayNotHasKey( '_agent_notes', $mock_post_meta[222] );
		$this->assertEquals( 'Should remain', $mock_post_meta[222]['other_meta'] );
	}
}
