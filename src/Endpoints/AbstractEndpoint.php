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

	protected string $namespace = 'agentic/v1';

	abstract protected function define_route(): string;

	abstract protected function define_methods(): array|string;

	protected function define_args(): array {
		return [];
	}

	abstract public function handle( WP_REST_Request $request ): WP_Error|WP_REST_Response;

	/**
	 * Check if the request has permission.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ): WP_Error|bool {
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

	public function register(): bool {
		return register_rest_route(
			$this->namespace,
			$this->define_route(),
			[
				'methods'             => $this->define_methods(),
				'callback'            => $this->handle( ... ),
				'permission_callback' => $this->check_permission( ... ),
				'args'                => $this->define_args(),
			]
		);
	}

	protected function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	protected function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
