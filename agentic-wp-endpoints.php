<?php
/**
 * Plugin Name: Agentic Endpoints
 * Plugin URI: https://github.com/stracker-phil/agentic-endpoints
 * Description: Provides REST endpoints for efficient WP maintenance by an AI agent
 * Version: 1.0.0
 * Author: Philipp Stracker
 * Author URI: https://philippstracker.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: agentic-endpoints
 * Requires at least: 5.9
 * Requires PHP: 8.1
 */

declare( strict_types = 1 );

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AGENTIC_ENDPOINTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENTIC_ENDPOINTS_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader.
if ( ! file_exists( AGENTIC_ENDPOINTS_DIR . 'vendor/autoload.php' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="error"><p>';
		echo esc_html__( 'Agentic Endpoints requires Composer dependencies. Please run "composer install" in the plugin directory.', 'agentic-endpoints' );
		echo '</p></div>';
	} );

	return;
}

require_once AGENTIC_ENDPOINTS_DIR . 'vendor/autoload.php';

/**
 * Main plugin class extending WPDI Scope.
 *
 * Uses composition root pattern - bootstrap() is the only place
 * where service resolution happens.
 */
class Agentic_Endpoints_Plugin extends WPDI\Scope {

	/**
	 * Composition root - bootstraps the application.
	 *
	 * This is the ONLY place where service location happens.
	 * All services receive their dependencies via constructor injection.
	 *
	 * @param WPDI\Resolver $resolver Service resolver.
	 * @return void
	 */
	protected function bootstrap( WPDI\Resolver $resolver ): void {
		// Resolve the main application with all its dependencies.
		$app = $resolver->get( AgenticEndpoints\Application::class );

		// Initialize the application (registers WordPress hooks).
		$app->run();
	}
}

// Initialize the plugin.
new Agentic_Endpoints_Plugin( __FILE__ );

// Register activation hook.
register_activation_hook( __FILE__, static fn() => flush_rewrite_rules() );

// Register deactivation hook.
register_deactivation_hook( __FILE__, static fn() => flush_rewrite_rules() );
