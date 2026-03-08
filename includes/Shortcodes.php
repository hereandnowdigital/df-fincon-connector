<?php
  /**
   * WordPress Admin dashboard Functionality
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

class Shortcodes {

  /**
   * instance
   */
  private static ?self $instance = null;
 
  /**
   * STOCK_ICONS
   * @var array
   */
  const STOCK_ICONS = [
    'in-stock' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.3332 4L5.99984 11.3333L2.6665 8" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'low-stock' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2006_48395)"><path d="M8.00016 14.6668C11.6821 14.6668 14.6668 11.6821 14.6668 8.00016C14.6668 4.31826 11.6821 1.3335 8.00016 1.3335C4.31826 1.3335 1.3335 4.31826 1.3335 8.00016C1.3335 11.6821 4.31826 14.6668 8.00016 14.6668Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 10.6667V8" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 5.3335H8.00667" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_2006_48395"><rect width="16" height="16" fill="white"/></clipPath></defs></svg>',
    'no-stock' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 4L12 12" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  ];

  /**
   * STOCK_TOOLTIPS
   * @var array
   */
  const STOCK_TOOLTIPS = [
    'in-stock' => 'In stock and available to order.',
    'low-stock' => 'Low stock, please confirm availability with the branch.',
    'no-stock' => 'Currently sold out/unavailable.',
  ];


  public function __construct() {
    $this->register_shortcodes();
  }

  /**
   * Create an instance of the class
   * @return Shortcodes|null
   */
  public static function create(  ): self {
    if ( self::$instance === null ) {
      self::$instance = new self( );
    }
    return self::$instance;
  }

  /**
   * Register plugin shortcodes
   * @return void
   */
  private function register_shortcodes() {
    add_shortcode('fincon_stock_status', [__CLASS__, 'fincon_stock_status']);
    add_shortcode('fincon_cart_location_selector', [__CLASS__, 'fincon_cart_location_selector']);
    add_shortcode('fincon_checkout_location_selector', [__CLASS__, 'fincon_checkout_location_selector']);
  }

  /**
   * 
   * @param array $atts
   * @return string
   */
  public static function fincon_stock_status( array $atts = [] ) {
    $output = '';
    $atts = shortcode_atts(['id' => 0], $atts, 'fincon_stock_display');
    $product_id = intval($atts['id']);
    
    if ( ! $product_id ) :
      if ( function_exists( 'is_product' ) && is_product() ) :
        global $post;
        $product_id = isset( $post->ID ) ? $post->ID : 0;
      else :
        $product_id = get_the_ID();
      endif;
    endif;
    $product_id = get_the_ID();

    $product = wc_get_product( $product_id );
    if ( ! $product ) 
        return $output;
    if ( $product->get_stock_status() === 'outofstock' ) 
        return '';

    $locations = ProductSync::get_stock_meta_mapping();

    uasort($locations, function($a, $b) {
        $label_a = current($a); // Gets 'Johannesburg'
        $label_b = current($b); // Gets 'Cape Town'
        
        return strcasecmp($label_a, $label_b);
    });

    foreach ($locations as $location => $field) :
      reset($field);
      $field_id = key($field);
      $label = current($field);
      $stock_count = (int) get_post_meta($product_id, $field_id, true);
      $stock_status = '';

      switch (true) :
        case ($stock_count > ProductSync::STOCK_THRESHOLD):
          $stock_status = 'in-stock';
          break;
        case ($stock_count > 0): 
          $stock_status = 'low-stock';
          break;
        default:
          $stock_status = 'no-stock';
          break;
      endswitch;
      if ($stock_status) :
        $location_slug = sanitize_title_with_dashes( $label );
        $classes = [
          'fincon-stock-item',
          $stock_status,
          'fincon-stock-item__' . esc_attr($location_slug),
        ];
        $tooltip = esc_attr__( self::STOCK_TOOLTIPS[$stock_status], 'df-fincon' );
        $output .= sprintf(
          '<div class="%1$s" data-stockcount="%2$s" title="%3$s" style="cursor:pointer;"><i>%4$s</i><span>%5$s</span></div>' ,
          implode(' ', $classes),
          $stock_count,
          $tooltip,
          self::STOCK_ICONS[$stock_status],
          $label,
        );
      else :
        $output = 'no-stock';
      endif;
    endforeach;

    if  ( $output )
      $output = '<div class="fincon-stock-status">'
            . '<span class="fincon-stock-status_title">' . __( 'Availability', 'df-fincon' ) . '</span>'
            . '<div class="fincon-stock-wrapper">' 
            . $output 
            . '</div></div>';
  
    return $output;
  
  }

  /**
   * Shortcode to display location selector at the top of the cart
   * @return bool|string
   */
  public static function fincon_cart_location_selector (): bool|string {
    ob_start();
    WOO::add_location_selector_to_cart();
    $output = ob_get_clean();
    return $output;
  }

  /**
   * Shortcode to display location selector on checkout page
   *
   * Usage: [fincon_checkout_location_selector]
   * Renders the location selector for block/Elementor-based checkout pages
   * where traditional WooCommerce checkout hooks don't fire.
   *
   * @return bool|string HTML output or false on failure
   * @since 1.0.0
   */
  public static function fincon_checkout_location_selector (): bool|string {
    ob_start();
    WOO::add_location_selector_to_checkout();
    $output = ob_get_clean();
    return $output;
  }


}