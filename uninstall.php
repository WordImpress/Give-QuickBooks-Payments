<?php
/**
 * Give QuickBooks Uninstall
 *
 * @link              https://wordimpress.com
 * @since             1.0.0
 * @package           Give_QuickBooks_Payments
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clear CRON jobs.
wp_clear_scheduled_hook( 'give_qb_check_access_token_expires', array( 'thirty_minute' ) );

// Get Give core settings.
$give_settings = give_get_settings();

// List of plugin Global settings.
$plugin_settings = array(
	'give_quickbooks_client_id',
	'give_quickbooks_client_secret',
	'give_quickbooks_collect_billing',
	'give_quickbooks_payment_method_label',
	'give_quickbooks_access_token',
	'give_quickbooks_refresh_token',
	'give_quickbooks_connected',
	'give_quickbooks_realm_id',
	'give_quickbooks_auth_code',
);

foreach ( $give_settings as $setting_key => $setting ) {
	if ( in_array( $setting_key, $plugin_settings, true )
	     || 'give_quickbooks' === substr( $setting_key, 0, 15 )
	) {
		unset( $give_settings[ $setting_key ] );
	}
}
// Update settings.
update_option( 'give_settings', $give_settings );