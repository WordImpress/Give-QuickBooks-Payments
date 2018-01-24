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
		// Registering QuickBooks Payment gateway with give.
		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );

		// Add QuickBooks Settings section.
		add_action( 'give_get_settings_gateways', array( $this, 'add_settings' ) );
		add_action( 'give_get_sections_gateways', array( $this, 'add_section' ) );
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
	 * @return  array Get the settings fields of GoCardless settings.
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
				'desc'    => __( 'Please enter Client ID from your application.', 'give-quickbooks-payments' ),
				'id'      => 'give_quickbooks_client_id',
				'default' => '',
				'type'    => 'text',
			),
			array(
				'name'    => __( 'Client Secret', 'give-quickbooks-payments' ),
				'desc'    => __( 'Please enter Client Secret from your application.', 'give-quickbooks-payments' ),
				'id'      => 'give_quickbooks_client_secret',
				'default' => '',
				'type'    => 'text',
			),
			array(
				'name'  => __( 'Give QuickBooks Gateway Settings Docs Link', 'give-gocardless' ),
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

}
