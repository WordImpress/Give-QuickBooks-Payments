<?php

/**
 * Class Give_QuickBooks_API
 *
 * @since      1.0
 * @package    Give_QuickBooks_Payments
 * @subpackage Give_QuickBooks_Payments/includes
 * @author     WordImpress
 */
class Give_QuickBooks_API {

	/**
	 * Get oAuth access token from Auth Code.
	 *
	 * @since 1.0
	 *
	 * @param $code
	 * @param $grant_type
	 *
	 * @return array|bool|\WP_Error
	 */
	public static function get_auth_access_token( $code, $grant_type ) {

		// Bail out, if Grant type is not set.
		if ( ! isset( $grant_type ) ) {
			return false;
		}

		// Get Authorization Header.
		$authorization_header_info = give_qb_authorization_header();

		$result = wp_remote_post( GIVE_QUICKBOOKS_ACCESS_TOKEN_ENDPOINT, array(
			'headers' => array(
				'Authorization' => $authorization_header_info,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'   => $grant_type,
				'code'         => $code,
				'redirect_uri' => give_qb_get_settings_url(),
			),
		) );

		return $result;
	}

	/**
	 * Get OAuth Access Token from the refresh token.
	 *
	 * @since 1.0
	 *
	 * @return object
	 */
	public static function get_auth_refresh_access_token() {

		$result = wp_remote_post( GIVE_QUICKBOOKS_ACCESS_TOKEN_ENDPOINT, array(
			'headers' => array(
				'Authorization' => give_qb_authorization_header(),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => give_qb_get_oauth_refresh_token(),
			),
		) );

		return $result;
	}

	/**
	 * Get access token from the Customer card information at the time of donation.
	 *
	 * @since 1.0
	 *
	 * @param array $payment_data
	 *
	 * @return array|mixed|object
	 */
	public static function get_access_token( $payment_data ) {

		$card_expiry       = explode( "/", $payment_data['post_data']['card_expiry'] );
		$card_expiry_month = trim( $card_expiry[0] );

		$card_array['card'] = array(
			'expYear'  => $payment_data['post_data']['card-expiry-year'],
			'expMonth' => $card_expiry_month,
			'cvc'      => $payment_data['post_data']['card_cvc'],
			'number'   => $payment_data['post_data']['card_number'],
			'name'     => $payment_data['post_data']['card_name'],
		);

		$data = wp_json_encode( $card_array );

		$base_url = give_is_test_mode() ? GIVE_QUICKBOOKS_SANDBOX_BASE_URL : GIVE_QUICKBOOKS_PRODUCTION_BASE_URL;
		$result   = wp_remote_post( $base_url . '/quickbooks/v4/payments/tokens', array(
			'headers' => array(
				'content-type' => 'application/json',
			),
			'body'    => $data,
		) );

		$response_body = wp_remote_retrieve_body( $result );
		$response_obj  = json_decode( $response_body );

		return $response_obj;
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
	public static function looking_for_access_token() {

		// Get Auth code from db.
		$code = give_qb_get_auth_code();

		// Bail out if QuickBooks Auth Code empty.
		if ( empty( $code ) ) {
			return false;
		}

		// Request for new access token if current access_token expires and get status '401'.
		$refresh_token_obj = self::get_auth_refresh_access_token();

		$refresh_token = $refresh_token_obj->refresh_token;
		$access_token  = $refresh_token_obj->access_token;

		give_update_option( 'give_quickbooks_access_token', $access_token );
		give_update_option( 'give_quickbooks_refresh_token', $refresh_token );

	}

	/**
	 * QuickBooks Payment process request.
	 *
	 * @since 1.0
	 *
	 * @param array  $payment_data
	 * @param string $access_token
	 *
	 * @return object
	 */
	public static function quickbooks_payment_request( $payment_data, $access_token ) {

		$card_expiry       = explode( '/', $payment_data['post_data']['card_expiry'] );
		$card_expiry_month = trim( $card_expiry[0] );

		$request_data = array(
			'amount'   => $payment_data['price'],
			'token'    => $access_token,
			'currency' => give_get_currency(),
			'context'  => array(
				'mobile'      => false,
				'isEcommerce' => true,
			),
			'card'     => array(
				'expYear'  => $payment_data['post_data']['card-expiry-year'],
				'expMonth' => $card_expiry_month,
				'cvc'      => $payment_data['post_data']['card_cvc'],
				'number'   => $payment_data['card_info']['card_number'],
				'name'     => $payment_data['post_data']['card_name'],
			),
		);

		$data         = wp_json_encode( $request_data );
		$access_token = give_qb_get_oauth_access_token();

		$authorization = 'Bearer ' . $access_token;
		$base_url      = give_is_test_mode() ? GIVE_QUICKBOOKS_SANDBOX_BASE_URL : GIVE_QUICKBOOKS_PRODUCTION_BASE_URL;

		$result = wp_remote_post( $base_url . '/quickbooks/v4/payments/charges', array(
			'headers' => array(
				'content-type'  => 'application/json',
				'Request-Id'    => give_qb_generate_unique_request_id(),
				'Authorization' => $authorization,
			),
			'body'    => $data,
		) );


		$response_body = wp_remote_retrieve_body( $result );
		$response_obj  = json_decode( $response_body );

		if ( ! isset( $response_obj ) ) {

			if ( 401 === $result['response']['code'] ) {
				self::looking_for_access_token();
				self::quickbooks_payment_request( $payment_data, $access_token );
			}
		}

		return $response_obj;
	}

}
