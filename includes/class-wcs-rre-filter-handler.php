<?php
/**
 * Filter Handler Class
 *
 * Applies custom retry rules via the WooCommerce Subscriptions filter.
 * This is the critical safety layer that ensures rules are validated
 * before being applied, with fallback to defaults on any error.
 *
 * @package WCS_Retry_Rules_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles applying custom retry rules via the WCS filter.
 */
class WCS_RRE_Filter_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var WCS_RRE_Filter_Handler
	 */
	private static $instance = null;

	/**
	 * Rules manager instance.
	 *
	 * @var WCS_RRE_Rules_Manager
	 */
	private $rules_manager;

	/**
	 * Get singleton instance.
	 *
	 * @return WCS_RRE_Filter_Handler
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
	private function __construct() {
		$this->rules_manager = WCS_RRE_Rules_Manager::instance();
	}

	/**
	 * Initialize the filter handler.
	 *
	 * Hooks into the WCS filter at priority 5 to ensure we run
	 * before the default WCS_Retry_Rules class instantiation.
	 */
	public function init() {
		add_filter( 'wcs_default_retry_rules', array( $this, 'apply_custom_rules' ), 5 );
		add_filter( 'woocommerce_email_subject_customer_payment_retry', array( $this, 'filter_customer_email_subject' ), 10, 3 );
		add_filter( 'woocommerce_email_heading_customer_payment_retry', array( $this, 'filter_customer_email_heading' ), 10, 3 );
		add_filter( 'woocommerce_email_additional_content_customer_payment_retry', array( $this, 'filter_customer_email_additional_content' ), 10, 3 );
		add_filter( 'woocommerce_email_subject_payment_retry', array( $this, 'filter_admin_email_subject' ), 10, 3 );
		add_filter( 'woocommerce_email_heading_payment_retry', array( $this, 'filter_admin_email_heading' ), 10, 3 );
		add_filter( 'woocommerce_email_additional_content_payment_retry', array( $this, 'filter_admin_email_additional_content' ), 10, 3 );
	}

	/**
	 * Apply custom rules via the WCS filter.
	 *
	 * This method is designed to be fail-safe:
	 * - Returns defaults if no custom rules are configured
	 * - Validates each rule before applying
	 * - Catches any exceptions and falls back to defaults
	 *
	 * @param array $default_rules The default WCS retry rules.
	 * @return array Custom rules if valid, otherwise default rules.
	 */
	public function apply_custom_rules( $default_rules ) {
		try {
			// Get custom rules from our configuration.
			$custom_rules = $this->rules_manager->get_active_rules();

			// Safety: If no custom rules configured, return defaults.
			if ( empty( $custom_rules ) || ! is_array( $custom_rules ) ) {
				return $default_rules;
			}

			// Validate each rule before applying.
			$validated_rules = array();
			foreach ( $custom_rules as $index => $rule ) {
				$validation = $this->rules_manager->validate_rule( $rule );
				if ( true === $validation ) {
					$validated_rules[] = $this->rules_manager->sanitize_rule( $rule );
				} else {
					// Log validation failure but continue with other rules.
					$this->log_error(
						sprintf(
							'Rule %d failed validation: %s',
							$index + 1,
							is_wp_error( $validation ) ? $validation->get_error_message() : 'Unknown error'
						)
					);
				}
			}

			// Safety: If all rules failed validation, return defaults.
			if ( empty( $validated_rules ) ) {
				$this->log_error( 'All custom rules failed validation, using WCS defaults' );
				return $default_rules;
			}

			return $validated_rules;

		} catch ( Exception $e ) {
			// Safety: On any exception, fall back to defaults.
			$this->log_error( 'Exception applying custom rules: ' . $e->getMessage() );
			return $default_rules;
		}
	}

	/**
	 * Log an error message when WP_DEBUG is enabled.
	 *
	 * @param string $message Error message to log.
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WCS Retry Rules Editor: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Filter customer email subject.
	 *
	 * @param string   $subject Email subject.
	 * @param mixed    $object  Email object.
	 * @param WC_Email $email   Email instance.
	 * @return string
	 */
	public function filter_customer_email_subject( $subject, $object, $email ) {
		$override = $this->get_rule_email_override( $email, 'subject', 'customer' );
		return '' !== $override ? $override : $subject;
	}

	/**
	 * Filter customer email heading.
	 *
	 * @param string   $heading Email heading.
	 * @param mixed    $object  Email object.
	 * @param WC_Email $email   Email instance.
	 * @return string
	 */
	public function filter_customer_email_heading( $heading, $object, $email ) {
		$override = $this->get_rule_email_override( $email, 'heading', 'customer' );
		return '' !== $override ? $override : $heading;
	}

	/**
	 * Filter customer additional content.
	 *
	 * @param string   $content Additional content.
	 * @param mixed    $object  Email object.
	 * @param WC_Email $email   Email instance.
	 * @return string
	 */
	public function filter_customer_email_additional_content( $content, $object, $email ) {
		$override = $this->get_rule_email_override( $email, 'additional_content', 'customer' );
		return '' !== $override ? $override : $content;
	}

	/**
	 * Filter admin email subject.
	 *
	 * @param string   $subject Email subject.
	 * @param mixed    $object  Email object.
	 * @param WC_Email $email   Email instance.
	 * @return string
	 */
	public function filter_admin_email_subject( $subject, $object, $email ) {
		$override = $this->get_rule_email_override( $email, 'subject', 'admin' );
		return '' !== $override ? $override : $subject;
	}

	/**
	 * Filter admin email heading.
	 *
	 * @param string   $heading Email heading.
	 * @param mixed    $object  Email object.
	 * @param WC_Email $email   Email instance.
	 * @return string
	 */
	public function filter_admin_email_heading( $heading, $object, $email ) {
		$override = $this->get_rule_email_override( $email, 'heading', 'admin' );
		return '' !== $override ? $override : $heading;
	}

	/**
	 * Filter admin additional content.
	 *
	 * @param string   $content Additional content.
	 * @param mixed    $object  Email object.
	 * @param WC_Email $email   Email instance.
	 * @return string
	 */
	public function filter_admin_email_additional_content( $content, $object, $email ) {
		$override = $this->get_rule_email_override( $email, 'additional_content', 'admin' );
		return '' !== $override ? $override : $content;
	}

	/**
	 * Get an email override from the retry rule raw data.
	 *
	 * @param WC_Email $email     Email instance.
	 * @param string   $field     Field to override (subject, heading, additional_content).
	 * @param string   $recipient Recipient type (customer or admin).
	 * @return string
	 */
	private function get_rule_email_override( $email, $field, $recipient ) {
		if ( ! class_exists( 'WCS_Retry' ) || ! is_object( $email ) || ! isset( $email->retry ) ) {
			return '';
		}

		if ( ! is_a( $email->retry, 'WCS_Retry' ) ) {
			return '';
		}

		$rule = $email->retry->get_rule();
		if ( ! is_a( $rule, 'WCS_Retry_Rule' ) ) {
			return '';
		}

		$raw = $rule->get_raw_data();
		$override_key = 'email_override_' . $recipient;
		if ( empty( $raw[ $override_key ] ) ) {
			return '';
		}

		$key = 'email_' . $field . '_' . $recipient;

		return isset( $raw[ $key ] ) && '' !== $raw[ $key ] ? (string) $raw[ $key ] : '';
	}
}
