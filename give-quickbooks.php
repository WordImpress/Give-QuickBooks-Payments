<?php
/**
 * Plugin Name:       Give - QuickBooks Gateway
 * Plugin URI:        https://givewp.com/addons/#/
 * Description:       Accept donations through the QuickBooks Payments gateway.
 * Version:           1.0
 * Author:            WordImpress
 * Author URI:        https://wordimpress.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       give-quickbooks-payments
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! class_exists( 'Give_QuickBooks_Payments' ) ) :

	/**
	 * Give_QuickBooks_Payments Class
	 *
	 * @package Give_QuickBooks_Payments
	 * @since   1.0
	 */
	final class Give_QuickBooks_Payments {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of Give_QuickBooks_Payments exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 */
		private static $instance;

		/**
		 * Give QuickBooks Payment Admin Object.
		 *
		 * @since  1.0
		 * @access public
		 *
		 * @var Give_QuickBooks_Admin object.
		 */
		public $plugin_admin;

		/**
		 * Give QuickBooks Payment Gateway Object.
		 *
		 * @since  1.0
		 * @access public
		 *
		 * @var Give_QuickBooks_Gateway object.
		 */
		public $quickbooks_gateway;

		/**
		 * Get the instance and store the class inside it. This plugin utilises
		 * the PHP singleton design pattern.
		 *
		 * @since     1.0
		 * @static
		 * @staticvar array $instance
		 * @access    public
		 *
		 * @see       Give_QuickBooks_Payments();
		 *
		 * @uses      Give_QuickBooks_Payments::hooks() Setup hooks and actions.
		 * @uses      Give_QuickBooks_Payments::includes() Loads all the classes.
		 * @uses      Give_QuickBooks_Payments::licensing() Add Give - QuickBooks Payment License.
		 *
		 * @return object self::$instance Instance
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Give_QuickBooks_Payments ) ) {
				self::$instance = new Give_QuickBooks_Payments();
				self::$instance->setup();
			}

			return self::$instance;
		}

		/**
		 * Setup Give - QuickBooks Payments.
		 *
		 * @since  1.0
		 * @access private
		 */
		private function setup() {
			self::$instance->setup_constants();

			add_action( 'give_init', array( $this, 'init' ), 10 );
			add_action( 'plugins_loaded', array( $this, 'check_environment' ), 999 );
		}

		/**
		 * Init Give - QuickBooks Payments.
		 *
		 * Sets up hooks, licensing and includes files.
		 *
		 * @since  1.0
		 * @access public
		 *
		 * @return void
		 */
		public function init() {
			if ( ! self::$instance->check_environment() ) {
				return;
			}

			self::$instance->hooks();
			self::$instance->licensing();
			self::$instance->includes();
		}

		/**
		 * Check plugin environment.
		 *
		 * @since  1.0
		 * @access public
		 *
		 * @return bool
		 */
		public function check_environment() {

			// Load plugin helper functions.
			if ( ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}

			// Flag to check whether deactivate plugin or not.
			$is_deactivate = false;

			// Verify dependency cases.
			switch ( true ) {
				case doing_action( 'give_init' ):
					if (
						defined( 'GIVE_VERSION' ) &&
						version_compare( GIVE_VERSION, GIVE_QUICKBOOKS_MIN_GIVE_VER, '<' )
					) {
						/* Min. Give. plugin version. */
						// Show admin notice.
						$message = sprintf(
							'<strong>%1$s</strong> %2$s <a href="%3$s" target="_blank">%4$s</a> %5$s',
							__( 'Activation Error:', 'give-quickbooks-payments' ),
							__( 'You must have', 'give-quickbooks-payments' ),
							esc_url( 'https://givewp.com' ),
							__( 'Give', 'give-quickbooks-payments' ),
							sprintf( __( 'core version %1$s+ for the Give - QuickBooks Payment Gateway add-on to activate.', 'give-quickbooks-payments' ), GIVE_QUICKBOOKS_MIN_GIVE_VER )
						);

						$class = 'notice notice-error';
						printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );

						$is_deactivate = true;
					}

					break;

				case doing_action( 'plugins_loaded' ) && ! did_action( 'give_init' ):
					/* Check to see if Give is activated, if it isn't deactivate and show a banner. */

					// Check for if give plugin activate or not.
					$is_give_active = defined( 'GIVE_PLUGIN_BASENAME' ) ? is_plugin_active( GIVE_PLUGIN_BASENAME ) : false;

					if ( ! $is_give_active ) {
						// Show admin notice.
						$message = sprintf(
							'<strong>%1$s</strong> %2$s <a href="%3$s" target="_blank">%4$s</a> %5$s',
							__( 'Activation Error:', 'give-quickbooks-payments' ),
							__( 'You must have', 'give-quickbooks-payments' ),
							esc_url( 'https://givewp.com' ),
							__( 'Give', 'give-quickbooks-payments' ),
							__( 'plugin installed and activated for QuickBooks Payment Gateway to activate.', 'give-quickbooks-payments' )
						);

						$class = 'notice notice-error';
						printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );

						$is_deactivate = true;
					}

					break;
			}// End switch().

			// Don't let this plugin activate.
			if ( $is_deactivate ) {
				// Deactivate plugin.
				deactivate_plugins( GIVE_QUICKBOOKS_BASENAME );

				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}

				return false;
			}

			return true;
		}

		/**
		 * Setup constants.
		 *
		 * @since   1.0.
		 * @access  private
		 */
		private function setup_constants() {
			/**
			 * Define constants.
			 * Required minimum versions, paths, urls, etc.
			 *
			 * @since    1.0
			 */
			if ( ! defined( 'GIVE_QUICKBOOKS_VERSION' ) ) {
				define( 'GIVE_QUICKBOOKS_VERSION', '1.0' );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_SLUG' ) ) {
				define( 'GIVE_QUICKBOOKS_SLUG', 'quickbooks' );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_PLUGIN_FILE' ) ) {
				define( 'GIVE_QUICKBOOKS_PLUGIN_FILE', __FILE__ );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_PLUGIN_DIR' ) ) {
				define( 'GIVE_QUICKBOOKS_PLUGIN_DIR', dirname( GIVE_QUICKBOOKS_PLUGIN_FILE ) );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_PLUGIN_URL' ) ) {
				define( 'GIVE_QUICKBOOKS_PLUGIN_URL', plugin_dir_url( GIVE_QUICKBOOKS_PLUGIN_FILE ) );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_BASENAME' ) ) {
				define( 'GIVE_QUICKBOOKS_BASENAME', plugin_basename( GIVE_QUICKBOOKS_PLUGIN_FILE ) );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_MIN_GIVE_VER' ) ) {
				define( 'GIVE_QUICKBOOKS_MIN_GIVE_VER', '2.0' );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_SANDBOX_BASE_URL' ) ) {
				define( 'GIVE_QUICKBOOKS_SANDBOX_BASE_URL', 'https://sandbox.api.intuit.com' );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_PRODUCTION_BASE_URL' ) ) {
				define( 'GIVE_QUICKBOOKS_PRODUCTION_BASE_URL', 'https://api.intuit.com' );
			}
			if ( ! defined( 'GIVE_QUICKBOOKS_ACCESS_TOKEN_ENDPOINT' ) ) {
				define( 'GIVE_QUICKBOOKS_ACCESS_TOKEN_ENDPOINT', 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer' );
			}
		}

		/**
		 * Throw error on object clone.
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 *
		 * @since  1.0
		 * @access protected
		 *
		 * @return void
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'give-quickbooks-payments' ), '1.0' );
		}

		/**
		 * Disable Unserialize of the class.
		 *
		 * @since  1.0
		 * @access protected
		 *
		 * @return void
		 */
		public function __wakeup() {
			// Unserialize instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'give-quickbooks-payments' ), '1.0' );
		}

		/**
		 * Constructor Function.
		 *
		 * @since  1.0
		 * @access protected
		 */
		public function __construct() {
			self::$instance = $this;
		}

		/**
		 * Reset the instance of the class
		 *
		 * @since  1.0
		 * @access public
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Includes.
		 *
		 * @since  1.0
		 * @access private
		 *
		 * - Give_QuickBooks_Admin. Defines all hooks for the admin area.
		 * - Give_QuickBooks_Gateway. QuickBooks Payment Gateway
		 */
		private function includes() {
			/**
			 * The class responsible for defining all actions that occur in the admin area.
			 */
			require_once( GIVE_QUICKBOOKS_PLUGIN_DIR . '/includes/admin/give-quickbooks-admin.php' );

			/**
			 * The class responsible for gateway setting.
			 */
			require_once GIVE_QUICKBOOKS_PLUGIN_DIR . '/includes/give-quickbooks-gateway.php';

			/**
			 * The file is includes for QuickBooks gateway helpers.
			 */
			require_once GIVE_QUICKBOOKS_PLUGIN_DIR . '/includes/give-quickbooks-helpers.php';

			/**
			 * QuickBooks API.
			 */
			require_once GIVE_QUICKBOOKS_PLUGIN_DIR . '/includes/give-quickbooks-api.php';

			self::$instance->plugin_admin      = new Give_QuickBooks_Admin();
			self::$instance->quickbooks_gateway = new Give_QuickBooks_Gateway();

		}

		/**
		 * Hooks.
		 *
		 * @since  1.0
		 * @access public
		 */
		public function hooks() {
			add_action( 'init', array( $this, 'load_textdomain' ) );
			add_action( 'admin_init', array( $this, 'activation_banner' ) );
			add_filter( 'plugin_action_links_' . GIVE_QUICKBOOKS_BASENAME, array( $this, 'action_links' ), 10, 2 );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		}

		/**
		 * Implement Give Licensing for Give - QuickBooks Payment Gateway Add On.
		 *
		 * @since  1.0
		 * @access private
		 */
		private function licensing() {
			if ( class_exists( 'Give_License' ) ) {
				new Give_License(
					GIVE_QUICKBOOKS_PLUGIN_FILE,
					'QuickBooks Gateway',
					GIVE_QUICKBOOKS_VERSION,
					'WordImpress'
				);
			}
		}

		/**
		 * Load Plugin Text Domain
		 *
		 * Looks for the plugin translation files in certain directories and loads
		 * them to allow the plugin to be localised
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return bool True on success, false on failure.
		 */
		public function load_textdomain() {
			// Traditional WordPress plugin locale filter.
			$locale = apply_filters( 'plugin_locale', get_locale(), 'give-quickbooks-payments' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'give-quickbooks-payments', $locale );

			// Setup paths to current locale file.
			$mofile_local = trailingslashit( GIVE_QUICKBOOKS_PLUGIN_DIR . 'languages' ) . $mofile;

			if ( file_exists( $mofile_local ) ) {
				// Look in the /wp-content/plugins/Give-QuickBooks-Payments/languages/ folder.
				load_textdomain( 'give-quickbooks-payments', $mofile_local );
			} else {
				// Load the default language files.
				load_plugin_textdomain( 'give-quickbooks-payments', false, trailingslashit( GIVE_QUICKBOOKS_PLUGIN_DIR . 'languages' ) );
			}

			return false;
		}

		/**
		 * Activation banner.
		 *
		 * Uses Give's core activation banners.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function activation_banner() {

			// Check for activation banner inclusion.
			if ( ! class_exists( 'Give_Addon_Activation_Banner' ) && file_exists( GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php' ) ) {
				include GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';
			}

			// Initialize activation welcome banner.
			if ( class_exists( 'Give_Addon_Activation_Banner' ) ) {

				// Only runs on admin.
				$args = array(
					'file'              => GIVE_QUICKBOOKS_PLUGIN_FILE,
					'name'              => __( 'QuickBooks Gateway', 'give-quickbooks-payments' ),
					'version'           => GIVE_QUICKBOOKS_VERSION,
					'settings_url'      => admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=quickbooks' ),
					'documentation_url' => 'https://givewp.com/documentation/add-ons/#/',
					'support_url'       => 'https://givewp.com/support/',
					'testing'           => false,
				);
				new Give_Addon_Activation_Banner( $args );
			}

			return true;
		}

		/**
		 * Adding additional setting page link along plugin's action link.
		 *
		 * @since   1.0.0
		 * @access  public
		 *
		 * @param   array $actions get all actions.
		 *
		 * @return  array       return new action array
		 */
		function action_links( $actions ) {

			if ( ! class_exists( 'Give' ) ) {
				return $actions;
			}

			// Check min Give version.
			if ( defined( 'GIVE_QUICKBOOKS_MIN_GIVE_VER' ) && version_compare( GIVE_VERSION, GIVE_QUICKBOOKS_MIN_GIVE_VER, '<' ) ) {
				return $actions;
			}

			$new_actions = array(
				'settings' => sprintf( '<a href="%1$s">%2$s</a>', admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=quickbooks' ), __( 'Settings', 'give-quickbooks-payments' ) ),
			);

			return array_merge( $new_actions, $actions );

		}

		/**
		 * Plugin row meta links.
		 *
		 * @since   1.0.0
		 * @access  public
		 *
		 * @param   array  $plugin_meta An array of the plugin's metadata.
		 * @param   string $plugin_file Path to the plugin file, relative to the plugins directory.
		 *
		 * @return  array  return meta links for plugin.
		 */
		function plugin_row_meta( $plugin_meta, $plugin_file ) {

			if ( ! class_exists( 'Give' ) ) {
				return $plugin_meta;
			}

			// Return if not Give QuickBooks Payment plugin.
			if ( $plugin_file !== GIVE_QUICKBOOKS_BASENAME ) {
				return $plugin_meta;
			}

			$new_meta_links = array(
				sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( add_query_arg( array(
					'utm_source'   => 'plugins-page',
					'utm_medium'   => 'plugin-row',
					'utm_campaign' => 'admin',
				), 'https://givewp.com/documentation/add-ons/#' ) ), __( 'Documentation', 'give-quickbooks-payments' ) ),
				sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( add_query_arg( array(
					'utm_source'   => 'plugins-page',
					'utm_medium'   => 'plugin-row',
					'utm_campaign' => 'admin',
				), 'https://givewp.com/addons/' ) ), __( 'Add-ons', 'give-quickbooks-payments' ) ),
			);

			return array_merge( $plugin_meta, $new_meta_links );

		}

	} //End Give_QuickBooks_Payments Class.

endif;

/**
 * Loads a single instance of Give QuickBooks Payment.
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $give_quickBooks_payments = Give_QuickBooks_Payments(); ?>
 *
 * @since   1.0.0
 *
 * @see     Give_QuickBooks_Payments::get_instance()
 *
 * @return object Give_QuickBooks_Payments Returns an instance of the  class
 */
function Give_QuickBooks_Payments() {
	return Give_QuickBooks_Payments::get_instance();
}

Give_QuickBooks_Payments();