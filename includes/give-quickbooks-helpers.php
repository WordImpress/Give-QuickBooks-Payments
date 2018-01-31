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
function generate_unique_request_id() {
	$guid = sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
		mt_rand(0, 65535), mt_rand(0, 65535),
		mt_rand(0, 65535),
		mt_rand(0, 4095),
		bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
		mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
	);

	return $guid;
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