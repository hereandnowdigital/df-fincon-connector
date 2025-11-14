<?php
/**
 * Main class for plugin: Fincon Connector by Digital Fold
 *
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * Text Domain: df-fincon
 * 
 */

namespace DF_FINCON;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class Plugin {
  
  /**
   * Plugin slug
   * - used for WC_Logger source
   * @var string
   */     
  const SLUG = DF_FINCON_PLUGIN_SLUG;

  /**
   * Plugin options key
   * @var string
   */    
  const OPTIONS_NAME = self::SLUG . '_options';

  /**
   * Singleton instance
   * @var self|null
   */
  private static ?self $instance = null;

  public function __construct( ) {

    if ( self::$instance !== null ) 
      throw new \Exception( self::SLUG . ' plugin already initialized.');

    self::$instance = $this;    
    $this->load_dependencies();
  }

  public static function create( ): static {
    if ( self::$instance === null ) 
      new static( );
    return self::$instance;
  }

  private function load_dependencies(): void {
    if ( class_exists( 'WooCommerce' ) ) :
      Logger::create( );
      Woo::create( );
    endif;
    
    if ( is_admin() ) 
      Admin::create( );

  }

}