<?php
/**
 * Product Synchronization Class
 *
 * Manages the synchronization of product data 
 * from the Fincon API into WooCommerce products.
 *
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * @subpackage Includes
 * Text Domain: df-fincon
 */

namespace DF_FINCON;

use WC_Product;

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
  public const OPTIONS_NAME = Plugin::OPTIONS_NAME . '_PRODUCT';

  /**
   * Batch import state option name
   */
  const BATCH_STATE_OPTION_NAME = self::OPTIONS_NAME . '_batch_state';

  /**
   * Manual import progress option name
   */
  const MANUAL_IMPORT_PROGRESS_OPTION_NAME = self::OPTIONS_NAME . '_manual_import_progress';

  /**
   * Get batch import state
   * 
   * @return array Array with: in_progress, last_rec_no, total_processed, batch_size, started_at, completed_at
   */
  public static function get_batch_state(): array {
    $default = [
      'in_progress' => false,
      'last_rec_no' => 0,
      'total_processed' => 0,
      'batch_size' => 100,
      'started_at' => null,
      'completed_at' => null,
    ];
    $state = get_option( self::BATCH_STATE_OPTION_NAME, $default );
    return wp_parse_args( $state, $default );
  }

  /**
   * Update batch import state
   * 
   * @param array $state State array to save
   */
  public static function update_batch_state( array $state ): void {
    update_option( self::BATCH_STATE_OPTION_NAME, $state, false );
  }

  /**
   * Start a new batch import
   * 
   * @param int $batch_size Batch size to use
   */
  public static function start_batch_import( int $batch_size = 100 ): void {
    self::update_batch_state( [
      'in_progress' => true,
      'last_rec_no' => 0,
      'total_processed' => 0,
      'batch_size' => $batch_size,
      'started_at' => current_time( 'mysql' ),
      'completed_at' => null,
    ] );
  }

  /**
   * Update batch import progress
   * 
   * @param int $last_rec_no Last RecNo from API response
   * @param int $count_returned Count of records returned in this batch
   */
  public static function update_batch_progress( int $last_rec_no, int $count_returned ): void {
    $state = self::get_batch_state();
    $state['last_rec_no'] = $last_rec_no;
    $state['total_processed'] += $count_returned;
    self::update_batch_state( $state );
  }

  /**
   * Complete batch import
   */
  public static function complete_batch_import(): void {
    $state = self::get_batch_state();
    $state['in_progress'] = false;
    $state['completed_at'] = current_time( 'mysql' );
    self::update_batch_state( $state );
  }

  /**
   * Reset batch import state
   */
  public static function reset_batch_state(): void {
    delete_option( self::BATCH_STATE_OPTION_NAME );
  }

  /**
   * Get manual import progress
   * 
   * @return array Array with: in_progress, last_rec_no, total_processed, batch_size, started_at
   */
  public static function get_manual_import_progress(): array {
    $default = [
      'in_progress' => false,
      'last_rec_no' => 0,
      'total_processed' => 0,
      'batch_size' => 100,
      'started_at' => null,
      'target_count' => 0,
    ];
    $progress = get_option( self::MANUAL_IMPORT_PROGRESS_OPTION_NAME, $default );
    return wp_parse_args( $progress, $default );
  }

  /**
   * Start a new manual import
   * 
   * @param int $batch_size Batch size to use
   * @param int $target_count Target number of products to import (0 = all)
   */
  public static function start_manual_import( int $batch_size = 100, int $target_count = 0 ): void {
    update_option( self::MANUAL_IMPORT_PROGRESS_OPTION_NAME, [
      'in_progress' => true,
      'last_rec_no' => 0,
      'total_processed' => 0,
      'batch_size' => $batch_size,
      'started_at' => current_time( 'mysql' ),
      'target_count' => $target_count,
    ], false );
  }

  /**
   * Update manual import progress
   * 
   * @param int $last_rec_no Last RecNo from API response
   * @param int $count_returned Count of records returned in this batch
   */
  public static function update_manual_import_progress( int $last_rec_no, int $count_returned ): void {
    $progress = self::get_manual_import_progress();
    $progress['last_rec_no'] = $last_rec_no;
    $progress['total_processed'] += $count_returned;
    update_option( self::MANUAL_IMPORT_PROGRESS_OPTION_NAME, $progress, false );
  }

  /**
   * Complete manual import
   */
  public static function complete_manual_import(): void {
    $progress = self::get_manual_import_progress();
    $progress['in_progress'] = false;
    update_option( self::MANUAL_IMPORT_PROGRESS_OPTION_NAME, $progress, false );
  }

  /**
   * Reset manual import progress
   */
  public static function reset_manual_import_progress(): void {
    delete_option( self::MANUAL_IMPORT_PROGRESS_OPTION_NAME );
  }
  
  public const FINCON_PRODUCT_CHANGED_META_KEY = '_fincon_changed_datetime';

  public const LAST_SYNC_DATETIME_META_KEY = '_fincon_last_sync_datetime'; 



  public const STOCK_THRESHOLD = 5;

  /**
   * Get stock meta mapping from LocationManager
   *
   * @return array Array in format ['code' => ['_stock_code' => 'Short Name']]
   * @since 1.0.0
   */
  public static function get_stock_meta_mapping(): array {
    $location_manager = LocationManager::create();
    return $location_manager->get_stock_meta_mapping();
  }

  /**
   * Backward compatibility method for PRODUCT_META_STOCK_LOCATIONS constant
   *
   * @deprecated 1.0.0 Use get_stock_meta_mapping() instead
   * @return array Same structure as old PRODUCT_META_STOCK_LOCATIONS constant
   */
  public static function PRODUCT_META_STOCK_LOCATIONS(): array {
    return self::get_stock_meta_mapping();
  }

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
  ];

  public const PRODUCT_META_SELLING_PRICES = [
    '_selling_price_2' => 'Selling Price 2',
    '_selling_price_3' => 'Selling Price 3',
    '_selling_price_4' => 'Selling Price 4',
    '_selling_price_5' => 'Selling Price 5',
    '_selling_price_6' => 'Selling Price 6',
  ];

  /**
   * Promotional price meta fields for price lists 2-6
   *
   * @const array
   * @since 1.0.0
   */
  public const PRODUCT_META_PROMO_PRICES = [
    '_promo_price_2' => 'Promotional Price 2',
    '_promo_price_3' => 'Promotional Price 3',
    '_promo_price_4' => 'Promotional Price 4',
    '_promo_price_5' => 'Promotional Price 5',
    '_promo_price_6' => 'Promotional Price 6',
  ];

  /**
   * Promotional flag meta fields for price lists 2-6
   *
   * @const array
   * @since 1.0.0
   */
  public const PRODUCT_META_PROMO_FLAGS = [
    '_promo_2' => 'Promotional Flag 2',
    '_promo_3' => 'Promotional Flag 3',
    '_promo_4' => 'Promotional Flag 4',
    '_promo_5' => 'Promotional Flag 5',
    '_promo_6' => 'Promotional Flag 6',
  ];

  /**
   * All price list numbers (1-6)
   *
   * @const array
   * @since 1.0.0
   */
  public const PRICE_LIST_NUMBERS = [1, 2, 3, 4, 5, 6];


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
   * 
   * 
   * @param mixed $stock_items
   * 
   * @return array{created_count: int, errors: array, imported_count: int, next_rec_no: mixed, raw_response_summary: array{Count: mixed, RecNo: mixed, skipped_count: int, total_fetched: int, updated_count: int}|array{created_count: int, errors: string[], imported_count: int, next_rec_no: mixed, raw_response_summary: array{Count: mixed, RecNo: mixed}, skipped_count: int, total_fetched: int, updated_count: int}}
   */
  public static function process_import_products ( $stock_items = []  ): array {
    $imported_count = 0;
    $created_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $errors = [];

    if ( ! empty( $stock_items ) )
      foreach ( $stock_items as $item ) {

        $item_no = $item['ItemNo'] ?? 'N/A';
        $item_desc = $item['Description'] ?? 'No Description';
        $item_active = $item['Active'] ?? 'N';

        if ( empty( $item_no ) || $item_no === 'N/A' ) {
          $skipped_count++;
          $errors[] = __('Skipped item - missing ItemNo');
          
          // Log skipped product if cron logging is enabled
          try {
            $cron_logger = ProductCronLogger::create();
            if ( $cron_logger->is_enabled() ) {
              $cron_logger->log_product( $item_no, 0, 'skipped', 'missing ItemNo' );
            }
          } catch ( \Exception $e ) {
            // Silently fail logging to not disrupt import
          }
          
          continue;
        }


        if ( strtoupper(trim($item_active)) !== 'Y' ) {
          $skipped_count++;
          $errors[] = __('Skipped item - item not Active on Fincon');
          
          // Log skipped product if cron logging is enabled
          try {
            $cron_logger = ProductCronLogger::create();
            if ( $cron_logger->is_enabled() ) {
              $cron_logger->log_product( $item_no, 0, 'skipped', 'missing ItemNo' );
            }
          } catch ( \Exception $e ) {
            // Silently fail logging to not disrupt import
          }
          
          continue;
        }

        try {
          $result = self::create_or_update_wc_product( $item);
          $status = $result['status'];
          $product_id = $result['product_id'] ?? 0;
          $sku = $result['sku'] ?? $item_no;
          
          if ( $status === self::PROD_STATUS_CREATE )
            $created_count++;
          elseif ( $status === self::PROD_STATUS_UPDATE )
            $updated_count++;
          elseif ( $status === self::PROD_STATUS_SKIP )
            $skipped_count++;
          
          $imported_count++;

          // Log product import result if cron logging is enabled
          try {
            $cron_logger = ProductCronLogger::create();
            if ( $cron_logger->is_enabled() ) {
              $status_label = match( $status ) {
                self::PROD_STATUS_CREATE => 'created',
                self::PROD_STATUS_UPDATE => 'updated',
                self::PROD_STATUS_SKIP => 'skipped',
                default => 'unknown',
              };
              $cron_logger->log_product( $sku, $product_id, $status_label );
            }
          } catch ( \Exception $e ) {
            // Silently fail logging to not disrupt import
          }

        } catch ( \Exception $e ) {
            $skipped_count++;
            $error_message = sprintf( 'Failed to import Item %s (%s): %s', $item_no, $item_desc, $e->getMessage() );
            $errors[] = $error_message;
            
            // Log failed product if cron logging is enabled
            try {
              $cron_logger = ProductCronLogger::create();
              if ( $cron_logger->is_enabled() ) {
                $cron_logger->log_product( $item_no, 0, 'failed', $e->getMessage() );
              }
            } catch ( \Exception $log_e ) {
              // Silently fail logging to not disrupt import
            }
        }
      }
    

      $summary = [
        'total_fetched' => count( $stock_items ),
        'imported_count' => $imported_count,
        'created_count' => $created_count,
        'updated_count' => $updated_count,
        'skipped_count' => $skipped_count,
        'errors' => $errors,
      ];

      if ( ! empty( $errors ) ) 
        Logger::error( 'Product import finished with errors.', $summary );
      else 
        Logger::info( 'Product import successful.', $summary );
    
      return $summary;
  }

   /**
    * Creates or updates a WooCommerce product based on Fincon data.
    * @param array $fincon_item Fincon StockRecord data.
    * @param bool $update_only_changed If true, only update products with a newer Fincon ChangeDate.
    * @return array Array with 'status' and 'product_id' keys
    */
   public static function create_or_update_wc_product( array $fincon_item ): array {
     $options = self::get_options();
     $update_only_changed = ! empty( $options['import_update_only_changed'] );
     $sku = $fincon_item['ItemNo'] ?? '';
     $fincon_change_date = $fincon_item['ChangeDate']; // Format CCYYMMDD
     $fincon_change_time = $fincon_item['ChangeTime']; // Format HH:MM:SS
     
     $fincon_timestamp_str = substr($fincon_change_date, 0, 4) . '-' . substr($fincon_change_date, 4, 2) . '-' . substr($fincon_change_date, 6, 2);
     $fincon_timestamp_str .= ' ' . substr($fincon_change_time, 0, 8);
     $fincon_timestamp = (int) strtotime( $fincon_timestamp_str );
 
     $product_id = wc_get_product_id_by_sku( $sku );
     
     $status = self::PROD_STATUS_CREATE;
             
     if ( $product_id ) :
         $product = wc_get_product( $product_id );
         $status = self::PROD_STATUS_UPDATE;
         
         if ( ! $product )
           throw new \Exception( "Could not load WC product for ID: {$product_id}" );
         
         // Check if update is needed based on timestamps
         if ( $update_only_changed ) :
           $last_sync_timestamp = (int) $product->get_meta( self::FINCON_PRODUCT_CHANGED_META_KEY, true );
           
           // If last_sync_timestamp is more recent than fincon_timestamp, skip the update
           if ( $last_sync_timestamp > $fincon_timestamp ) :
             LOGGER::debug('last_sync_timestamp: ' . $last_sync_timestamp);
             LOGGER::debug('fincon_timestamp: ' . $fincon_timestamp);
             LOGGER::debug( 'Skipping update - last_sync_timestamp is more recent than fincon_timestamp' );
             $status = self::PROD_STATUS_SKIP;
           endif;
             
         endif;
         
     else :
         $product = new \WC_Product_Simple();
         $product->set_sku( $sku );
         $product->set_status( 'draft' );
         //Only update product name from fincon the first time it is created
         $product->set_name( $fincon_item['Description'] );
     endif;
 
     // Stock levels should ALWAYS be updated, regardless of skip status
     // Pass false for save_product since we save at the end
     self::update_product_stock( $product, $fincon_item, false );
 
     // Only update product details if not skipping
     if ( $status !== self::PROD_STATUS_SKIP ) :
       self::update_product_price( $product, $fincon_item );
      
       //Don't update product title from Fincon if the product was already created previously
       if ( $status = self::PROD_STATUS_CREATE )
 
       $product->set_manage_stock( true );
       
       $product->set_weight( $fincon_item['Weight'] ?? '' );
       $product->set_length( $fincon_item['BoxLength'] ?? '' );
       $product->set_width( $fincon_item['BoxWidth'] ?? '' );
       $product->set_height( $fincon_item['BoxHeight'] ?? '' );
     
       $product->update_meta_data( self::FINCON_PRODUCT_CHANGED_META_KEY, $fincon_timestamp );
       foreach (self::PRODUCT_META_FINCON_DATA as $key => $label)
         $product->update_meta_data( $key, $fincon_item[$key] ?? '' );
 
        $profromdate = $product->get_meta('ProFromDate');
 
       if ( !empty($profromdate) )
         self::update_product_promo_price( $product );
 
     endif;
 
     // Always update last sync datetime to track when we last checked this product
     $current_timestamp = time();
     $product->update_meta_data( self::LAST_SYNC_DATETIME_META_KEY, $current_timestamp );
     $product->save_meta_data();
 
     try {
       $product->save();
       // Get the final product ID (for newly created products)
       $final_product_id = $product->get_id();
       if ( ! $product_id && $final_product_id ) {
         $product_id = $final_product_id;
       }
     } catch ( \WC_Data_Exception $e ) {
       error_log( 'WooCommerce data error: ' . $e->getMessage() );
       Logger::error( 'WooCommerce data error: ' . $e->getMessage() );
     } catch ( \Exception $e ) {
       error_log( 'General error: ' . $e->getMessage() );
       Logger::error( 'General error: ' . $e->getMessage() );
     }
     
     Logger::debug(sprintf('ItemNo: %s, Product ID: %s, Status: %s', $fincon_item['ItemNo'], $product_id, $status));
     
     return [
       'status' => $status,
       'product_id' => $product_id,
       'sku' => $sku,
     ];
   }

  /**
   * Get comma-separated string of location codes
   *
   * @return string Comma-separated location codes (e.g., "00,01,03")
   * @since 1.0.0
   */
  public static function locations_as_string(): string {
    $location_manager = LocationManager::create();
    return $location_manager->get_location_codes_string();
  }

  /**
   * Update product stock from Fincon data
   *
   * @param WC_Product $product WooCommerce product object
   * @param array $fincon_item Fincon StockRecord data
   * @param bool $save_product Whether to save the product after updating
   * @return void
   * @since 1.0.0
   */
  public static function update_product_stock( $product, $fincon_item = [], $save_product = true ): void {
    $total_stock = 0;
    $stock_mapping = self::get_stock_meta_mapping();
    
    if ( ! empty( $fincon_item['StockLoc'] ) && is_array( $fincon_item['StockLoc'] ) ) {
      foreach ( $fincon_item['StockLoc'] as $loc_data ) {
        $loc_no = $loc_data['LocNo'] ?? '';
        if ( array_key_exists( $loc_no, $stock_mapping ) ) {
          $in_stock = isset( $loc_data['InStock'] ) ? floatval( $loc_data['InStock'] ) : 0;
          $total_stock += (float) $in_stock;
          $product->update_meta_data( "LocNo_{$loc_no}", $in_stock );
          $product->update_meta_data( "_stock_{$loc_no}", $in_stock );
        }
      }
    }
    
    $product->set_stock_quantity( $total_stock );
    $product->set_stock_status( $total_stock > 0 ? 'instock' : 'outofstock' );

    if ( $save_product ) {
      try {
        $product->save();
      } catch ( \WC_Data_Exception $e ) {
        error_log( 'WooCommerce data error: ' . $e->getMessage() );
        Logger::error( 'WooCommerce data error: ' . $e->getMessage() );
      } catch ( \Exception $e ) {
        error_log( 'General error: ' . $e->getMessage() );
        Logger::error( 'General error: ' . $e->getMessage() );
      }
      $product->save_meta_data();
    }
  }

  /**
   * Update product regular prices from Fincon data
   *
   * @param WC_Product $product WooCommerce product object
   * @param array $fincon_item Fincon StockRecord data
   * @return void
   * @since 1.0.0
   */
  public static function update_product_price( $product, $fincon_item = [] ) {
    $price = $fincon_item['SellingPrice1'] ?? 0;
    $formatted_price = wc_format_decimal( $price, wc_get_price_decimals(), true );
    $product->set_regular_price( $formatted_price );
    
    Logger::debug( sprintf( 'Set regular price (SellingPrice1) to %s', $formatted_price ) );

    $i = 1;
    foreach ( self::PRODUCT_META_SELLING_PRICES as $field_id => $label ) :

      $i++;
      $price = $fincon_item["SellingPrice$i"] ?? 0;

      if ( $price ) :
        $price = $fincon_item["SellingPrice$i"] ?? 0;
        $formatted_price = wc_format_decimal( $price, wc_get_price_decimals(), true );
        $product->update_meta_data( $field_id, wc_format_decimal( $formatted_price, wc_get_price_decimals(), true ) );
        Logger::debug( sprintf( 'Saved SellingPrice%d to meta field %s: %s', $i, $field_id, $formatted_price ) );
      endif;
    endforeach;
  }

  /**
   * Check if a promotional period is currently active
   *
   * @param string $from_date Promotional from date in CCYYMMDD format
   * @param string $to_date Promotional to date in CCYYMMDD format (empty for ongoing)
   * @return bool True if promotion is active, false otherwise
   * @since 1.0.0
   */
  public static function is_promotion_active( string $from_date, string $to_date ): bool {
    if ( empty( $from_date ) )
      return false;

    $current_time = time();
    
    // Parse from date
    $from_timestamp = strtotime( substr( $from_date, 0, 4 ) . '-' . substr( $from_date, 4, 2 ) . '-' . substr( $from_date, 6, 2 ) );
    
    // Check if promotion hasn't started yet
    if ( $from_timestamp > $current_time )
      return false;
    
    // If no end date, promotion is ongoing once started
    if ( empty( $to_date ) )
      return true;
    
    // Parse to date (set to end of day)
    $to_timestamp = strtotime( substr( $to_date, 0, 4 ) . '-' . substr( $to_date, 4, 2 ) . '-' . substr( $to_date, 6, 2 ) . ' 23:59:59' );
    
    return $current_time <= $to_timestamp;
  }

  /**
   * Extract price list numbers from ProPriceType string
   *
   * @param string $promo_type ProPriceType string (e.g., "1,2,3" or "123" or "1-3")
   * @return array Array of price list numbers (1-6)
   * @since 1.0.0
   */
  public static function extract_price_list_numbers( string $promo_type ): array {
    $price_lists = [];
    
    if ( empty( $promo_type ) ) {
      // Empty ProPriceType means apply to all price lists
      return self::PRICE_LIST_NUMBERS;
    }
    
    // Remove any whitespace
    $promo_type = trim( $promo_type );
    
    // Check for comma-separated values
    if ( str_contains( $promo_type, ',' ) ) {
      $parts = explode( ',', $promo_type );
      foreach ( $parts as $part ) {
        $part = trim( $part );
        if ( is_numeric( $part ) && $part >= 1 && $part <= 6 ) {
          $price_lists[] = (int) $part;
        }
      }
    } else {
      // Check for range notation (e.g., "1-3")
      if ( str_contains( $promo_type, '-' ) ) {
        $range_parts = explode( '-', $promo_type );
        if ( count( $range_parts ) === 2 && is_numeric( $range_parts[0] ) && is_numeric( $range_parts[1] ) ) {
          $start = (int) $range_parts[0];
          $end = (int) $range_parts[1];
          for ( $i = $start; $i <= $end; $i++ ) {
            if ( $i >= 1 && $i <= 6 ) {
              $price_lists[] = $i;
            }
          }
        }
      } else {
        // Treat as individual numbers (e.g., "123" or "2")
        for ( $i = 0; $i < strlen( $promo_type ); $i++ ) {
          $char = $promo_type[$i];
          if ( is_numeric( $char ) && $char >= 1 && $char <= 6 ) {
            $price_lists[] = (int) $char;
          }
        }
      }
    }
    
    // Remove duplicates and sort
    $price_lists = array_unique( $price_lists );
    sort( $price_lists );
    
    return $price_lists;
  }

  /**
   * Apply promotional price to a specific price list
   *
   * @param WC_Product $product WooCommerce product object
   * @param int $price_list_number Price list number (1-6)
   * @param float $promo_price Promotional price
   * @param string $from_date Promotional from date in CCYYMMDD format
   * @param string $to_date Promotional to date in CCYYMMDD format (empty for ongoing)
   * @return void
   * @since 1.0.0
   */
  public static function apply_promotional_price_to_price_list( $product, int $price_list_number, float $promo_price, string $from_date, string $to_date ): void {
    $formatted_price = $promo_price == 0 ? '' : wc_format_decimal( $promo_price, wc_get_price_decimals(), true );
    
    if ( $price_list_number === 1 ) {
      // Price list 1 is the regular WooCommerce sale price
      $product->set_sale_price( $formatted_price );
      
      $from_timestamp = strtotime( substr( $from_date, 0, 4 ) . '-' . substr( $from_date, 4, 2 ) . '-' . substr( $from_date, 6, 2 ) );
      $product->set_date_on_sale_from( date( 'Y-m-d H:i:s', $from_timestamp ) );
      
      if ( ! empty( $to_date ) ) {
        $to_timestamp = strtotime( substr( $to_date, 0, 4 ) . '-' . substr( $to_date, 4, 2 ) . '-' . substr( $to_date, 6, 2 ) );
        $product->set_date_on_sale_to( date( 'Y-m-d H:i:s', $to_timestamp ) );
      }
      
      Logger::debug( sprintf( 'Applied promotional price %s to price list 1 (regular price)', $formatted_price ) );
    } else {
      // Price lists 2-6 store in meta fields
      $meta_key = "_promo_price_{$price_list_number}";
      $product->update_meta_data( $meta_key, $formatted_price );
      
      // Also update the promotional flag
      $flag_key = "_promo_{$price_list_number}";
      $product->update_meta_data( $flag_key, 'yes' );
      
      Logger::debug( sprintf( 'Applied promotional price %s to price list %d (meta: %s)', $formatted_price, $price_list_number, $meta_key ) );
    }
  }

  /**
   * Update product promotional prices from Fincon data
   *
   * @param WC_Product $product WooCommerce product object
   * @return void
   * @since 1.0.0
   */
  public static function update_product_promo_price ( $product ): void {
    $promo_from_date = $product->get_meta( 'ProFromDate' );
    $promo_to_date = $product->get_meta( 'ProToDate' );
    $raw_price = (float) $product->get_meta( 'ProPrice' );
    $promo_type = $product->get_meta( 'ProPriceType' );
    
    // Check if promotion is active
    if ( ! self::is_promotion_active( $promo_from_date, $promo_to_date ) ) {
      Logger::debug( 'Promotion is not active, clearing all promotional prices' );
      self::clear_all_promotional_prices( $product );
      return;
    }
    
    // Extract which price lists this promotion applies to
    $price_lists = self::extract_price_list_numbers( $promo_type );
    
    if ( empty( $price_lists ) ) {
      Logger::debug( 'No valid price lists found in ProPriceType, skipping promotional price update' );
      return;
    }
    
    Logger::info( sprintf( 'Applying promotional price %s to price lists: %s', $raw_price, implode( ', ', $price_lists ) ) );
    
    // Apply promotional price to each price list
    foreach ( $price_lists as $price_list ) {
      self::apply_promotional_price_to_price_list( $product, $price_list, $raw_price, $promo_from_date, $promo_to_date );
    }
    
    // Clear promotional flags for price lists not in the promotion
    self::update_promotional_flags( $product, $price_lists );
  }

  /**
   * Clear all promotional prices from product
   *
   * @param WC_Product $product WooCommerce product object
   * @return void
   * @since 1.0.0
   */
  private static function clear_all_promotional_prices( $product ): void {
    // Clear price list 1 (regular sale price)
    $product->set_sale_price( '' );
    $product->set_date_on_sale_from( '' );
    $product->set_date_on_sale_to( '' );
    
    // Clear price lists 2-6 promotional prices
    foreach ( self::PRODUCT_META_PROMO_PRICES as $meta_key => $label ) {
      $product->update_meta_data( $meta_key, '' );
    }
    
    // Clear all promotional flags
    foreach ( self::PRODUCT_META_PROMO_FLAGS as $meta_key => $label ) {
      $product->update_meta_data( $meta_key, 'no' );
    }
    
    Logger::debug( 'Cleared all promotional prices and flags' );
  }

  /**
   * Update promotional flags based on active price lists
   *
   * @param WC_Product $product WooCommerce product object
   * @param array $active_price_lists Array of price list numbers with active promotions
   * @return void
   * @since 1.0.0
   */
  private static function update_promotional_flags( $product, array $active_price_lists ): void {
    // Update flags for price lists 2-6
    for ( $i = 2; $i <= 6; $i++ ) {
      $flag_key = "_promo_{$i}";
      $is_active = in_array( $i, $active_price_lists ) ? 'yes' : 'no';
      $product->update_meta_data( $flag_key, $is_active );
    }
    
    Logger::debug( sprintf( 'Updated promotional flags for price lists: %s', implode( ', ', $active_price_lists ) ) );
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

 /**
   * Get product sync options
   * 
   * @return mixed
   */
  public static function get_options( $option_key = '' ): mixed {
    $options = get_option( self::OPTIONS_NAME, [] );
    if ( ! empty( $option_key ) )
      if ( array_key_exists( $option_key, $options ) )
        return $options[$option_key];
      else
        return null;
    return wp_parse_args( $options, [] );
  }

  }
