<?php
  /**
   * WooCommerce Functionality
   *
   * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
   * @package df-fincon-connector
   * Text Domain: df-fincon
   * */

  namespace DF_FINCON;

  use TypeError;
  
  // Exit if accessed directly.
  if ( ! defined( 'ABSPATH' ) )
    exit;

class Woo {

  private static ?self $instance = null;
  
  
  const LAST_SYNC_META_KEY = ProductSync::FINCON_PRODUCT_CHANGED_META_KEY;

  const LAST_SYNC_DATETIME_META_KEY = ProductSync::LAST_SYNC_DATETIME_META_KEY;

  private static $product_meta_fincon_data = ProductSync::PRODUCT_META_FINCON_DATA;


  private static $product_meta_selling_prices = ProductSync::PRODUCT_META_SELLING_PRICES;

  private static $product_meta_stock = ProductSync::PRODUCT_META_STOCK;

  private function __construct( ) {
    self::init();
  }  

 public static function create(  ): self {
    if ( self::$instance === null ) {
      self::$instance = new self( );
    }
    return self::$instance;
  }

  private static function init() {
    self::register_actions();
    self::register_filters();
  }

  private static function register_actions(): void {
    add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'display_fincon_product_data_panel' ] );
    add_action( 'woocommerce_product_options_pricing', [__CLASS__, 'display_selling_prices_fields']);
    add_action( 'woocommerce_admin_process_product_object', [__CLASS__, 'save_selling_prices_fields']);
    add_action( 'woocommerce_product_options_inventory_product_data', [__CLASS__, 'display_stock_fields']);
    add_action( 'woocommerce_admin_process_product_object', [__CLASS__, 'save_stock_fields' ]);
  }

  private static function register_filters(): void {
    add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'add_fincon_product_data_tab' ] );
  }

  /**
   * Adds the new "FinCon" tab to the WooCommerce Product Data metabox.
   *
   * @param array $product_data_tabs Existing tabs array.
   * @return array Modified tabs array.
   */
  public static function add_fincon_product_data_tab( array $product_data_tabs ): array {
    $product_data_tabs['fincon_sync'] = array(
      'label'    => __( 'FinCon Data', 'df-fincon' ),
      'target'   => 'fincon_product_data',
      'class'    => array( 'show_if_simple', 'show_if_variable' ),
      'priority' => 80, 
    );
    return $product_data_tabs;
  }

  /**
   * Displays the content within the new "FinCon" product data panel.
   * This is where we display the sync metadata.
   */
  public static function display_fincon_product_data_panel(): void {
    global $post;
    
    $product = wc_get_product( $post->ID );
    
    $fincon_timestamp = $product->get_meta( self::LAST_SYNC_META_KEY, true );
    $sync_timestamp = $product->get_meta( self::LAST_SYNC_DATETIME_META_KEY, true );
    $item_no = $product->get_meta( "ItemNo", true );

    echo '<div id="fincon_product_data" class="panel woocommerce_options_panel">';
    echo '<div class="options_group">';
    echo '<h3>' . __( 'FinCon Synchronization Data', 'df-fincon' ) . '</h3>';
    echo '<p>';
        
    if ( ! empty( $fincon_timestamp ) ) {
        $formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fincon_timestamp );
        
        echo '<p>';
        printf(
            esc_html__( 'FinCon product changed timestamp: %s', 'df-fincon' ),
            '<strong>' . esc_html( $formatted_date ) . '</strong>'
        );
        echo '</p>';
        
    } else {
        echo '<p>' . esc_html__( 'This product has not yet been synchronized with the FinCon API.', 'df-fincon' ) . '</p>';
    }

    if ( ! empty( $sync_timestamp ) ) {
        $formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sync_timestamp );
        
        echo '<p>';
        printf(
            esc_html__( 'Product last synchronized: %s', 'df-fincon' ),
            '<strong>' . esc_html( $formatted_date ) . '</strong>'
        );
        echo '</p>';
    } else {
        echo '<p>' . esc_html__( 'This product has not yet been synchronized by the plugin.', 'df-fincon' ) . '</p>';
    }

echo '<h3>' . __( 'FinCon Data', 'df-fincon' ) . '</h3>';
    echo '<table class="widefat striped fincon-meta-table">';
    echo '<tbody>';

    foreach ( self::$product_meta_fincon_data as $meta_key => $label ) {
        $value = $product->get_meta( $meta_key );
        if ( $value !== '' ) {
            if ( in_array( $meta_key, ['ProFromDate','ProToDate'], true ) ) {
                $value = date_i18n( get_option('date_format'), strtotime( $value ) );
            }
            echo '<tr>';
            echo '<th>' . esc_html( $label ) . '</th>';
            echo '<td>' . esc_html( $value ) . '</td>';
            echo '</tr>';
        }
    }
  
    echo '</tbody>';
    echo '</table>';

    echo '</div>'; // .options_group
    echo '</div>'; // .panel
  }

  public static function display_selling_prices_fields(): void {
    echo '<div class="options_group">';

      $i = 0;
      foreach ( self::$product_meta_selling_prices as $field_id => $label ): 
        $i++;
        woocommerce_wp_text_input( array(
          'id'                => $field_id,
          'label'             => sprintf( __( 'Selling Price %d (R)', 'df-fincon' ), $i ),
          'desc_tip'          => true,
          'type'              => 'number',
          'custom_attributes' => array(
              'step' => '0.01',
              'min'  => '0',
          ),
        ) );
      endforeach;

    echo '</div>';
  }

  public static function save_selling_prices_fields( $product ): void {
    for ( $i = 1; $i <= 6; $i++ ) :
      $field_id = "_selling_price_{$i}";
      $value    = isset( $_POST[ $field_id ] ) ? wc_clean( wp_unslash( $_POST[ $field_id ] ) ) : '';
      if ( $value !== '' ) :
        $product->update_meta_data( $field_id, $value );
      else :
        $product->delete_meta_data( $field_id );
      endif;
    endfor;
  }


  public static function display_stock_fields(): void {
    echo '<div class="options_group">';

    foreach ( self::$product_meta_stock as $field_id => $label ) 
      woocommerce_wp_text_input( [
        'id'                => $field_id,
        'label'             => __( $label, 'df-fincon' ),
        'type'              => 'number',
        'desc_tip'          => true,
        'custom_attributes' => [ 'step' => '1', 'min' => '0' ],
      ] );

      echo '</div>';      

  }

  /**
   * Save the custom stock fields
   */
  public static function save_stock_fields( $product ) : void {
    foreach ( self::$product_meta_stock as $field_id => $label ) 
      if ( isset( $_POST[ $field_id ] ) ) :
        $value = wc_clean( wp_unslash( $_POST[ $field_id ] ) );
        $product->update_meta_data( $field_id, $value );
      endif;
  }

}
