<?php
/**
 * Admin Class
 *
 * Handles the admin interface for the retry rules editor.
 *
 * @package WCS_Retry_Rules_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin interface class.
 */
class WCS_RRE_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var WCS_RRE_Admin
	 */
	private static $instance = null;

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'wcs-retry-rules-editor';


	/**
	 * Get singleton instance.
	 *
	 * @return WCS_RRE_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
		add_action( 'admin_footer-plugins.php', array( $this, 'render_plugin_details_modals' ) );
		add_filter( 'woocommerce_subscription_settings', array( $this, 'add_subscription_settings' ), 50 );
		add_action( 'woocommerce_admin_field_wcs_rre_app', array( $this, 'render_app_field' ) );
		add_action( 'woocommerce_admin_field_wcs_rre_section_nav', array( $this, 'render_section_nav_field' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( $this->is_settings_page( $hook_suffix ) ) {
			// Enqueue CSS.
			wp_enqueue_style(
				'wcs-rre-admin',
				WCS_RRE_PLUGIN_URL . 'admin/css/admin.css',
				array(),
				WCS_RRE_VERSION
			);

			// Enqueue JavaScript.
			wp_enqueue_script(
				'wcs-rre-admin',
				WCS_RRE_PLUGIN_URL . 'admin/js/admin.js',
				array( 'wp-api-fetch' ),
				WCS_RRE_VERSION,
				true
			);

			// Pass data to JavaScript.
			wp_localize_script(
				'wcs-rre-admin',
				'wcsRreData',
				array(
					'apiNamespace' => 'wcs-rre/v1',
					'nonce'        => wp_create_nonce( 'wp_rest' ),
					'strings'      => array(
						'save'               => __( 'Save Rules', 'wcs-retry-rules-editor' ),
						'saving'             => __( 'Saving...', 'wcs-retry-rules-editor' ),
						'reset'              => __( 'Reset to Defaults', 'wcs-retry-rules-editor' ),
						'addRule'            => __( 'Add Rule', 'wcs-retry-rules-editor' ),
						'deleteRule'         => __( 'Delete', 'wcs-retry-rules-editor' ),
						'moveUp'             => __( 'Move Up', 'wcs-retry-rules-editor' ),
						'moveDown'           => __( 'Move Down', 'wcs-retry-rules-editor' ),
						'confirmReset'       => __( 'Are you sure you want to reset to WooCommerce Subscriptions defaults? This will remove all custom rules.', 'wcs-retry-rules-editor' ),
						'confirmDelete'      => __( 'Are you sure you want to delete this rule?', 'wcs-retry-rules-editor' ),
						'unsavedChanges'     => __( 'You have unsaved changes. Are you sure you want to leave?', 'wcs-retry-rules-editor' ),
						'saveSuccess'        => __( 'Rules saved successfully!', 'wcs-retry-rules-editor' ),
						'saveError'          => __( 'Error saving rules:', 'wcs-retry-rules-editor' ),
						'loadError'          => __( 'Error loading rules:', 'wcs-retry-rules-editor' ),
						'retryAfter'         => __( 'Retry After', 'wcs-retry-rules-editor' ),
						'customerEmail'      => __( 'Customer Email', 'wcs-retry-rules-editor' ),
						'adminEmail'         => __( 'Admin Email', 'wcs-retry-rules-editor' ),
						'orderStatus'        => __( 'Order Status', 'wcs-retry-rules-editor' ),
						'subscriptionStatus' => __( 'Subscription Status', 'wcs-retry-rules-editor' ),
						'rule'               => __( 'Rule', 'wcs-retry-rules-editor' ),
						'minutes'            => __( 'minutes', 'wcs-retry-rules-editor' ),
						'hours'              => __( 'hours', 'wcs-retry-rules-editor' ),
						'days'               => __( 'days', 'wcs-retry-rules-editor' ),
						'usingDefaults'      => __( 'Using WooCommerce Subscriptions defaults', 'wcs-retry-rules-editor' ),
						'usingCustom'        => __( 'Using custom rules', 'wcs-retry-rules-editor' ),
						'cumulativeTime'     => __( 'Cumulative time from payment failure:', 'wcs-retry-rules-editor' ),
						'timeline'           => __( 'Timeline Preview', 'wcs-retry-rules-editor' ),
						'paymentFails'       => __( 'Payment Fails', 'wcs-retry-rules-editor' ),
						'retryAttempt'       => __( 'Retry Attempt', 'wcs-retry-rules-editor' ),
						'afterAllRetries'    => __( 'After all retries: Order fails, invoice sent to customer', 'wcs-retry-rules-editor' ),
					'emailOverridesTitle' => __( 'Email Content Overrides', 'wcs-retry-rules-editor' ),
					'emailOverridesDesc'  => __( 'Preview defaults or override per rule.', 'wcs-retry-rules-editor' ),
					'emailCustomerLabel'  => __( 'Customer email', 'wcs-retry-rules-editor' ),
					'emailAdminLabel'     => __( 'Admin email', 'wcs-retry-rules-editor' ),
					'emailSubject'        => __( 'Subject', 'wcs-retry-rules-editor' ),
					'emailHeading'        => __( 'Heading', 'wcs-retry-rules-editor' ),
					'emailAdditional'     => __( 'Additional content', 'wcs-retry-rules-editor' ),
					'emailNoTemplate'     => __( 'No email sent for this rule.', 'wcs-retry-rules-editor' ),
					'emailOverrideToggle' => __( 'Override email content for this rule', 'wcs-retry-rules-editor' ),
					'emailPreview'        => __( 'Preview', 'wcs-retry-rules-editor' ),
						'emailPreviewTitle'   => __( 'Email Preview', 'wcs-retry-rules-editor' ),
						'emailPreviewLoad'    => __( 'Loading preview...', 'wcs-retry-rules-editor' ),
						'emailPreviewError'   => __( 'Error loading preview:', 'wcs-retry-rules-editor' ),
						'emailPlaceholders'   => __( 'Available placeholders:', 'wcs-retry-rules-editor' ),
						'emailPreviewClose'   => __( 'Close', 'wcs-retry-rules-editor' ),
						'emailPreviewSubject' => __( 'Subject', 'wcs-retry-rules-editor' ),
						'emailPreviewHeading' => __( 'Heading', 'wcs-retry-rules-editor' ),
					),
					'emailPlaceholders' => $this->get_email_placeholders(),
				)
			);
		}

		if ( 'plugins.php' === $hook_suffix ) {
			add_thickbox();
		}
	}

	/**
	 * Append Retry Rules UI to WooCommerce -> Settings -> Subscriptions.
	 *
	 * @param array $settings Existing settings.
	 * @return array
	 */
	public function add_subscription_settings( $settings ) {
		$current_section = $this->get_subscription_section();
		$nav_field       = array(
			'type' => 'wcs_rre_section_nav',
			'id'   => 'wcs_rre_section_nav',
		);

		if ( 'retry-rules' !== $current_section ) {
			return array_merge( array( $nav_field ), $settings );
		}

		return array(
			$nav_field,
			array(
				'title' => __( 'Retry Rules', 'wcs-retry-rules-editor' ),
				'type'  => 'title',
				'desc'  => __( 'Configure the retry rules for failed subscription payments. These rules determine when and how WooCommerce Subscriptions retries failed payments.', 'wcs-retry-rules-editor' ),
				'id'    => 'wcs_rre_rules_title',
			),
			array(
				'type' => 'wcs_rre_app',
				'id'   => 'wcs_rre_app',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wcs_rre_rules_end',
			),
		);
	}

	/**
	 * Render the app field for WooCommerce settings.
	 *
	 * @param array $field Field config.
	 */
	public function render_app_field( $field ) {
		echo '<tr valign="top" class="wcs-rre-app-row">';
		echo '<td colspan="2" class="forminp">';
		$this->render_admin_page();
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render the Subscriptions section nav (General | Retry Rules).
	 *
	 * @param array $field Field config.
	 */
	public function render_section_nav_field( $field ) {
		$current_section = $this->get_subscription_section();
		$base_url        = admin_url( 'admin.php?page=wc-settings&tab=subscriptions' );
		$links           = array(
			''            => __( 'General', 'wcs-retry-rules-editor' ),
			'retry-rules' => __( 'Retry Rules', 'wcs-retry-rules-editor' ),
		);

		echo '<tr valign="top" class="wcs-rre-section-nav">';
		echo '<td colspan="2" class="forminp">';
		echo '<ul class="subsubsub">';

		$index = 0;
		foreach ( $links as $section => $label ) {
			$url   = '' === $section ? $base_url : add_query_arg( 'section', $section, $base_url );
			$class = ( $section === $current_section ) ? 'current' : '';
			printf(
				'<li><a href="%s" class="%s">%s</a>%s</li>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label ),
				( ++$index < count( $links ) ) ? ' | ' : ''
			);
		}

		echo '</ul>';
		echo '<br class="clear" />';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Add plugin row meta links on the plugins page.
	 *
	 * @param array  $links Existing links.
	 * @param string $file  Plugin file.
	 * @return array
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( WCS_RRE_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$details_link = sprintf(
			'<a href="%s" class="thickbox">%s</a>',
			esc_url( '#TB_inline?width=700&height=600&inlineId=wcs-rre-plugin-details' ),
			esc_html__( 'View details', 'wcs-retry-rules-editor' )
		);

		$changelog_link = sprintf(
			'<a href="%s" class="thickbox">%s</a>',
			esc_url( '#TB_inline?width=700&height=600&inlineId=wcs-rre-plugin-changelog' ),
			esc_html__( 'View changelog', 'wcs-retry-rules-editor' )
		);

		$links[] = $details_link;
		$links[] = $changelog_link;

		return $links;
	}

	/**
	 * Render plugin details/changelog modals for Thickbox.
	 */
	public function render_plugin_details_modals() {
		$readme_path   = WCS_RRE_PLUGIN_DIR . 'README.md';
		$changelog_path = WCS_RRE_PLUGIN_DIR . 'CHANGELOG.md';

		$readme   = file_exists( $readme_path ) ? file_get_contents( $readme_path ) : '';
		$changelog = file_exists( $changelog_path ) ? file_get_contents( $changelog_path ) : '';
		?>
		<div id="wcs-rre-plugin-details" style="display:none;">
			<div class="wcs-rre-plugin-details">
				<h2><?php esc_html_e( 'WCS Retry Rules Editor', 'wcs-retry-rules-editor' ); ?></h2>
				<pre class="wcs-rre-plugin-pre"><?php echo esc_html( $readme ); ?></pre>
			</div>
		</div>

		<div id="wcs-rre-plugin-changelog" style="display:none;">
			<div class="wcs-rre-plugin-details">
				<h2><?php esc_html_e( 'Changelog', 'wcs-retry-rules-editor' ); ?></h2>
				<pre class="wcs-rre-plugin-pre"><?php echo esc_html( $changelog ); ?></pre>
			</div>
		</div>

		<style>
			.wcs-rre-plugin-details {
				padding: 10px 16px;
			}
			.wcs-rre-plugin-pre {
				background: #f6f7f7;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 12px;
				white-space: pre-wrap;
				font-size: 12px;
				line-height: 1.5;
			}
		</style>
		<?php
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		?>
		<div class="wcs-rre-wrap">
			<div id="wcs-rre-app">
				<div class="wcs-rre-loading">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading...', 'wcs-retry-rules-editor' ); ?>
				</div>
			</div>

			<noscript>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'JavaScript is required to use the Retry Rules Editor.', 'wcs-retry-rules-editor' ); ?></p>
				</div>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Check if we are on the Retry Rules settings screen.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return bool
	 */
	private function is_settings_page( $hook_suffix ) {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return false;
		}

		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = $this->get_subscription_section();
		return ( 'subscriptions' === $tab && 'retry-rules' === $section );
	}

	/**
	 * Get the current Subscriptions settings section slug.
	 *
	 * @return string
	 */
	private function get_subscription_section() {
		return isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
	}

	/**
	 * Get available placeholders for retry emails.
	 *
	 * @return array
	 */
	private function get_email_placeholders() {
		return array(
			'customer' => array( '{order_number}', '{order_date}', '{retry_time}' ),
			'admin'    => array( '{site_title}', '{order_number}', '{order_date}', '{retry_time}' ),
		);
	}
}
