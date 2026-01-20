<?php
/**
 * Plugin Loader Class
 *
 * Handles loading all plugin components and initializing hooks.
 *
 * @package WCS_Retry_Rules_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin loader class.
 */
class WCS_RRE_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var WCS_RRE_Loader
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WCS_RRE_Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Load required class files.
	 */
	private function load_dependencies() {
		require_once WCS_RRE_PLUGIN_DIR . 'includes/class-wcs-rre-rules-manager.php';
		require_once WCS_RRE_PLUGIN_DIR . 'includes/class-wcs-rre-filter-handler.php';
		require_once WCS_RRE_PLUGIN_DIR . 'includes/class-wcs-rre-rest-controller.php';
		require_once WCS_RRE_PLUGIN_DIR . 'admin/class-wcs-rre-admin.php';
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		// Initialize the filter handler (applies custom rules).
		WCS_RRE_Filter_Handler::instance()->init();

		// Initialize REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Initialize admin interface.
		if ( is_admin() ) {
			WCS_RRE_Admin::instance()->init();
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$controller = new WCS_RRE_REST_Controller();
		$controller->register_routes();
	}
}
