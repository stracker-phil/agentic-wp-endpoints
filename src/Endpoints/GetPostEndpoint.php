<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AgenticEndpoints\Converter\BlocksToMarkdown;
use Exception;

/**
 * REST endpoint for getting a post as Markdown.
 */
class GetPostEndpoint extends AbstractEndpoint {

	private BlocksToMarkdown $converter;

	public function __construct( BlocksToMarkdown $converter ) {
		$this->converter = $converter;
	}

	protected function define_route(): string {
		return '/agentic-post';
	}

	protected function define_methods(): string {
		return 'GET';
	}

	protected function define_args(): array {
		return [
			'post_id' => [
				'description'       => __( 'Post ID to convert to Markdown.', 'agentic-endpoints' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		];
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = $request->get_param( 'post_id' );

		try {
			// Get content from post.
			$post = get_post( $post_id );

			if ( ! $post ) {
				return $this->error(
					'post_not_found',
					__( 'Post not found.', 'agentic-endpoints' ),
					404
				);
			}

			// Parse blocks from content.
			$blocks = parse_blocks( $post->post_content );

			// Convert blocks to Markdown.
			$result = $this->converter->convert( $blocks );

			return $this->success( [
				'post_id'           => $post_id,
				'post_title'        => $post->post_title,
				'post_status'       => $post->post_status,
				'post_name'         => $post->post_name,
				'post_date'         => $post->post_date,
				'post_modified'     => $post->post_modified,
				'markdown'          => $result['markdown'],
				'has_html_fallback' => $result['has_html_fallback'],
			] );
		} catch ( Exception $e ) {
			return $this->error(
				'conversion_failed',
				sprintf( __( 'Failed to convert to Markdown: %s', 'agentic-endpoints' ), $e->getMessage() ),
				500
			);
		}
	}
}
