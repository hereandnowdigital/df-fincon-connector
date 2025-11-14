<?php
/**
 * Product Synchronization Class
 *
 * Manages the synchronization of product data (primarily stock and price)
 * from the Fincon API into WooCommerce products.
 *
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * @subpackage Includes
 * Text Domain: df-fincon
 */

namespace DF_FINCON;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class ProductSync {

  private static ?self $instance = null;

  /**
   * Options name
   * 
   * @const string
   */
  public const OPTIONS_NAME = Plugin::OPTIONS_NAME . '_API';

  
  public const FINCON_PRODUCT_CHANGED_META_KEY = '_fincon_changed_datetime';

  public const LAST_SYNC_DATETIME_META_KEY = '_fincon_last_sync_datetime'; 

  public const PRODUCT_META_FINCON_DATA = [
    'ItemNo' => 'ItemNo',
    'SellingPrice1' => 'Selling Price 1',
    'SellingPrice2' => 'Selling Price 2',
    'SellingPrice3' => 'Selling Price 3',
    'SellingPrice4' => 'Selling Price 4',
    'SellingPrice5' => 'Selling Price 5',
    'SellingPrice6' => 'Selling Price 6',
    'MinOrderQuantity' => 'Minimum Order Quantity',
    'ProPrice' => 'Promotional Price',
    'ProPriceType' => 'Promotional Price Type',
    'ProFromDate' => 'Promotional From Date',
    'ProToDate' => 'Promotional To Date',
    'ProQuantity' => 'Promotional Quantity',
    'ProMaxQuantity'  => 'Promotional Max Quantity',
    'Barcode' => 'Barcode',
    'Comment' => 'Comment',
    'Category'  => 'Category Code',
    'CatDescription'  => 'Category Description',
    'Brand'  => 'Brand Code',
    'BrandDescription'  => 'Brand Description',
    'Group' => 'Group',
    'ItemClass' => 'Item Class',
    'ItemClassDescription' => 'Item Class Description',
    'LocNo_00' => 'Stock Johannesburg',
    'LocNo_01' => 'Stock Cape Town',
    'LocNo_03' => 'Stock Durban',
  ];

  public const PRODUCT_META_SELLING_PRICES = [
    '_selling_price_1' => 'Selling Price 1',
    '_selling_price_2' => 'Selling Price 2',
    '_selling_price_3' => 'Selling Price 3',
    '_selling_price_4' => 'Selling Price 4',
    '_selling_price_5' => 'Selling Price 5',
    '_selling_price_6' => 'Selling Price 6',
  ];

  public const PRODUCT_META_STOCK = [
    '_stock_00' => 'Stock Johannesburg',
    '_stock_01' => 'Stock Cape Town',
    '_stock_03' => 'Stock Durban',
  ];

  public const LOCS = [ '00', '01', '03' ];

  public const PROD_STATUS_CREATE = "_CREATE_";
  public const PROD_STATUS_UPDATE = "_UPDATE_";
  public const PROD_STATUS_SKIP = "_SKIP_";

  public function __construct(  ) {
    
  }

  /**
   * Create an instance.
   *
   * @return self
   */
 public static function create(  ): self {
    if ( self::$instance === null ) {
      self::$instance = new self( );
    }
    return self::$instance;
  }

  
   /**
   * Creates or updates a WooCommerce product based on Fincon data.
   * @param array $fincon_item Fincon StockRecord data.
   * @param bool $update_only_changed If true, only update products with a newer Fincon ChangeDate.
   * @return string $tatus Status of product import
   */
  public static function create_or_update_wc_product( array $fincon_item, bool $update_only_changed ): string {
    $sku = $fincon_item['ItemNo'] ?? '';
    $fincon_change_date = $fincon_item['ChangeDate']; // Format CCYYMMDD
    $fincon_change_time = $fincon_item['ChangeTime']; // Format HH:MM:SS
    
    $fincon_timestamp_str = substr($fincon_change_date, 0, 4) . '-' . substr($fincon_change_date, 4, 2) . '-' . substr($fincon_change_date, 6, 2);
    $fincon_timestamp_str .= ' ' . substr($fincon_change_time, 0, 8);
    $fincon_timestamp = (int) strtotime( $fincon_timestamp_str );

    $product_id = wc_get_product_id_by_sku( $sku );
    
    $status = self::PROD_STATUS_CREATE;
            
    LOGGER::debug('$sku: ' . $sku);
    LOGGER::debug('$product_id: ' . $product_id);

    
    if ( $product_id ) :
        $product = wc_get_product( $product_id );
        $status = self::PROD_STATUS_UPDATE;
        
        if ( ! $product ) 
          throw new \Exception( "Could not load WC product for ID: {$product_id}" );
        
        // Check if update is needed based on timestamps
        // #EM-TODO: Test if this is working as expected
        if ( $update_only_changed ) :
          $last_sync_timestamp = (int) $product->get_meta( self::FINCON_PRODUCT_CHANGED_META_KEY, true );
          
          if ( $last_sync_timestamp >= $fincon_timestamp ) :
            LOGGER::debug('last_sync_timestamp: ' . $last_sync_timestamp);
            LOGGER::debug('fincon_timestamp: ' . $fincon_timestamp);
            LOGGER::debug( '$last_sync_timestamp >= $fincon_timestamp:', $last_sync_timestamp >= $fincon_timestamp ? 'true' : 'false');
            //$status = self::PROD_STATUS_SKIP;
          endif;
            
        endif;
        
    else :
        $product = new \WC_Product_Simple();
        $product->set_sku( $sku );
        $product->set_status( 'draft' );

    endif;

    self::update_product_stock( $product, $fincon_item );
    self::update_product_price( $product, $fincon_item );

    if ( $status !== self::PROD_STATUS_SKIP ) :
      $product->set_name( $fincon_item['Description'] );

      $product->set_manage_stock( true );
      
      $product->set_weight( $fincon_item['Weight'] ?? '' );
      $product->set_length( $fincon_item['BoxLength'] ?? '' );
      $product->set_width( $fincon_item['BoxWidth'] ?? '' );
      $product->set_height( $fincon_item['BoxHeight'] ?? '' );

      $current_timestamp = time(); 
    
      $product->update_meta_data( self::FINCON_PRODUCT_CHANGED_META_KEY, $fincon_timestamp );
      foreach (self::PRODUCT_META_FINCON_DATA as $key => $label) 
          $product->update_meta_data( $key, $fincon_item[$key] ?? '' );
    endif;



    $product->update_meta_data( self::LAST_SYNC_DATETIME_META_KEY, $current_timestamp );
    $product->save_meta_data();

    try {
      $product->save();
    } catch ( \WC_Data_Exception $e ) {
      error_log( 'WooCommerce data error: ' . $e->getMessage() );
      Logger::error( 'WooCommerce data error: ' . $e->getMessage() );
    } catch ( \Exception $e ) {
      error_log( 'General error: ' . $e->getMessage() );
      Logger::error( 'General error: ' . $e->getMessage() );
    }

    return $status;
  }


  public static function update_product_price( $product, $fincon_item = [] ) {
    
    $price = $fincon_item['SellingPrice1'] ?? 0;
    $formatted_price = wc_format_decimal( $price, wc_get_price_decimals(), true ); 
    $product->set_regular_price( $formatted_price );
    $i = 0;
    foreach ( self::PRODUCT_META_SELLING_PRICES as $field_id => $label ) :

      $i++;
      $price = $fincon_item["SellingPrice$i"] ?? 0;

      if ( $price ) :
        $price = $fincon_item["SellingPrice$i"] ?? 0;
        $formatted_price = wc_format_decimal( $price, wc_get_price_decimals(), true ); 
        $product->update_meta_data( $field_id, wc_format_decimal( $formatted_price, wc_get_price_decimals(), true ) );
      endif;
    endforeach;
  }  

  /*
  * 
  */
  public static function update_product_stock( $product, $fincon_item = [] ) {
    $total_stock = 0;
    if ( ! empty( $fincon_item['StockLoc'] ) && is_array( $fincon_item['StockLoc'] ) ) :
      foreach ( $fincon_item['StockLoc'] as $loc_data ) :
        $loc_no = $loc_data['LocNo'] ?? '';
        if ( in_array($loc_no, self::LOCS, true ) ) :
          $in_stock = isset( $loc_data['InStock'] ) ? floatval( $loc_data['InStock'] ) : 0;
          $total_stock += (float) $in_stock;
          $product->update_meta_data( "LocNo_{$loc_no}", $in_stock );
          $product->update_meta_data( "_stock_{$loc_no}", $in_stock );
        endif;        
      endforeach;
    endif;

    $product->set_stock_quantity( $total_stock );
    $product->set_stock_status( $total_stock > 0 ? 'instock' : 'outofstock' );
    
  }

  /**
   * Finds or creates a taxonomy term and returns its ID.
   *
   * @param int $product_id The product ID (required by wp_set_post_terms for context, though optional here).
   * @param string $term_name The name of the term to find or create.
   * @param string $taxonomy The taxonomy slug (e.g., 'product_cat', 'pwb-brand').
   * @return array|\WP_Error Array of term IDs on success, or WP_Error on failure.
   */
  private static function get_product_term_id( string $term_name, string $taxonomy, array $taxonomy_meta = [] ): int|\WP_Error {
    $term_id = null;
    $term_name = ucwords( strtolower( trim( $term_name ) ) );
    
    if ( empty( $term_name ) ) :
      Logger::debug(sprintf( 'Cannot create or set term for empty name in taxonomy %s.', $taxonomy ));
      return new \WP_Error( 'empty_term_name', sprintf( 'Cannot create or set term for empty name in taxonomy %s.', $taxonomy ) );
    endif;

    $term = get_term_by( 'name', $term_name, $taxonomy );

    if ( $term ) :
      $term_id =  $term->term_id ;
    else :
      $result = wp_insert_term( 
          $term_name, 
          $taxonomy, 
          array(
              'slug' => sanitize_title( $term_name )
          )
      );
      if ( is_wp_error( $result ) ) :
        $error = $result;
        Logger::error( sprintf( 'Failed to create term "%s" in taxonomy "%s".', $term_name, $taxonomy ), $result );
        if ( isset( $result->error_data['term_exists'] ) ) 
          $term_id = (int) $result->error_data['term_exists'];
        else
          return $error;
      endif;
    endif;

    if ( empty( $term_id ) )
      return new \WP_Error( 'null_term_id', sprintf( 'Term ID is null for term in taxonomy %s.', $term, $taxonomy ) );

    if ( ! empty( $taxonomy_meta ) ) 
      foreach ( $taxonomy_meta as $key => $value ): 
        if ( $term_id > 0  && ! empty($value)  )
          update_term_meta( $term_id, $key, $value );
      endforeach;

    return $term_id;

  }

}
