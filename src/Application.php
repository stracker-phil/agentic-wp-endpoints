<?php

declare(strict_types=1);

namespace AgenticEndpoints;

use AgenticEndpoints\Endpoints\ToBlocksEndpoint;
use AgenticEndpoints\Endpoints\ToMarkdownEndpoint;

/**
 * Main application class for Agentic Endpoints plugin.
 *
 * Receives all dependencies via constructor injection.
 * Registers WordPress hooks for REST API endpoints.
 */
class Application {

    /**
     * REST endpoints to register.
     *
     * @var array
     */
    private array $endpoints;

    /**
     * Constructor.
     *
     * Dependencies are injected by WPDI autowiring.
     *
     * @param ToBlocksEndpoint   $to_blocks_endpoint   Markdown to blocks endpoint.
     * @param ToMarkdownEndpoint $to_markdown_endpoint Blocks to markdown endpoint.
     */
    public function __construct(
        ToBlocksEndpoint $to_blocks_endpoint,
        ToMarkdownEndpoint $to_markdown_endpoint
    ) {
        $this->endpoints = [
            $to_blocks_endpoint,
            $to_markdown_endpoint,
        ];
    }

    /**
     * Initialize the application.
     *
     * Registers WordPress hooks for REST API.
     *
     * @return void
     */
    public function run(): void {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Register all REST API routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        foreach ($this->endpoints as $endpoint) {
            $endpoint->register();
        }
    }
}
