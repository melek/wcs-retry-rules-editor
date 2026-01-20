<?php
/**
 * Rules Manager Class
 *
 * Handles CRUD operations for retry rules configuration.
 *
 * @package WCS_Retry_Rules_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages retry rules storage and retrieval.
 */
class WCS_RRE_Rules_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var WCS_RRE_Rules_Manager
	 */
	private static $instance = null;

	/**
	 * Option key for storing active rules.
	 */
	const OPTION_KEY = 'wcs_rre_active_rules';

	/**
	 * Minimum retry interval in seconds (5 minutes).
	 */
	const MIN_INTERVAL = 300;

	/**
	 * Valid customer email templates.
	 *
	 * @var array
	 */
	private $valid_customer_emails = array(
		'',
		'WCS_Email_Customer_Payment_Retry',
	);

	/**
	 * Valid admin email templates.
	 *
	 * @var array
	 */
	private $valid_admin_emails = array(
		'',
		'WCS_Email_Payment_Retry',
	);

	/**
	 * Optional email override fields.
	 *
	 * @var array
	 */
	private $email_override_fields = array(
		'email_override_customer',
		'email_override_admin',
		'email_subject_customer',
		'email_heading_customer',
		'email_additional_content_customer',
		'email_subject_admin',
		'email_heading_admin',
		'email_additional_content_admin',
	);

	/**
	 * Get singleton instance.
	 *
	 * @return WCS_RRE_Rules_Manager
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
	 * Get the active custom rules.
	 *
	 * @return array Array of rules or empty array if none configured.
	 */
	public function get_active_rules() {
		$config = get_option( self::OPTION_KEY, array() );
		return isset( $config['rules'] ) && is_array( $config['rules'] ) ? $config['rules'] : array();
	}

	/**
	 * Check if custom rules are configured.
	 *
	 * @return bool True if custom rules exist.
	 */
	public function has_custom_rules() {
		$rules = $this->get_active_rules();
		return ! empty( $rules );
	}

	/**
	 * Save rules configuration.
	 *
	 * @param array $rules Array of rule configurations.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function save_rules( $rules ) {
		// Validate all rules first.
		foreach ( $rules as $index => $rule ) {
			$validation = $this->validate_rule( $rule );
			if ( is_wp_error( $validation ) ) {
				return new WP_Error(
					'invalid_rule',
					sprintf(
						/* translators: 1: rule number, 2: error message */
						__( 'Rule %1$d: %2$s', 'wcs-retry-rules-editor' ),
						$index + 1,
						$validation->get_error_message()
					)
				);
			}
		}

		// Sanitize all rules.
		$sanitized_rules = array_map( array( $this, 'sanitize_rule' ), $rules );

		// Build configuration object.
		$config = array(
			'rules'       => $sanitized_rules,
			'modified_at' => current_time( 'c' ),
			'modified_by' => get_current_user_id(),
		);

		// Save to database.
		$result = update_option( self::OPTION_KEY, $config );

		// Clear cache.
		wp_cache_delete( self::OPTION_KEY, 'options' );

		return true;
	}

	/**
	 * Delete all custom rules (revert to WCS defaults).
	 *
	 * @return bool True on success.
	 */
	public function clear_rules() {
		delete_option( self::OPTION_KEY );
		wp_cache_delete( self::OPTION_KEY, 'options' );
		return true;
	}

	/**
	 * Validate a single rule.
	 *
	 * @param array $rule Rule configuration.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_rule( $rule ) {
		// Check required fields.
		$required = array(
			'retry_after_interval',
			'email_template_customer',
			'email_template_admin',
			'status_to_apply_to_order',
			'status_to_apply_to_subscription',
		);

		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $rule ) ) {
				return new WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: field name */
						__( 'Missing required field: %s', 'wcs-retry-rules-editor' ),
						$field
					)
				);
			}
		}

		// Validate interval (minimum 5 minutes).
		if ( ! is_numeric( $rule['retry_after_interval'] ) || $rule['retry_after_interval'] < self::MIN_INTERVAL ) {
			return new WP_Error(
				'invalid_interval',
				__( 'Retry interval must be at least 5 minutes (300 seconds)', 'wcs-retry-rules-editor' )
			);
		}

		// Validate email templates.
		if ( ! in_array( $rule['email_template_customer'], $this->valid_customer_emails, true ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Invalid customer email template', 'wcs-retry-rules-editor' )
			);
		}

		if ( ! in_array( $rule['email_template_admin'], $this->valid_admin_emails, true ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Invalid admin email template', 'wcs-retry-rules-editor' )
			);
		}

		// Validate order status.
		$valid_order_statuses = $this->get_valid_order_statuses();
		$order_status         = str_replace( 'wc-', '', $rule['status_to_apply_to_order'] );
		if ( ! in_array( $order_status, $valid_order_statuses, true ) ) {
			return new WP_Error(
				'invalid_status',
				__( 'Invalid order status', 'wcs-retry-rules-editor' )
			);
		}

		// Validate subscription status.
		$valid_sub_statuses = $this->get_valid_subscription_statuses();
		if ( ! in_array( $rule['status_to_apply_to_subscription'], $valid_sub_statuses, true ) ) {
			return new WP_Error(
				'invalid_status',
				__( 'Invalid subscription status', 'wcs-retry-rules-editor' )
			);
		}

		foreach ( $this->email_override_fields as $field ) {
			if ( isset( $rule[ $field ] ) && ! is_string( $rule[ $field ] ) && ! is_bool( $rule[ $field ] ) && ! is_numeric( $rule[ $field ] ) ) {
				return new WP_Error(
					'invalid_email_content',
					__( 'Email override fields must be text.', 'wcs-retry-rules-editor' )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize a rule for storage.
	 *
	 * @param array $rule Rule configuration.
	 * @return array Sanitized rule.
	 */
	public function sanitize_rule( $rule ) {
		$sanitized = array(
			'retry_after_interval'            => absint( $rule['retry_after_interval'] ),
			'email_template_customer'         => sanitize_text_field( $rule['email_template_customer'] ),
			'email_template_admin'            => sanitize_text_field( $rule['email_template_admin'] ),
			'status_to_apply_to_order'        => sanitize_key( str_replace( 'wc-', '', $rule['status_to_apply_to_order'] ) ),
			'status_to_apply_to_subscription' => sanitize_key( $rule['status_to_apply_to_subscription'] ),
		);

		$sanitized['email_subject_customer']            = isset( $rule['email_subject_customer'] ) ? sanitize_text_field( $rule['email_subject_customer'] ) : '';
		$sanitized['email_heading_customer']            = isset( $rule['email_heading_customer'] ) ? sanitize_text_field( $rule['email_heading_customer'] ) : '';
		$sanitized['email_additional_content_customer'] = isset( $rule['email_additional_content_customer'] ) ? wp_kses_post( $rule['email_additional_content_customer'] ) : '';
		$sanitized['email_subject_admin']               = isset( $rule['email_subject_admin'] ) ? sanitize_text_field( $rule['email_subject_admin'] ) : '';
		$sanitized['email_heading_admin']               = isset( $rule['email_heading_admin'] ) ? sanitize_text_field( $rule['email_heading_admin'] ) : '';
		$sanitized['email_additional_content_admin']    = isset( $rule['email_additional_content_admin'] ) ? wp_kses_post( $rule['email_additional_content_admin'] ) : '';
		$sanitized['email_override_customer']           = isset( $rule['email_override_customer'] ) ? (bool) $rule['email_override_customer'] : false;
		$sanitized['email_override_admin']              = isset( $rule['email_override_admin'] ) ? (bool) $rule['email_override_admin'] : false;

		return $sanitized;
	}

	/**
	 * Get WooCommerce Subscriptions default retry rules.
	 *
	 * @return array Default retry rules.
	 */
	public function get_wcs_defaults() {
		return array(
			array(
				'retry_after_interval'            => 12 * HOUR_IN_SECONDS,
				'email_template_customer'         => '',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
				'email_override_customer'         => false,
				'email_override_admin'            => false,
				'email_subject_customer'          => '',
				'email_heading_customer'          => '',
				'email_additional_content_customer' => '',
				'email_subject_admin'             => '',
				'email_heading_admin'             => '',
				'email_additional_content_admin'  => '',
			),
			array(
				'retry_after_interval'            => 12 * HOUR_IN_SECONDS,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
				'email_override_customer'         => false,
				'email_override_admin'            => false,
				'email_subject_customer'          => '',
				'email_heading_customer'          => '',
				'email_additional_content_customer' => '',
				'email_subject_admin'             => '',
				'email_heading_admin'             => '',
				'email_additional_content_admin'  => '',
			),
			array(
				'retry_after_interval'            => 24 * HOUR_IN_SECONDS,
				'email_template_customer'         => '',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
				'email_override_customer'         => false,
				'email_override_admin'            => false,
				'email_subject_customer'          => '',
				'email_heading_customer'          => '',
				'email_additional_content_customer' => '',
				'email_subject_admin'             => '',
				'email_heading_admin'             => '',
				'email_additional_content_admin'  => '',
			),
			array(
				'retry_after_interval'            => 48 * HOUR_IN_SECONDS,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
				'email_override_customer'         => false,
				'email_override_admin'            => false,
				'email_subject_customer'          => '',
				'email_heading_customer'          => '',
				'email_additional_content_customer' => '',
				'email_subject_admin'             => '',
				'email_heading_admin'             => '',
				'email_additional_content_admin'  => '',
			),
			array(
				'retry_after_interval'            => 72 * HOUR_IN_SECONDS,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
				'email_override_customer'         => false,
				'email_override_admin'            => false,
				'email_subject_customer'          => '',
				'email_heading_customer'          => '',
				'email_additional_content_customer' => '',
				'email_subject_admin'             => '',
				'email_heading_admin'             => '',
				'email_additional_content_admin'  => '',
			),
		);
	}

	/**
	 * Get valid order statuses.
	 *
	 * @return array Array of valid order status keys (without 'wc-' prefix).
	 */
	public function get_valid_order_statuses() {
		$statuses = wc_get_order_statuses();
		return array_map(
			function ( $status ) {
				return str_replace( 'wc-', '', $status );
			},
			array_keys( $statuses )
		);
	}

	/**
	 * Get valid subscription statuses.
	 *
	 * @return array Array of valid subscription status keys.
	 */
	public function get_valid_subscription_statuses() {
		// These are the statuses that make sense during a retry period.
		return array(
			'active',
			'on-hold',
			'pending',
			'pending-cancel',
		);
	}

	/**
	 * Get order statuses with labels for the UI.
	 *
	 * @return array Associative array of status => label.
	 */
	public function get_order_statuses_for_ui() {
		$statuses = wc_get_order_statuses();
		$result   = array();

		foreach ( $statuses as $key => $label ) {
			$result[ str_replace( 'wc-', '', $key ) ] = $label;
		}

		return $result;
	}

	/**
	 * Get subscription statuses with labels for the UI.
	 *
	 * @return array Associative array of status => label.
	 */
	public function get_subscription_statuses_for_ui() {
		return array(
			'active'         => __( 'Active', 'wcs-retry-rules-editor' ),
			'on-hold'        => __( 'On hold', 'wcs-retry-rules-editor' ),
			'pending'        => __( 'Pending', 'wcs-retry-rules-editor' ),
			'pending-cancel' => __( 'Pending Cancellation', 'wcs-retry-rules-editor' ),
		);
	}
}
