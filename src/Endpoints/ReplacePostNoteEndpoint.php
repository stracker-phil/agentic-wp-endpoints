<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST endpoint for replacing agent notes for a post.
 */
class ReplacePostNoteEndpoint extends AbstractEndpoint {

	protected function define_route(): string {
		return '/agentic-post/(?P<id>\d+)/notes';
	}

	protected function define_methods(): string {
		return 'POST';
	}

	protected function define_args(): array {
		return [
			'notes' => [
				'description'       => __( 'Notes content for the AI agent.', 'agentic-endpoints' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'trim',
			],
		];
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$notes   = $request->get_param( 'notes' );

		// Verify post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error(
				'post_not_found',
				__( 'Post not found.', 'agentic-endpoints' ),
				404
			);
		}

		// Update agent notes.
		update_post_meta( $post_id, '_agent_notes', $notes );

		return $this->success( [
			'post_id' => $post_id,
			'notes'   => $notes,
		] );
	}
}
