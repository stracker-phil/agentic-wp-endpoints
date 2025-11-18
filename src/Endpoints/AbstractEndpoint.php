<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Endpoints;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Abstract base class for REST endpoints.
 */
abstract class AbstractEndpoint {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'agentic/v1';

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Get the route path.
	 *
	 * @return string
	 */
	abstract protected function get_route(): string;

	/**
	 * Get the HTTP method(s) for this endpoint.
	 *
	 * @return string|array
	 */
	abstract protected function get_methods();

	/**
	 * Handle the REST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	abstract public function handle( WP_REST_Request $request );

	/**
	 * Get the arguments schema for the endpoint.
	 *
	 * @return array
	 */
	protected function get_args(): array {
		return [];
	}

	/**
	 * Check if the request has permission.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		// Default: require edit_posts capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'agentic-endpoints' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Register the route with WordPress.
	 *
	 * @return bool
	 */
	protected function register_route(): bool {
		return register_rest_route(
			$this->namespace,
			$this->get_route(),
			[
				'methods'             => $this->get_methods(),
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => $this->get_args(),
			]
		);
	}

	/**
	 * Create a success response.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Create an error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
