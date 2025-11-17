<?php

declare(strict_types=1);

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AgenticEndpoints\Converter\BlocksToMarkdown;

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
    public function __construct(BlocksToMarkdown $converter) {
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
        return '/convert/to-markdown';
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
                'description'       => __('Post ID to convert to Markdown.', 'agentic-endpoints'),
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
            'content' => [
                'description'       => __('Raw block content to convert to Markdown.', 'agentic-endpoints'),
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'wp_kses_post',
            ],
        ];
    }

    /**
     * Handle the REST request.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle(WP_REST_Request $request) {
        $post_id = $request->get_param('post_id');
        $content = $request->get_param('content');

        // Either post_id or content must be provided.
        if (empty($post_id) && empty($content)) {
            return $this->error(
                'missing_parameter',
                __('Either post_id or content parameter is required.', 'agentic-endpoints'),
                400
            );
        }

        try {
            $blocks = [];

            if (! empty($post_id)) {
                // Get content from post.
                $post = get_post($post_id);

                if (! $post) {
                    return $this->error(
                        'post_not_found',
                        __('Post not found.', 'agentic-endpoints'),
                        404
                    );
                }

                $content = $post->post_content;
            }

            // Parse blocks from content.
            $blocks = parse_blocks($content);

            // Convert blocks to Markdown.
            $result = $this->converter->convert($blocks);

            $response_data = [
                'markdown'          => $result['markdown'],
                'has_html_fallback' => $result['has_html_fallback'],
            ];

            if (! empty($post_id)) {
                $response_data['post_id'] = $post_id;
                $response_data['post_title'] = $post->post_title;
            }

            return $this->success($response_data);
        } catch (\Exception $e) {
            return $this->error(
                'conversion_failed',
                sprintf(__('Failed to convert to Markdown: %s', 'agentic-endpoints'), $e->getMessage()),
                500
            );
        }
    }
}
