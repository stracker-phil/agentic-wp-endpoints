<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AgenticEndpoints\Converter\BlocksToMarkdown;
use Exception;

/**
 * REST endpoint for converting Gutenberg blocks to Markdown.
 */
class ToMarkdownEndpoint extends AbstractEndpoint {

	/**
	 * Blocks to Markdown converter.
	 *
	 * @var BlocksToMarkdown
	 */
	private BlocksToMarkdown $converter;

	/**
	 * Constructor.
	 *
	 * @param BlocksToMarkdown $converter Blocks to Markdown converter.
	 */
	public function __construct( BlocksToMarkdown $converter ) {
		$this->converter = $converter;
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_route();
	}

	/**
	 * Get the route path.
	 *
	 * @return string
	 */
	protected function get_route(): string {
		return '/agentic-post';
	}

	/**
	 * Get the HTTP method(s) for this endpoint.
	 *
	 * @return string
	 */
	protected function get_methods(): string {
		return 'GET';
	}

	/**
	 * Get the arguments schema for the endpoint.
	 *
	 * @return array
	 */
	protected function get_args(): array {
		return [
			'post_id' => [
				'description'       => __( 'Post ID to convert to Markdown.', 'agentic-endpoints' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Handle the REST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
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
