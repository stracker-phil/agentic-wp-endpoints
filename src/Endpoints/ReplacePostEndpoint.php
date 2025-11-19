<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AgenticEndpoints\Converter\MarkdownToBlocks;
use Exception;

/**
 * REST endpoint for replacing post content with Markdown converted to blocks.
 */
class ReplacePostEndpoint extends AbstractEndpoint {

	/**
	 * Markdown to blocks converter.
	 *
	 * @var MarkdownToBlocks
	 */
	private MarkdownToBlocks $converter;

	/**
	 * Constructor.
	 *
	 * @param MarkdownToBlocks $converter Markdown to blocks converter.
	 */
	public function __construct( MarkdownToBlocks $converter ) {
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
		return 'POST';
	}

	/**
	 * Get the arguments schema for the endpoint.
	 *
	 * @return array
	 */
	protected function get_args(): array {
		return [
			'markdown' => [
				'description'       => __( 'Markdown content to convert to Gutenberg blocks.', 'agentic-endpoints' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			],
		];
	}

	/**
	 * Handle the REST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ): WP_Error|WP_REST_Response {
		$markdown = $request->get_param( 'markdown' );

		if ( empty( $markdown ) ) {
			return $this->error(
				'empty_markdown',
				__( 'Markdown content cannot be empty.', 'agentic-endpoints' ),
				400
			);
		}

		try {
			$blocks = $this->converter->convert( $markdown );

			// Also generate the serialized block content for direct use.
			$block_content = $this->serialize_blocks( $blocks );

			return $this->success( [
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
