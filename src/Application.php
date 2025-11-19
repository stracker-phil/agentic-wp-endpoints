<?php

declare( strict_types = 1 );

namespace AgenticEndpoints;

use AgenticEndpoints\Endpoints\GetPostEndpoint;
use AgenticEndpoints\Endpoints\ReplacePostEndpoint;
use AgenticEndpoints\Endpoints\AbstractEndpoint;

/**
 * Main application class for Agentic Endpoints plugin.
 *
 * Receives all dependencies via constructor injection.
 * Registers WordPress hooks for REST API endpoints.
 */
class Application {

	/**
	 * @var AbstractEndpoint[]
	 */
	private array $endpoints;

	public function __construct(
		ReplacePostEndpoint $replace_post_endpoint,
		GetPostEndpoint $get_post_endpoint
	) {

		$this->endpoints = [
			$replace_post_endpoint,
			$get_post_endpoint,
		];
	}

	public function run(): void {
		add_action( 'rest_api_init', $this->register_rest_routes( ... ) );
	}

	/**
	 * Register all REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		foreach ( $this->endpoints as $endpoint ) {
			$endpoint->register();
		}
	}
}
