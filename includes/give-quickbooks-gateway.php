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

		$this->look_for_access_token();

		// Registering QuickBooks Payment gateway with give.
		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );

		// Add QuickBooks Settings section.
		add_action( 'give_get_settings_gateways', array( $this, 'add_settings' ) );
		add_action( 'give_get_sections_gateways', array( $this, 'add_section' ) );

		// Adding Give QuickBooks auth button into Give WP setting API.
		add_action( 'give_admin_field_quickbooks_auth_button', array( $this, 'quickbooks_auth_button_callback' ), 10, 2 );
	}

	public function look_for_access_token() {
		if ( empty( $_GET['code'] ) ) {
			return false;
		}

		if ( empty( $_GET['realmId'] ) ) {
			return false;
		}

		$responseState = $_GET['state'];
		if ( strcmp( 'RandomState', $responseState ) != 0 ) {
			throw new Exception( "The state is not correct from Intuit Server. Consider your app is hacked." );
		}

		if ( ! isset( $_GET["code"] ) ) {

			$this->get_connect_url();

		} else {

			$code          = give_clean( $_GET['code'] );
			$responseState = $_GET['state'];

			if ( strcmp( 'RandomState', $responseState ) != 0 ) {
				throw new Exception( "The state is not correct from Intuit Server. Consider your app is hacked." );
			}

			$result = $this->getAccessToken( $tokenEndPointUrl = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', $code, $grant_type = 'authorization_code' );


			$result        = json_decode( $result );
			$refresh_token = $result['refresh_token'];
			$access_token  = $result['access_token'];

			update_option( 'give_quickbooks_access_token', $access_token );
			update_option( 'give_quickbooks_refresh_token', $refresh_token );
		}

	}

	public function getAccessToken( $tokenEndPointUrl, $code, $grant_type ) {
		if ( ! isset( $grant_type ) ) {
			throw new InvalidArgumentException( 'The grant_type is mandatory.', InvalidArgumentException::INVALID_GRANT_TYPE );
		}

		$authorizationHeaderInfo = $this->generateAuthorizationHeader();

		//Try catch???
		$result = wp_remote_post( $tokenEndPointUrl, array(
			'headers' => array(
				'Authorization' => $authorizationHeaderInfo,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'   => $grant_type,
				'code'         => $code,
				'redirect_uri' => 'http://quickbook.test/wp-admin/edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=quickbooks',
			),
		) );

		return $result;
	}

	private function generateAuthorizationHeader() {
		$client_id                    = give_get_option( 'give_quickbooks_client_id' );
		$client_secret                = give_get_option( 'give_quickbooks_client_secret' );
		$encodedClientIDClientSecrets = base64_encode( $client_id . ':' . $client_secret );
		$authorizationheader          = 'Basic ' . $encodedClientIDClientSecrets;

		return $authorizationheader;
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
				'name' => __( 'Connect / Disconnect', 'give-gocardless' ),
				'desc' => 'Connect / Disconnect Development or Production.',
				'id'   => 'give_quickbooks_auth_button',
				'type' => 'quickbooks_auth_button',
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

		// Check if connected or not.
		$is_connected = $this->is_connected();

		// Get the connected data from the database.
		$get_connected_data = self::get_quickbooks_auth_data();
		?>
		<tr valign="top" <?php echo ! empty( $value['wrapper_class'] ) ? 'class="' . $value['wrapper_class'] . '"' : '' ?>>
			<th scope="row" class="titledesc">
				<label for=""><?php echo Give_Admin_Settings::get_field_title( $value ); ?></label>
			</th>
			<td class="gocardless-auth" colspan="2">
				<a class="connect-quickbooks-button"
				   href="<?php echo ( $is_connected ) ? $this->get_disconnect_url() : $this->get_connect_url(); ?>"><img width="225px"
				                                                                                                         src="<?php echo GIVE_QUICKBOOKS_PLUGIN_URL . 'assets/images/C2QB_green_btn_lg_default.png' ?>">
				</a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Check whether we're connected with QuickBooks app or not.
	 *
	 * @access  protected
	 * @since   1.0.0
	 *
	 * @return  bool|true     if access_token is available.
	 *          bool|false    if access_token is not available.
	 */
	protected function is_connected() {

		// Get the authentication setting of QuickBooks.
		$auth_data = self::get_quickbooks_auth_data();

		// Check whether there is action token is available or not.
		if ( isset( $auth_data['access_token'] ) && ! empty( $auth_data['access_token'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get QuickBooks oAuth data.
	 *
	 * @access  public
	 * @since   1.0.0
	 *
	 * @return  array $gateway_data Get Quickbooks authentication data.
	 */
	public static function get_quickbooks_auth_data() {

		// Retrieve QuickBooks authentication setting data.
		$gateway_data = give_get_option( 'give_quickbooks_settings' );

		return $gateway_data;
	}

	/**
	 * Get disconnect URL.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  string Disconnect authentication URL with QuickBooks API.
	 */
	public function get_disconnect_url() {
		return add_query_arg( array(
			'give_quickbooks_disconnect'       => 'true',
			'give_quickbooks_disconnect_nonce' => wp_create_nonce( 'give_disconnect_quickbooks' ),
		), $this->get_setting_url() );
	}

	/**
	 * Get setting URL.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  string QuickBooks Gateway setting page url.
	 */
	public function get_setting_url() {

		// Return QuickBooks gateway setting page url.
		return admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=' . GIVE_QUICKBOOKS_SLUG );
	}

	/**
	 * Generate oAuth Connection URL.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  string  authentication URL with QuickBooks.
	 */
	public function generate_connection_url() {
		return $this->get_connect_url();
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
	public function get_connect_url( $args = array() ) {

		// Create argument list.
		$args = wp_parse_args( $args, array(
			'redirect_uri'          => 'http://quickbook.test/wp-admin/edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=quickbooks',
			'client_id'             => give_get_option( 'give_quickbooks_client_id' ),
			'scope'                 => 'com.intuit.quickbooks.payment',
			'give_quickbooks_nonce' => wp_create_nonce( 'give_quickbooks_nonce' ),
			'response_type'         => 'code',
			'state'                 => 'RandomState',
		) );

		$authorizationRequestUrl = 'https://appcenter.intuit.com/connect/oauth2';
		$authorizationRequestUrl .= '?' . http_build_query( $args, null, '&', PHP_QUERY_RFC1738 );

		return $authorizationRequestUrl;
	}

}
