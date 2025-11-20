<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AgenticEndpoints\Converter\MarkdownToBlocks;
use Exception;

/**
 * REST endpoint for replacing the post-content with Markdown converted to blocks.
 */
class ReplacePostEndpoint extends AbstractEndpoint {

	private MarkdownToBlocks $converter;

	public function __construct( MarkdownToBlocks $converter ) {
		$this->converter = $converter;
	}

	protected function define_route(): string {
		return '/agentic-post/(?P<id>\d+)';
	}

	protected function define_methods(): string {
		return 'POST';
	}

	protected function define_args(): array {
		return [
			'markdown'    => [
				'description'       => __( 'Markdown content to convert to Gutenberg blocks.', 'agentic-endpoints' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'agent_notes' => [
				'description'       => __( 'Notes for the AI agent to track progress.', 'agentic-endpoints' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'trim',
			],
		];
	}

	public function handle( WP_REST_Request $request ): WP_Error|WP_REST_Response {
		$post_id     = (int) $request->get_param( 'id' );
		$markdown    = $request->get_param( 'markdown' );
		$agent_notes = $request->get_param( 'agent_notes' );

		// Verify post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error(
				'post_not_found',
				__( 'Post not found.', 'agentic-endpoints' ),
				404
			);
		}

		if ( empty( $markdown ) ) {
			return $this->error(
				'empty_markdown',
				__( 'Markdown content cannot be empty.', 'agentic-endpoints' ),
				400
			);
		}

		try {
			$blocks = $this->converter->convert( $markdown );

			// Generate the serialized block content.
			$block_content = $this->serialize_blocks( $blocks );

			// Update post content.
			wp_update_post( [
				'ID'           => $post_id,
				'post_content' => $block_content,
			] );

			// Update agent notes if provided.
			if ( $agent_notes !== null ) {
				update_post_meta( $post_id, '_agent_notes', $agent_notes );
			}

			return $this->success( [
				'post_id'       => $post_id,
				'blocks'        => $blocks,
				'block_content' => $block_content,
				'block_count'   => count( $blocks ),
			] );
		} catch ( Exception $e ) {
			return $this->error(
				'conversion_failed',
				sprintf( __( 'Failed to convert Markdown: %s', 'agentic-endpoints' ), $e->getMessage() ),
				500
			);
		}
	}

	/**
	 * Serialize blocks to Gutenberg block format.
	 *
	 * @param array $blocks Array of blocks.
	 * @return string Serialized block content.
	 */
	private function serialize_blocks( array $blocks ): string {
		$output = '';

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';
			$attrs      = $block['attrs'] ?? [];
			$inner_html = $block['innerHTML'] ?? '';

			if ( empty( $block_name ) ) {
				continue;
			}

			// Create block comment opening.
			if ( ! empty( $attrs ) ) {
				$attrs_json = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				$output     .= sprintf( '<!-- wp:%s %s -->', $block_name, $attrs_json );
			} else {
				$output .= sprintf( '<!-- wp:%s -->', $block_name );
			}

			$output .= "\n" . $inner_html . "\n";

			// Create block comment closing.
			$output .= sprintf( '<!-- /wp:%s -->', $block_name );
			$output .= "\n\n";
		}

		return trim( $output );
	}
}
