<?php

declare( strict_types = 1 );

namespace AgenticEndpoints;

use WPDI\Scope;
use WPDI\Resolver;

/**
 * Main plugin class extending WPDI Scope.
 *
 * Uses composition root pattern - bootstrap() is the only place
 * where service resolution happens.
 */
class Plugin extends Scope {

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		parent::__construct( $plugin_file );
		$this->register_hooks( $plugin_file );
	}

	/**
	 * Register activation and deactivation hooks.
	 *
	 * @param string $plugin_file Path to main plugin file.
	 * @return void
	 */
	private function register_hooks( string $plugin_file ): void {
		register_activation_hook( $plugin_file, static fn() => flush_rewrite_rules() );
		register_deactivation_hook( $plugin_file, static fn() => flush_rewrite_rules() );
	}

	/**
	 * Composition root - bootstraps the application.
	 *
	 * This is the ONLY place where service location happens.
	 * All services receive their dependencies via constructor injection.
	 *
	 * @param Resolver $resolver Service resolver.
	 * @return void
	 */
	protected function bootstrap( Resolver $resolver ): void {
		// Resolve the main application with all its dependencies.
		$app = $resolver->get( Application::class );

		// Initialize the application (registers WordPress hooks).
		$app->run();
	}
}
