<?php
/**
 * REST API Controller
 *
 * Provides REST API endpoints for managing retry rules.
 *
 * @package WCS_Retry_Rules_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for retry rules.
 */
class WCS_RRE_REST_Controller extends WP_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wcs-rre/v1';

	/**
	 * Rules manager instance.
	 *
	 * @var WCS_RRE_Rules_Manager
	 */
	private $rules_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rules_manager = WCS_RRE_Rules_Manager::instance();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// GET/POST rules endpoint.
		register_rest_route(
			$this->namespace,
			'/rules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rules' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_rules' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_rules_args(),
				),
			)
		);

		// GET defaults endpoint.
		register_rest_route(
			$this->namespace,
			'/defaults',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_defaults' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// POST reset endpoint.
		register_rest_route(
			$this->namespace,
			'/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_to_defaults' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// GET config endpoint (for UI initialization).
		register_rest_route(
			$this->namespace,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// GET/POST email settings endpoint.
		register_rest_route(
			$this->namespace,
			'/email-preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_email_preview' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'recipient' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_email_preview_recipient' ),
					),
					'rule'      => array(
						'required'          => true,
						'type'              => 'object',
						'validate_callback' => array( $this, 'validate_email_preview_rule' ),
					),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to access these endpoints.
	 *
	 * @return bool True if user can manage WooCommerce.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get current rules.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_rules( $request ) {
		$custom_rules = $this->rules_manager->get_active_rules();

		// If no custom rules, indicate we're using defaults.
		if ( empty( $custom_rules ) ) {
			return rest_ensure_response(
				array(
					'rules'      => $this->rules_manager->get_wcs_defaults(),
					'is_default' => true,
					'message'    => __( 'Using WooCommerce Subscriptions default rules', 'wcs-retry-rules-editor' ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'rules'      => $custom_rules,
				'is_default' => false,
				'message'    => __( 'Using custom rules', 'wcs-retry-rules-editor' ),
			)
		);
	}

	/**
	 * Update/save rules.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function update_rules( $request ) {
		$rules = $request->get_param( 'rules' );

		if ( ! is_array( $rules ) ) {
			return new WP_Error(
				'invalid_rules',
				__( 'Rules must be an array', 'wcs-retry-rules-editor' ),
				array( 'status' => 400 )
			);
		}

		// Save the rules.
		$result = $this->rules_manager->save_rules( $rules );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rules saved successfully', 'wcs-retry-rules-editor' ),
				'rules'   => $this->rules_manager->get_active_rules(),
			)
		);
	}

	/**
	 * Get WCS default rules.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_defaults( $request ) {
		return rest_ensure_response(
			array(
				'rules' => $this->rules_manager->get_wcs_defaults(),
			)
		);
	}

	/**
	 * Reset to WCS defaults (delete custom rules).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function reset_to_defaults( $request ) {
		$this->rules_manager->clear_rules();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rules reset to WooCommerce Subscriptions defaults', 'wcs-retry-rules-editor' ),
				'rules'   => $this->rules_manager->get_wcs_defaults(),
			)
		);
	}

	/**
	 * Get configuration data for the admin UI.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_config( $request ) {
		return rest_ensure_response(
			array(
				'order_statuses'        => $this->rules_manager->get_order_statuses_for_ui(),
				'subscription_statuses' => $this->rules_manager->get_subscription_statuses_for_ui(),
				'email_templates'       => array(
					'customer' => array(
						''                                 => __( 'None', 'wcs-retry-rules-editor' ),
						'WCS_Email_Customer_Payment_Retry' => __( 'Payment Retry (Customer)', 'wcs-retry-rules-editor' ),
					),
					'admin'    => array(
						''                        => __( 'None', 'wcs-retry-rules-editor' ),
						'WCS_Email_Payment_Retry' => __( 'Payment Retry (Admin)', 'wcs-retry-rules-editor' ),
					),
				),
				'min_interval'          => WCS_RRE_Rules_Manager::MIN_INTERVAL,
				'email_preview'         => $this->get_email_preview_config(),
			)
		);
	}

	/**
	 * Get email preview HTML for a retry email type.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_email_preview( $request ) {
		$recipient = $request->get_param( 'recipient' );
		$rule      = $request->get_param( 'rule' );
		$map       = $this->get_email_template_map();

		$email_type = isset( $map[ $recipient ] ) ? $map[ $recipient ]['class'] : '';

		if ( ! class_exists( '\Automattic\WooCommerce\Internal\Admin\EmailPreview\EmailPreview' ) ) {
			return new WP_Error(
				'preview_unavailable',
				__( 'Email preview is not available with the current WooCommerce version.', 'wcs-retry-rules-editor' ),
				array( 'status' => 400 )
			);
		}

		try {
			$sanitized_rule = $this->rules_manager->sanitize_rule( $rule );
			$preview_filters = $this->get_preview_filters( $sanitized_rule, $recipient );

			$this->ensure_email_class_registered( $email_type );

			add_filter( 'wcs_default_retry_rules', $preview_filters['rules'], 1 );
			add_filter( $preview_filters['subject_filter'], $preview_filters['subject'], 10, 3 );
			add_filter( $preview_filters['heading_filter'], $preview_filters['heading'], 10, 3 );
			add_filter( $preview_filters['additional_filter'], $preview_filters['additional'], 10, 3 );

			$email_preview = \Automattic\WooCommerce\Internal\Admin\EmailPreview\EmailPreview::instance();
			$email_preview->set_email_type( $email_type );

			$content = $email_preview->render();
			if ( method_exists( $email_preview, 'ensure_links_open_in_new_tab' ) ) {
				$content = $email_preview->ensure_links_open_in_new_tab( $content );
			}

			$email = $email_preview->get_email();

			return rest_ensure_response(
				array(
					'type'    => $email_type,
					'subject' => $email_preview->get_subject(),
					'heading' => is_a( $email, 'WC_Email' ) ? $email->get_heading() : '',
					'html'    => $content,
				)
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'preview_failed',
				__( 'Unable to render email preview.', 'wcs-retry-rules-editor' ),
				array( 'status' => 500 )
			);
		} finally {
			if ( isset( $preview_filters ) ) {
				remove_filter( 'wcs_default_retry_rules', $preview_filters['rules'], 1 );
				remove_filter( $preview_filters['subject_filter'], $preview_filters['subject'], 10 );
				remove_filter( $preview_filters['heading_filter'], $preview_filters['heading'], 10 );
				remove_filter( $preview_filters['additional_filter'], $preview_filters['additional'], 10 );
			}
		}
	}

	/**
	 * Ensure a retry email class is registered with the WooCommerce mailer.
	 *
	 * @param string $email_type Email class name.
	 */
	private function ensure_email_class_registered( $email_type ) {
		if ( empty( $email_type ) || ! class_exists( $email_type ) ) {
			return;
		}

		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();

		if ( ! isset( $emails[ $email_type ] ) ) {
			$emails[ $email_type ] = new $email_type();
			$mailer->emails       = $emails;
		}
	}

	/**
	 * Validate email preview recipient.
	 *
	 * @param string $value Recipient.
	 * @return bool|WP_Error
	 */
	public function validate_email_preview_recipient( $value ) {
		$allowed = array_keys( $this->get_email_template_map() );
		if ( in_array( $value, $allowed, true ) ) {
			return true;
		}

		return new WP_Error(
			'invalid_email_recipient',
			__( 'Invalid email preview recipient.', 'wcs-retry-rules-editor' )
		);
	}

	/**
	 * Validate preview rule param.
	 *
	 * @param array $rule Rule payload.
	 * @return bool|WP_Error
	 */
	public function validate_email_preview_rule( $rule ) {
		if ( ! is_array( $rule ) ) {
			return new WP_Error(
				'invalid_rule',
				__( 'Rule must be an object.', 'wcs-retry-rules-editor' )
			);
		}

		$validation = $this->rules_manager->validate_rule( $rule );
		if ( is_wp_error( $validation ) ) {
			return new WP_Error(
				'invalid_rule',
				$validation->get_error_message()
			);
		}

		return true;
	}

	/**
	 * Get email preview config for UI.
	 *
	 * @return array
	 */
	private function get_email_preview_config() {
		$map    = $this->get_email_template_map();
		$emails = WC()->mailer()->get_emails();
		$data   = array();

		foreach ( $map as $key => $email ) {
			$instance = null;
			foreach ( $emails as $wc_email ) {
				if ( $email['class'] === get_class( $wc_email ) || $email['id'] === $wc_email->id ) {
					$instance = $wc_email;
					break;
				}
			}

			$data[ $key ] = array(
				'class'        => $email['class'],
				'id'           => $email['id'],
				'label'        => $email['label'],
				'settings_url' => $email['settings_url'],
				'default_subject'    => $instance ? $instance->get_default_subject() : '',
				'default_heading'    => $instance ? $instance->get_default_heading() : '',
				'default_additional' => $instance ? $instance->get_default_additional_content() : '',
			);
		}

		return $data;
	}

	/**
	 * Get email template map used for previews and settings.
	 *
	 * @return array
	 */
	private function get_email_template_map() {
		$base_settings_url = admin_url( 'admin.php?page=wc-settings&tab=email&section=' );

		return array(
			'customer' => array(
				'class'        => 'WCS_Email_Customer_Payment_Retry',
				'id'           => 'customer_payment_retry',
				'label'        => __( 'Customer Payment Retry', 'wcs-retry-rules-editor' ),
				'settings_url' => esc_url_raw( $base_settings_url . 'customer_payment_retry' ),
			),
			'admin'    => array(
				'class'        => 'WCS_Email_Payment_Retry',
				'id'           => 'payment_retry',
				'label'        => __( 'Payment Retry (Admin)', 'wcs-retry-rules-editor' ),
				'settings_url' => esc_url_raw( $base_settings_url . 'payment_retry' ),
			),
		);
	}

	/**
	 * Get filters for previewing email content with rule overrides.
	 *
	 * @param array  $rule      Sanitized rule data.
	 * @param string $recipient Recipient type.
	 * @return array
	 */
	private function get_preview_filters( $rule, $recipient ) {
		$map = $this->get_email_template_map();
		$id  = $map[ $recipient ]['id'];
		$override_key = 'email_override_' . $recipient;

		$subject_filter    = 'woocommerce_email_subject_' . $id;
		$heading_filter    = 'woocommerce_email_heading_' . $id;
		$additional_filter = 'woocommerce_email_additional_content_' . $id;

		return array(
			'rules'             => function( $default_rules ) use ( $rule ) {
				return array( 1 => $rule );
			},
			'subject_filter'    => $subject_filter,
			'heading_filter'    => $heading_filter,
			'additional_filter' => $additional_filter,
			'subject'           => function( $subject ) use ( $rule, $recipient, $override_key ) {
				if ( empty( $rule[ $override_key ] ) ) {
					return $subject;
				}
				$key = 'email_subject_' . $recipient;
				return isset( $rule[ $key ] ) && '' !== $rule[ $key ] ? $rule[ $key ] : $subject;
			},
			'heading'           => function( $heading ) use ( $rule, $recipient, $override_key ) {
				if ( empty( $rule[ $override_key ] ) ) {
					return $heading;
				}
				$key = 'email_heading_' . $recipient;
				return isset( $rule[ $key ] ) && '' !== $rule[ $key ] ? $rule[ $key ] : $heading;
			},
			'additional'        => function( $content ) use ( $rule, $recipient, $override_key ) {
				if ( empty( $rule[ $override_key ] ) ) {
					return $content;
				}
				$key = 'email_additional_content_' . $recipient;
				return isset( $rule[ $key ] ) && '' !== $rule[ $key ] ? $rule[ $key ] : $content;
			},
		);
	}

	/**
	 * Get arguments schema for the rules endpoint.
	 *
	 * @return array Arguments schema.
	 */
	private function get_rules_args() {
		return array(
			'rules' => array(
				'required'          => true,
				'type'              => 'array',
				'validate_callback' => array( $this, 'validate_rules_param' ),
				'items'             => array(
					'type'       => 'object',
					'properties' => array(
						'retry_after_interval'            => array(
							'type'    => 'integer',
							'minimum' => WCS_RRE_Rules_Manager::MIN_INTERVAL,
						),
						'email_template_customer'         => array(
							'type' => 'string',
						),
						'email_template_admin'            => array(
							'type' => 'string',
						),
						'status_to_apply_to_order'        => array(
							'type' => 'string',
						),
						'status_to_apply_to_subscription' => array(
							'type' => 'string',
						),
						'email_subject_customer'          => array(
							'type' => 'string',
						),
						'email_heading_customer'          => array(
							'type' => 'string',
						),
						'email_additional_content_customer' => array(
							'type' => 'string',
						),
						'email_subject_admin'             => array(
							'type' => 'string',
						),
						'email_heading_admin'             => array(
							'type' => 'string',
						),
						'email_additional_content_admin'  => array(
							'type' => 'string',
						),
						'email_override_customer'         => array(
							'type' => 'boolean',
						),
						'email_override_admin'            => array(
							'type' => 'boolean',
						),
					),
				),
			),
		);
	}

	/**
	 * Validate the rules parameter.
	 *
	 * @param array           $rules   Rules array.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error True if valid, WP_Error if not.
	 */
	public function validate_rules_param( $rules, $request, $param ) {
		if ( ! is_array( $rules ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Rules must be an array', 'wcs-retry-rules-editor' )
			);
		}

		foreach ( $rules as $index => $rule ) {
			$validation = $this->rules_manager->validate_rule( $rule );
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

		return true;
	}
}
