<?php
/**
 * Plugin Name: Fincon Connector by Digital Fold
 * Plugin URI: https://digitalfold.co.za/
 * Description: WooCommerce Fincon connector 
 * Version: 0.0.1 
 * Author: Digital Fold
 * Author URI: https://digitalfold.co.za/
 * Contributors: Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * Requires at least: 6.8
 * Tested up to: 6.8
 * Requires PHP: 8.1+
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 8.0
 * WC tested up to: 8.8
 * WC HPOS compatible: true
 * Requires Plugins: woocommerce
 * Text Domain: df-fincon
 * 
 * @author  Digital Fold
 * @package df-fincon-connector
 * 
 */

/*
 * == Changelog ==
 * 0.0.1 - 2025-10-17 - Initial
 */


// Exit if accessed directly
defined( 'ABSPATH' ) || exit;


// Define plugin constants
define('DF_FINCON_VERSION', '0.0.1');
define('DF_FINCON_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DF_FINCON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DF_FINCON_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('DF_FINCON_PLUGIN_SLUG', str_replace( '-', '_', sanitize_title( dirname( DF_FINCON_PLUGIN_BASENAME ) ) ) );


add_action( 'before_woocommerce_init', 'df_fincon_declare_HPOS' );
function df_fincon_declare_HPOS() {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
      'custom_order_tables',
      __FILE__,
      true
    );
  }
};


add_action( 'plugins_loaded', 'df_fincon_load_plugin' );
function df_fincon_load_plugin(): void {
  require_once DF_FINCON_PLUGIN_DIR . '/includes/' . '_autoloader.php';
  DF_FINCON\Plugin::create( DF_FINCON_PLUGIN_SLUG );
}


