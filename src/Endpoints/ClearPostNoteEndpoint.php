<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST endpoint for clearing agent notes for a post.
 */
class ClearPostNoteEndpoint extends AbstractEndpoint {

	protected function define_route(): string {
		return '/agentic-post/(?P<id>\d+)/notes';
	}

	protected function define_methods(): string {
		return 'DELETE';
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'id' );

		// Verify post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error(
				'post_not_found',
				__( 'Post not found.', 'agentic-endpoints' ),
				404
			);
		}

		// Delete agent notes.
		delete_post_meta( $post_id, '_agent_notes' );

		return $this->success( [
			'post_id' => $post_id,
			'deleted' => true,
		] );
	}
}
