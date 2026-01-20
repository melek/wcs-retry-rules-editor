<?php
/**
 * Plugin Name: WCS Retry Rules Editor
 * Plugin URI: https://github.com/melek/ratwaysite
 * Description: Visual editor for WooCommerce Subscriptions Failed Payment Retry Rules
 * Version: 1.0.4
 * Author: Melek
 * Author URI: https://iwantitratway.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wcs-retry-rules-editor
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 *
 * @package WCS_Retry_Rules_Editor
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'WCS_RRE_VERSION', '1.0.4' );
define( 'WCS_RRE_PLUGIN_FILE', __FILE__ );
define( 'WCS_RRE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCS_RRE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCS_RRE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * We use priority 20 to ensure WooCommerce and WooCommerce Subscriptions
 * are fully loaded before we check for their existence.
 */
add_action( 'plugins_loaded', 'wcs_rre_init', 20 );

/**
 * Initialize the plugin.
 *
 * Only loads if WooCommerce Subscriptions is active.
 */
function wcs_rre_init() {
	// Safety check: Only load if WooCommerce Subscriptions is active.
	if ( ! class_exists( 'WC_Subscriptions' ) ) {
		add_action( 'admin_notices', 'wcs_rre_missing_wcs_notice' );
		return;
	}

	// Load the plugin loader class.
	require_once WCS_RRE_PLUGIN_DIR . 'includes/class-wcs-rre-loader.php';

	// Initialize the plugin.
	WCS_RRE_Loader::instance()->init();
}

/**
 * Display admin notice when WooCommerce Subscriptions is not active.
 */
function wcs_rre_missing_wcs_notice() {
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php
			esc_html_e(
				'WCS Retry Rules Editor requires WooCommerce Subscriptions to be installed and active.',
				'wcs-retry-rules-editor'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Declare compatibility with WooCommerce HPOS.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);
