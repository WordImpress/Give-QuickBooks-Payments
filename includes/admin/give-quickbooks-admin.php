<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wordimpress.com
 * @since      1.0.0
 *
 * @package    Give_QuickBooks_Payments
 * @subpackage Give_QuickBooks_Payments/includes/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Give_QuickBooks_Payments
 * @subpackage Give_QuickBooks_Payments/includes/admin
 * @author     WordImpress
 */
class Give_QuickBooks_Admin {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since   1.0
	 * @access  public
	 */
	public function __construct() {

		// Add QuickBooks Settings section.
		add_action( 'give_get_settings_gateways', array( $this, 'add_settings' ) );
		add_action( 'give_get_sections_gateways', array( $this, 'add_section' ) );

		// Adding Give QuickBooks auth button into Give WP setting API.
		add_action( 'give_admin_field_quickbooks_auth_button', array( $this, 'quickbooks_auth_button_callback' ), 10, 2 );

		// Call admin notice.
		add_action( 'admin_notices', array( $this, 'quickbooks_render_admin_notice' ) );

		// Add custom js on payment details page.
		add_action( 'give_view_order_details_before', array( $this, 'admin_payment_js' ), 100, 1 );

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
				'desc' => 'Connect / Disconnect oAuth for Development/Production.',
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
				'name'    => __( 'Payment Method Label', 'give-quickbooks-payments' ),
				'desc'    => __( 'Payment method label will be appear on frontend.', 'give-quickbooks-payments' ),
				'id'      => 'quickbooks_payment_method_label',
				'default' => give_qb_payment_method_label(),
				'type'    => 'text',
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

		$connection_status =  false;
		if ( ! empty( give_qb_get_client_id() )
		     && ! empty( give_qb_get_auth_code() )
		     && 'quickbooks' === give_get_current_setting_section()
		) {
			$auth_response = Give_QuickBooks_API::get_auth_refresh_access_token();
			$response_code = wp_remote_retrieve_response_code( (array) $auth_response );

			give_update_option( 'give_qb_connected', false );
			if ( 200 === $response_code ) {
				give_update_option( 'give_qb_connected', true );
				$connection_status = true;
			}
		}
		?>
		<tr valign="top" <?php echo ! empty( $value['wrapper_class'] ) ? 'class="' . $value['wrapper_class'] . '"' : '' ?>>
			<th scope="row" class="titledesc">
				<label for=""><?php echo Give_Admin_Settings::get_field_title( $value ); ?></label>
			</th>
			<td class="qb-auth" colspan="2">
				<a class="connect-quickbooks-button"
				   href="<?php echo $this->get_qb_connect_url(); ?>">
					<img width="225px" src="<?php echo GIVE_QUICKBOOKS_PLUGIN_URL . 'assets/images/qb_connect_bg.png' ?>">
				</a>
				<?php if ( ! $connection_status ) { ?>
					<p class="qb-auth-status-wrap">
						<strong style="float: left; margin-right: 5px;" class="qb-auth-status-label"><?php _e( 'Status: ', 'give-quickbooks-payments' ); ?></strong>
						<span class="qb-auth-status" style="font-style: italic; float:left; color: red;"><?php _e( 'Not Connected', 'give-quickbooks-payments' ); ?></span>
					</p>
				<?php } else { ?>
					<p class="qb-auth-status-wrap">
						<strong style="float: left; margin-right: 5px;" class="qb-auth-status-label"><?php _e( 'Status: ', 'give-quickbooks-payments' ); ?></strong>
						<span class="qb-auth-status" style="font-style: italic; float:left; color: green;"><?php _e( 'Connected', 'give-quickbooks-payments' ); ?></span>
					</p>
					<?php
				} ?>
				<p style="width: 100%; float: left; clear: both;" class="give-field-description"><?php echo Give_Admin_Settings::get_field_description( $value ); ?></p>
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
	 * Render admin notice.
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function quickbooks_render_admin_notice() {

		if ( ! empty( $_GET['give-message'] ) ) {
			// Give settings notices and errors.
			if ( current_user_can( 'manage_give_settings' ) ) {
				switch ( $_GET['give-message'] ) {
					case 'qb-auth-connected' :
						Give()->notices->register_notice( array(
							'id'          => 'qb-auth-connected',
							'type'        => 'updated',
							'description' => __( 'You have successfully authenticated with QuickBooks.', 'give-quickbooks-payments' ),
							'show'        => true,
						) );
						break;
					case 'qb-auth-rejected' :
						Give()->notices->register_notice( array(
							'id'          => 'qb-auth-rejected',
							'type'        => 'error',
							'description' => __( 'You have not authenticated with QuickBooks.', 'give-quickbooks-payments' ),
							'show'        => true,
						) );
						break;
				}

			}
		}
	}

	/**
	 * Add the input field when change the payment status drop-down on payment details page
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $payment_id
	 */
	public function admin_payment_js( $payment_id = 0 ) {

		if ( GIVE_QUICKBOOKS_SLUG !== give_get_payment_gateway( $payment_id ) ) {
			return;
		}
		?>
		<script type="text/javascript">
					jQuery( document ).ready( function( $ ) {
						$( 'select[name=give-payment-status]' ).change( function() {
							$( '.give-quickbooks-refund' ).remove();
							$( '#give_cancellation_in_quickbooks' ).remove();
							if ( 'refunded' === $( this ).val() ) {
								$( this ).parent().parent().append( '<p class="give-quickbooks-refund"><input type="checkbox" id="give_refund_in_quickbooks" name="give_refund_in_quickbooks" value="1"/><label for="give_refund_in_quickbooks"><?php esc_html_e( 'Refund Charge in QuickBooks?', 'give-quickbooks-payments' ); ?></label></p>' );
							} else if ( 'cancelled' === $( this ).val() ) {
								$( this ).parent().parent().append( '<input type="hidden" id="give_cancellation_in_quickbooks" name="give_cancellation_in_quickbooks" value="1"/>' );
							}
						} );
					} );
		</script>
		<?php
	}

}
