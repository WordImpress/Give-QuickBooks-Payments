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

		// Registering QuickBooks Payment gateway with give.
		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );

		// Process Payment.
		add_action( 'give_gateway_' . GIVE_QUICKBOOKS_SLUG, array( $this, 'process_payment' ) );

		// Register hooks for QuickBooks refund.
		add_action( 'give_update_payment_status', array( $this, 'process_refund' ), 200, 3 );

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

		$section = give_get_current_setting_section();
		if ( isset( $section ) && 'quickbooks?error=access_denied' === $section ) {
			wp_redirect( add_query_arg( 'give-message', 'qb-auth-rejected', give_qb_get_settings_url() ) );
			exit;
		}
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

		$result = Give_QuickBooks_API::get_auth_access_token( $code );

		// Check the response code
		$response_body = wp_remote_retrieve_body( $result );
		$response_code = wp_remote_retrieve_response_code( $result );
		$response_obj  = json_decode( $response_body );

		if ( 200 === $response_code ) {
			$refresh_token = $response_obj->refresh_token;
			$access_token  = $response_obj->access_token;

			give_update_option( 'give_quickbooks_access_token', $access_token );
			give_update_option( 'give_quickbooks_refresh_token', $refresh_token );

			$current_time               = current_time( 'timestamp' );
			$x_refresh_token_expires_in = $response_obj->x_refresh_token_expires_in;
			give_update_option( 'qb_auth_connected_time', $current_time + 3600 );
			give_update_option( 'qb_auth_x_refresh_token_expires_in', $x_refresh_token_expires_in );
		}

		if ( ! empty( $realmId ) ) {
			wp_redirect( add_query_arg( 'give-message', 'qb-auth-connected', give_qb_get_settings_url() ) );
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
			'checkout_label' => give_qb_payment_method_label(),
		);

		return $gateways;
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

		// Process Payment.
		$payment_process_response = Give_QuickBooks_API::quickbooks_payment_request( $payment_data );

		// Check any error?
		give_qb_handle_error( $payment_process_response );

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

		// Set the payment transaction ID.
		give_set_payment_transaction_id( $payment_id, $payment_process_response->id );
		give_update_payment_meta( $payment_id, '_give_qb_auth_code', $payment_process_response->authCode );

		switch ( $payment_process_response->status ) {
			case ( 'CAPTURED' ):
				give_update_payment_status( $payment_id, 'publish' );
				break;
			case ( 'CANCELLED' ):
				give_update_payment_status( $payment_id, 'cancelled' );
				break;
			case ( 'REFUNDED' ):
				give_update_payment_status( $payment_id, 'refunded' );
				break;
		}

		// Redirect to give success page.
		give_send_to_success_page();

		return true;
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

	/**
	 * QuickBooks refund process.
	 *
	 * @access  public
	 * @since   1.0.0
	 *
	 * @param   int    $payment_id Donation payment id.
	 * @param   string $new_status New payment status.
	 * @param   string $old_status Old payment status.
	 *
	 * @return  bool|WP_Error
	 */
	public function process_refund( $payment_id, $new_status, $old_status ) {

		// Only move forward if refund requested.
		if ( empty( $_POST['give_refund_in_quickbooks'] ) ) {
			return false;
		}

		// Get all posted data.
		$payment_data = $_POST;

		if ( 'refunded' != $new_status || ! isset( $payment_data ) ) {
			return false;
		}

		// Get QuickBooks payment id.
		$charge_id = give_get_payment_transaction_id( $payment_id );

		// If no charge ID, look in the payment notes.
		if ( empty( $charge_id ) ) {
			return new WP_Error( 'missing_payment', sprintf( __( 'Unable to refund order #%s. Order does not have payment ID. Make sure payment has been created.', 'give-quickbooks-payments' ), $payment_id ) );
		}

		// Create refund on QuickBooks.
		$parsed_resp = Give_QuickBooks_API::create_refund( $charge_id, $payment_data );

		if ( ! isset( $parsed_resp ) ) {
			$message = __( 'Authentication Fail', 'give-quickbooks-payments' );
			give_insert_payment_note( $payment_id, sprintf( __( 'Unable to refund via QuickBooks: %s', 'give-quickbooks-payments' ), $message ) );
			// Change it to previous status.
			give_update_payment_status( $payment_id, $old_status );

			return false;
		}

		if ( ! empty( $parsed_resp->errors ) ) {

			$message = '';
			$errors  = array();
			foreach ( $parsed_resp->errors as $err ) {
				$err_item = '';

				if ( ! empty( $err->code ) ) {
					$err_item .= $err->code . ': ';
				}

				if ( ! empty( $err->message ) ) {
					$err_item .= ucfirst( $err->message ) . ' ';
				}

				if ( ! empty( $err->moreInfo ) ) {
					$err_item .= $err->moreInfo;
				}

				$errors[] = $err_item;
			}

			if ( ! empty( $errors ) ) {
				$message .= implode( ', ', $errors );
			}

			give_insert_payment_note( $payment_id, sprintf( __( 'Unable to refund via QuickBooks: %s', 'give-quickbooks-payments' ), $message ) );

			// Change it to previous status.
			give_update_payment_status( $payment_id, $old_status );

			return false;
		}

		if ( empty( $parsed_resp->id ) ) {
			give_insert_payment_note( $payment_id, __( 'Unable to refund via QuickBooks. QuickBooks returns unexpected refund response.', 'give-quickbooks-payments' ) );

			return false;
		}

		if ( isset( $parsed_resp->status ) && 'ISSUED' === $parsed_resp->status ) {

			// Add refund id into payment.
			give_update_meta( $payment_id, '_give_quickbooks_refunded_id', $parsed_resp->id );

			// Insert note about refund.
			give_insert_payment_note( $payment_id, __( 'Refund successfully completed. Refund ID: ' . $parsed_resp->id, 'give-quickbooks-payments' ) );

		}

		return true;
	}

}
