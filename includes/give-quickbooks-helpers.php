<?php
/**
 * All functions related to gateway handler are here.
 *
 * @since  1.0.0
 * @author WordImpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate Random ID
 *
 * @since 1.0
 * @return bool|string
 */
function give_qb_generate_unique_request_id() {
	$guid = sprintf( '%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
		mt_rand( 0, 65535 ), mt_rand( 0, 65535 ),
		mt_rand( 0, 65535 ),
		mt_rand( 0, 4095 ),
		bindec( substr_replace( sprintf( '%016b', mt_rand( 0, 65535 ) ), '01', 6, 2 ) ),
		mt_rand( 0, 65535 ), mt_rand( 0, 65535 ), mt_rand( 0, 65535 )
	);

	return $guid;
}

/**
 * Get Client ID.
 *
 * @since 1.0
 * @return mixed
 */
function give_qb_get_client_id() {
	$give_qb_client_id = give_get_option( 'give_quickbooks_client_id' );

	return $give_qb_client_id;
}

/**
 * Get Client Secret ID.
 *
 * @since 1.0
 * @return mixed
 */
function give_qb_get_client_secret_id() {
	$give_qb_client_secret_id = give_get_option( 'give_quickbooks_client_secret' );

	return $give_qb_client_secret_id;
}

/**
 * Get oAuth access token.
 *
 * @since 1.0
 * @return mixed
 */
function give_qb_get_oauth_access_token() {
	$give_qb_access_token = give_get_option( 'give_quickbooks_access_token' );

	return $give_qb_access_token;
}

/**
 * Get oAuth refresh token.
 *
 * @since 1.0
 * @return mixed
 */
function give_qb_get_oauth_refresh_token() {
	$give_qb_refresh_token = give_get_option( 'give_quickbooks_refresh_token' );

	return $give_qb_refresh_token;
}

/**
 * Get oAuth refresh token.
 *
 * @since 1.0
 * @return mixed
 */
function give_qb_get_auth_code() {
	$give_qb_auth_code = give_get_option( 'give_quickbooks_auth_code' );

	return $give_qb_auth_code;
}

/**
 * Get Settings URL.
 *
 * @since 1.0
 * @return mixed
 */
function give_qb_get_settings_url() {
	// Return QuickBooks gateway setting page url.
	return admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=' . GIVE_QUICKBOOKS_SLUG );
}

/**
 * Get authorization header information.
 *
 * @since  1.0
 * @return string
 */
function give_qb_authorization_header() {
	$client_id                        = give_qb_get_client_id();
	$client_secret                    = give_qb_get_client_secret_id();
	$encoded_client_id_client_secrets = base64_encode( $client_id . ':' . $client_secret );
	$authorization_header             = 'Basic ' . $encoded_client_id_client_secrets;

	return $authorization_header;
}

/**
 * QuickBooks Frontend label.
 *
 * @since  1.0
 *
 * @return string
 */
function give_qb_payment_method_label() {
	return ( give_get_option( 'quickbooks_payment_method_label', false ) ? give_get_option( 'quickbooks_payment_method_label', '' ) : __( 'Credit Card (QuickBooks)', 'give-quickbooks-payments' ) );
}

/**
 * Handle Error message.
 *
 * @since 1.0
 *
 * @param $parsed_resp
 */
function give_qb_handle_error( $parsed_resp ) {

	if ( ! isset( $parsed_resp ) ) {
		$message = __( 'Authentication Fail', 'give-quickbooks-payments' );
		give_record_gateway_error( __( 'QuickBooks Error', 'give-quickbooks-payments' ), $message );
		give_set_error( 'request_error', $message );
		give_send_back_to_checkout( '?payment-mode=' . GIVE_QUICKBOOKS_SLUG );
	}

	if ( ! empty( $parsed_resp->errors ) ) {

		$message = '';
		$errors  = array();
		foreach ( $parsed_resp->errors as $err ) {
			$err_item = '';

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

		give_record_gateway_error( __( 'QuickBooks Error', 'give-quickbooks-payments' ), $message );
		give_set_error( 'request_error', $message );
		give_send_back_to_checkout( '?payment-mode=' . GIVE_QUICKBOOKS_SLUG );
		exit;
	}
}

/**
 * Stripe uses it's own credit card form because the card details are tokenized.
 *
 * We don't want the name attributes to be present on the fields in order to
 * prevent them from getting posted to the server.
 *
 * @param int  $form_id Donation Form ID.
 * @param bool $echo    Status to display or not.
 *
 * @access public
 * @since  1.0
 *
 * @return string $form
 */
function give_quickbooks_credit_card_form( $form_id, $echo = true ) {

	$billing_fields_enabled = give_get_option( 'quickbooks_collect_billing' );

	ob_start();

	do_action( 'give_before_cc_fields', $form_id ); ?>

	<fieldset id="give_cc_fields" class="give-do-validate">

		<legend><?php _e( 'Credit Card Info', 'give-quickbooks-payments' ); ?></legend>

		<?php if ( is_ssl() ) : ?>
			<div id="give_secure_site_wrapper">
				<span class="give-icon padlock"></span>
				<span>
					<?php _e( 'This is a secure SSL encrypted payment.', 'give-quickbooks-payments' ); ?>
				</span>
			</div>
		<?php endif; ?>

		<p id="give-card-number-wrap"
		   class="form-row form-row-two-thirds form-row-responsive">
			<label for="card_number" class="give-label">
				<?php _e( 'Card Number', 'give-quickbooks-payments' ); ?>
				<span class="give-required-indicator">*</span>
				<span class="give-tooltip give-icon give-icon-question"
				      data-tooltip="<?php _e( 'The (typically) 16 digits on the front of your credit card.', 'give-quickbooks-payments' ); ?>"></span>
				<span class="card-type"></span>
			</label>
			<input type="tel" autocomplete="off" name="card_number"
			       id="card_number" class="card-number give-input required"
			       placeholder="<?php _e( 'Card number', 'give-quickbooks-payments' ); ?>" />
		</p>

		<p id="give-card-cvc-wrap" class="form-row form-row-one-third form-row-responsive">
			<label for="card_cvc" class="give-label">
				<?php _e( 'CVC', 'give-quickbooks-payments' ); ?>
				<span class="give-required-indicator">*</span>
				<span class="give-tooltip give-icon give-icon-question"
				      data-tooltip="<?php _e( 'The 3 digit (back) or 4 digit (front) value on your card.', 'give-quickbooks-payments' ); ?>"></span>
			</label>
			<input type="tel" size="4" autocomplete="off"
			       name="card_cvc" id="card_cvc"
			       class="card-cvc give-input required"
			       placeholder="<?php _e( 'Security code', 'give-quickbooks-payments' ); ?>" />
		</p>

		<p id="give-card-name-wrap" class="form-row form-row-two-thirds form-row-responsive">
			<label for="card_name" class="give-label">
				<?php _e( 'Name on the Card', 'give-quickbooks-payments' ); ?>
				<span class="give-required-indicator">*</span>
				<span class="give-tooltip give-icon give-icon-question"
				      data-tooltip="<?php _e( 'The name printed on the front of your credit card.', 'give-quickbooks-payments' ); ?>"></span>
			</label>

			<input type="text" autocomplete="off"
			       name="card_name" id="card_name"
			       class="card-name give-input required"
			       placeholder="<?php _e( 'Card name', 'give-quickbooks-payments' ); ?>" />
		</p>

		<?php do_action( 'give_before_cc_expiration' ); ?>

		<p class="card-expiration form-row form-row-one-third form-row-responsive">
			<label for="card_expiry" class="give-label">
				<?php _e( 'Expiration', 'give-quickbooks-payments' ); ?>
				<span class="give-required-indicator">*</span>
				<span class="give-tooltip give-icon give-icon-question"
				      data-tooltip="<?php _e( 'The date your credit card expires, typically on the front of the card.', 'give-quickbooks-payments' ); ?>"></span>
			</label>

			<input type="hidden" id="card_exp_month"
			       name="card-expiry-month" class="card-expiry-month" />
			<input type="hidden" id="card_exp_year"
			       name="card-expiry-year" class="card-expiry-year" />

			<input type="tel" autocomplete="off"
			       name="card_expiry" id="card_expiry"
			       class="card-expiry give-input required"
			       placeholder="<?php _e( 'MM/YY', 'give-quickbooks-payments' ); ?>" />
		</p>

		<?php do_action( 'give_after_cc_expiration', $form_id ); ?>

	</fieldset>
	<?php

	// Remove Address Fields if user has option enabled
	if ( ! $billing_fields_enabled ) {
		remove_action( 'give_after_cc_fields', 'give_default_cc_address_fields' );
	}

	do_action( 'give_after_cc_fields', $form_id );

	$form = ob_get_clean();

	if ( false !== $echo ) {
		echo $form;
	}

	return $form;
}

add_action( 'give_quickbooks_cc_form', 'give_quickbooks_credit_card_form' );

/**
 * Add schedule of 30 min.
 *
 * @since 1.0
 *
 * @param $schedules
 *
 * @return mixed
 */
function give_qb_add_schedule( $schedules ) {
	$schedules['thirty_minute'] = array(
		'interval' => 1800,
		'display'  => esc_html__( 'Every Thirty Minute' ),
	);

	return $schedules;
}

// Add schedule cron time.
add_filter( 'cron_schedules', 'give_qb_add_schedule', 10, 1 );

/**
 * Run schedule event hook.
 *
 * @since 1.0
 */
function give_quickbooks_schedule_event() {
	// Make sure this event hasn't been scheduled
	if ( ! wp_next_scheduled( 'give_qb_check_access_token_expires' ) ) {
		// Schedule the event
		wp_schedule_event( time(), 'thirty_minute', 'give_qb_check_access_token_expires' );
	}
}

// Run scheduling event.
add_action( 'init', 'give_quickbooks_schedule_event' );


/**
 * Check whether access_token expires.
 *
 * @since 1.0
 */
function give_qb_check_access_token_expires() {

	Give_QuickBooks_API::looking_for_access_token();
}

add_action( 'give_qb_check_access_token_expires', 'give_qb_check_access_token_expires' );