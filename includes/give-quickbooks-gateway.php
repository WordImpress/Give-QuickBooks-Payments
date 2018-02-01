<?php

/**
 * The QuickBooks Payment Gateway Class.
 *
 * @link       https://wordimpress.com
 * @since      1.0.0
 *
 * @package    Give_QuickBooks_Payments
 * @subpackage Give_QuickBooks_Payments/includes
 */

/**
 * The QuickBooks Gateway functionality.
 *
 * Register and manage the QuickBooks gateway method.
 *
 * @package    Give_QuickBooks_Payments
 * @subpackage Give_QuickBooks_Payments/includes
 * @author     WordImpress
 * @since      1.0.0
 */
class Give_QuickBooks_Gateway {

	/**
	 * Give_QuickBooks_Gateway constructor.
	 *
	 * @access  public
	 * @since   1.0.0
	 */
	public function __construct() {

		// Get access token initially when connect oAuth and click on 'Connect to QuickBooks Button'.
		$this->get_access_token_from_auth_code();

		// Looking for access token if access token expires and get new 'access_token' from the 'refresh_token'
		$this->looking_for_access_token();

		// Registering QuickBooks Payment gateway with give.
		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );

		// Add QuickBooks Settings section.
		add_action( 'give_get_settings_gateways', array( $this, 'add_settings' ) );
		add_action( 'give_get_sections_gateways', array( $this, 'add_section' ) );

		// Adding Give QuickBooks auth button into Give WP setting API.
		add_action( 'give_admin_field_quickbooks_auth_button', array( $this, 'quickbooks_auth_button_callback' ), 10, 2 );

		// Process Payment.
		add_action( 'give_gateway_' . GIVE_QUICKBOOKS_SLUG, array( $this, 'process_payment' ) );
	}


	/**
	 * Looking for access token every time to check if expired.
	 *
	 * Check if current access token expires? If yes then recall api using refresh_token.
	 * 'access_token' expires every 1 hour (3600s).
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function looking_for_access_token() {

		// Bail out if get Code in redirect URL.
		if ( ! empty( $_GET['code'] ) ) {
			return false;
		}

		// Bail out if get realmId in redirect URL.
		if ( ! empty( $_GET['realmId'] ) ) {
			return false;
		}

		// Get Auth code from db.
		$code = give_qb_get_auth_code();

		// Bail out if QuickBooks Auth Code empty.
		if ( empty( $code ) ) {
			return false;
		}

		$result = Give_QuickBooks_API::get_auth_access_token( $code, 'authorization_code' );

		// Check the response code
		$response_code = wp_remote_retrieve_response_code( $result );

		// Request for new access token if current access_token expires and get status '401'.
		if ( 401 === $response_code ) {
			$refresh_token_obj = Give_QuickBooks_API::get_auth_refresh_access_token();

			$refresh_token = $refresh_token_obj->refresh_token;
			$access_token  = $refresh_token_obj->access_token;

			give_update_option( 'give_quickbooks_access_token', $access_token );
			give_update_option( 'give_quickbooks_refresh_token', $refresh_token );
		}

	}

	/**
	 * Get access token from auth code.
	 *
	 * Here we exchange auth_code and get new oAuth 'access_token'.
	 * Which we will use for the Payment process.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function get_access_token_from_auth_code() {

		// Bail out, if not getting auth `code` from the request param.
		if ( empty( $_GET['code'] ) ) {
			return false;
		}

		// Bail out, if not getting auth `realmId` from the request param.
		if ( empty( $_GET['realmId'] ) ) {
			return false;
		}

		$responseState = ! empty( $_GET['state'] ) ? give_clean( $_GET['state'] ) : 'RandomState';
		if ( strcmp( 'RandomState', $responseState ) != 0 ) {
			throw new Exception( "The state is not correct from Intuit Server. Consider your app is hacked." );
		}

		$realmId = ! empty( $_GET['realmId'] ) ? give_clean( $_GET['realmId'] ) : '';
		give_update_option( 'give_quickbooks_realm_id', $realmId );

		// Get Authorization Code.
		$code = ! empty( $_GET['code'] ) ? give_clean( $_GET['code'] ) : 0;
		give_update_option( 'give_quickbooks_auth_code', $code );

		$result = Give_QuickBooks_API::get_auth_access_token( $code, 'authorization_code' );

		// Check the response code
		$response_body = wp_remote_retrieve_body( $result );
		$response_obj  = json_decode( $response_body );

		$refresh_token = $response_obj->refresh_token;
		$access_token  = $response_obj->access_token;

		give_update_option( 'give_quickbooks_access_token', $access_token );
		give_update_option( 'give_quickbooks_refresh_token', $refresh_token );

		if ( ! empty( $realmId ) ) {
			wp_redirect( give_qb_get_settings_url() );
			exit;
		}

	}

	/**
	 * Register QuickBooks gateway with Give WP.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   array $gateways get the array of all registered gateways.
	 *
	 * @return  array $gateways return modified gateway array data.
	 */
	public function register_gateway( $gateways ) {

		// Register the QuickBooks Gateway values.
		$gateways['quickbooks'] = array(
			'admin_label'    => __( 'QuickBooks', 'give-quickbooks-payments' ),
			'checkout_label' => __( 'Credit Card (QuickBooks)', 'give-quickbooks-payments' ),
		);

		return $gateways;
	}

	/**
	 * Register the gateway settings API.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   array $settings Gateway setting array.
	 *
	 * @return  array Get the settings fields of QuickBooks settings.
	 */
	public function add_settings( $settings ) {
		$current_section = give_get_current_setting_section();

		if ( 'quickbooks' !== $current_section ) {
			return $settings;
		}

		return array(
			array(
				'id'   => 'give_quickbooks_admin_settings',
				'type' => 'title',
			),
			array(
				'name'    => __( 'Client ID', 'give-quickbooks-payments' ),
				'desc'    => __( 'Please enter your Development/Production Client ID from your intuit application.', 'give-quickbooks-payments' ),
				'id'      => 'give_quickbooks_client_id',
				'default' => '',
				'type'    => 'text',
			),
			array(
				'name'    => __( 'Client Secret', 'give-quickbooks-payments' ),
				'desc'    => __( 'Please enter your Development/Production Client Secret from your intuit application.', 'give-quickbooks-payments' ),
				'id'      => 'give_quickbooks_client_secret',
				'default' => '',
				'type'    => 'text',
			),
			array(
				'name' => __( 'Connect / Disconnect', 'give-quickbooks-payments' ),
				'desc' => 'Connect / Disconnect Development or Production.',
				'id'   => 'give_quickbooks_auth_button',
				'type' => 'quickbooks_auth_button',
			),
			array(
				'name' => __( 'Collect Billing Details', 'give-quickbooks-payments' ),
				'desc' => __( 'This option will enable the billing details section for Stripe which requires the donor\'s address to complete the donation. These fields are not required by QuickBooks to process the transaction, but you may have the need to collect the data.', 'give-quickbooks-payments' ),
				'id'   => 'quickbooks_collect_billing',
				'type' => 'checkbox',
			),
			array(
				'name'  => __( 'Give QuickBooks Gateway Settings Docs Link', 'give-quickbooks-payments' ),
				'url'   => esc_url( 'https://givewp.com/documentation/add-ons/#/' ),
				'title' => __( 'Give QuickBooks Gateway Settings', 'give-quickbooks-payments' ),
				'type'  => 'give_docs_link',
			),
			array(
				'id'   => 'give_quickbooks_admin_settings',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Add setting section.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sections Array of section.
	 *
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections['quickbooks'] = __( 'QuickBooks Settings', 'give-quickbooks-payments' );

		return $sections;
	}

	/**
	 * It register QuickBooks auth button by using GiveWP Setting API.
	 * Setting API.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   $value              array Pass various value from Setting api array.
	 * @param   $option_value       string Option value for button.
	 *
	 * @return  false               if not connected.
	 */
	public function quickbooks_auth_button_callback( $value, $option_value ) {
		?>
		<tr valign="top" <?php echo ! empty( $value['wrapper_class'] ) ? 'class="' . $value['wrapper_class'] . '"' : '' ?>>
			<th scope="row" class="titledesc">
				<label for=""><?php echo Give_Admin_Settings::get_field_title( $value ); ?></label>
			</th>
			<td class="gocardless-auth" colspan="2">
				<a class="connect-quickbooks-button"
				   href="<?php echo $this->get_qb_connect_url(); ?>">
					<img width="225px" src="<?php echo GIVE_QUICKBOOKS_PLUGIN_URL . 'assets/images/C2QB_green_btn_lg_default.png' ?>">
				</a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate Authentication with QuickBooks dynamically.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   array $args query args list for authentication url.
	 *
	 * @return  string  return complete authentication url with urls.
	 */
	public function get_qb_connect_url( $args = array() ) {
		// Create argument list.
		$args = wp_parse_args( $args, array(
			'redirect_uri'          => give_qb_get_settings_url(),
			'client_id'             => give_qb_get_client_id(),
			'scope'                 => 'com.intuit.quickbooks.payment',
			'give_quickbooks_nonce' => wp_create_nonce( 'give_quickbooks_nonce' ),
			'response_type'         => 'code',
			'state'                 => 'RandomState',
		) );

		$authorizationRequestUrl = GIVE_QUICKBOOKS_OAUTH_BASE_URL . '?' . http_build_query( $args, null, '&', PHP_QUERY_RFC1738 );

		return $authorizationRequestUrl;
	}

	/**
	 * GoCardless process the payment.
	 *
	 * @access  public
	 * @since   1.0.0
	 *
	 * @param   array $payment_data Pass submitted payment data.
	 *
	 * @return  bool | false            if process payment failed or there is any error
	 *                                  occurred while doing payment process.
	 */
	public static function process_payment( $payment_data ) {

		// Validate the gateway_nonce.
		give_validate_nonce( $payment_data['gateway_nonce'], 'give-gateway' );

		// Get access token from customer cc data.
		$access_token_result = Give_QuickBooks_API::get_access_token( $payment_data );

		if ( is_wp_error( $access_token_result ) ) {

			give_record_gateway_error( __( 'QuickBooks Error', 'give-quickbooks-payments' ), $access_token_result->get_error_message() );

			give_set_error( 'request_error', $access_token_result->get_error_message() );
			give_send_back_to_checkout( '?payment-mode=' . GIVE_QUICKBOOKS_SLUG );
			exit;
		}

		// Create new payment for donation.
		$payment_id = self::quickbooks_create_payment( $payment_data );

		// Check if payment is created.
		if ( empty( $payment_id ) ) {

			// Record the error.
			give_record_gateway_error( __( 'Payment Error', 'give-quickbooks-payments' ), sprintf( __( 'Payment creation failed before sending donor to QuickBooks. Payment data: %s', 'give-quickbooks-payments' ), json_encode( $payment_data ) ), $payment_id );

			give_set_error( 'payment_creation_error', __( 'Payment creation failed. Please try again', 'give-quickbooks-payments' ) );

			// Problems? Send back.
			give_send_back_to_checkout( '?payment-mode=' . GIVE_QUICKBOOKS_SLUG );
		}

		// This access token expiry in 15 min.
		$access_token = $access_token_result->value;

		// Process Payment.
		$payment_obj = Give_QuickBooks_API::quickbooks_payment_request( $payment_data, $access_token );

		return false;
	}

	/**
	 * Get success donation page url.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   int $payment_id Donation payment id.
	 *
	 * @return  string get give donation url with some query string.
	 */
	protected static function get_success_page( $payment_id ) {

		$success_redirect = add_query_arg( array(
			'payment_id' => $payment_id,
		), get_permalink( give_get_option( 'success_page' ) ) );

		return $success_redirect;
	}

	/**
	 * Create a new payment.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   array $payment_data Donation payment data.
	 *
	 * @return  int     Get payment ID.
	 */
	public static function quickbooks_create_payment( $payment_data ) {

		$form_id  = intval( $payment_data['post_data']['give-form-id'] );
		$price_id = isset( $payment_data['post_data']['give-price-id'] ) ? $payment_data['post_data']['give-price-id'] : '';

		// Collect payment data.
		$insert_payment_data = array(
			'price'           => $payment_data['price'],
			'give_form_title' => $payment_data['post_data']['give-form-title'],
			'give_form_id'    => $form_id,
			'give_price_id'   => $price_id,
			'date'            => $payment_data['date'],
			'user_email'      => $payment_data['user_email'],
			'purchase_key'    => $payment_data['purchase_key'],
			'currency'        => give_get_currency(),
			'user_info'       => $payment_data['user_info'],
			'status'          => 'pending',
			'gateway'         => GIVE_QUICKBOOKS_SLUG,
		);

		// Record the pending payment.
		return give_insert_payment( $insert_payment_data );
	}

}
