<?php
/**
 * Fincon Service Class
 * 
 * Handles persistent plugin options, including reading and writing Fincon API credentials,
 * and managing the critical Fincon ConnectID session state.
 *
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * Text Domain: df-fincon
 * 
 */

namespace DF_FINCON;
use \WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class FinconService {

  /**
   * 
   * @var FinconApi
   */
  private static ?FinconApi $api = null;


  /**
   * Array of results, to collate batch results
   * @var array
   */
  private static array $results = [];


  public function __construct() {
    self::$api = new FinconApi(['log_enabled' => false] );
  }

  private static function bootstrap_api(): void {
    if ( self::$api === null ):
      self::$api = new FinconApi( [ 'log_enabled' => false ] );
      $options = ProductSync::get_options();
      self::$api->batch_size = isset( $options['import_batch_size'] ) ? (int) $options['import_batch_size'] : 100;
    endif;
  }

  /**
   * Summary of import_product
   * @param string $product_item_code
   * @return array{created_count: int, errors: array, imported_count: int, next_rec_no: mixed, raw_response_summary: array{Count: mixed, RecNo: mixed, skipped_count: int, total_fetched: int, updated_count: int|array{created_count: int, errors: array, imported_count: int, next_rec_no: mixed, raw_response_summary: array, skipped_count: int, total_fetched: int, updated_count: int}}|\WP_Error}
   */
  public static function import_product( string $product_item_code ): array|\WP_Error  {
    self::bootstrap_api();
    self::$api->batch_size = 1;
    $api_response = self::$api->get_stock_items( $product_item_code, $product_item_code );
    if ( is_wp_error( $api_response ) ) {
      Logger::error( sprintf( 'Product import failed during API fetch: %s', $api_response->get_error_message() ) );
      return $api_response;
    }
    $stock_items = $api_response['Stock'] ?? [];
    return ProductSync::process_import_products( $stock_items );
  }

  private static function get_products_batch ( $request_next_record = '0' ): array|\WP_Error {
    $response = self::$api->get_stock_items( '', '', $request_next_record );
    return $response;
  }

  /**
   * 
   * @param int $count
   * @param int $offset
   * @param mixed $batch
   * @param bool $update_only_changed
   * @return array{created_count: int, errors: array, imported_count: int, next_rec_no: mixed, raw_response_summary: array{Count: mixed, RecNo: mixed, skipped_count: int, total_fetched: int, updated_count: int|array{created_count: int, errors: array, imported_count: int, next_rec_no: mixed, raw_response_summary: array, skipped_count: int, total_fetched: int, updated_count: int}}|\WP_Error}
   */
  /**
   * Import products - processes one batch at a time for manual imports
   * Saves progress after each batch to allow resuming
   * 
   * @param int $count Total number of products to import (0 = all)
   * @param bool $resume Whether to resume from last progress
   * @return array|\WP_Error Result with batch information
   */
  public static function import_products( $count = 0, bool $resume = false ): array|\WP_Error  {
    self::bootstrap_api();
    
    // Get or start manual import progress
    $progress = ProductSync::get_manual_import_progress();
    
    if ( ! $resume || empty( $progress['in_progress'] ) ) :
      // Start new import
      ProductSync::start_manual_import( self::$api->batch_size, $count );
      $progress = ProductSync::get_manual_import_progress();
      $request_next_record = 0;
    else :
      // Resume from last progress
      $request_next_record = $progress['last_rec_no'] ?? 0;
      Logger::info( sprintf( 'Resuming manual import from RecNo %d. Already processed: %d', $request_next_record, $progress['total_processed'] ) );
    endif;

    // Process one batch
    $response = self::get_products_batch( request_next_record: $request_next_record );

    if ( is_wp_error( $response ) ) :
      Logger::error( sprintf( 'Product import failed during API fetch: %s', $response->get_error_message() ) );
      return $response;
    endif;
    
    $stock_items = $response['Stock'] ?? [];
    $batch_result = ProductSync::process_import_products( $stock_items );
    
    // Get API response metadata
    $api_count = isset( $response['Count'] ) ? (int) $response['Count'] : count( $stock_items );
    $api_rec_no = isset( $response['RecNo'] ) ? (int) $response['RecNo'] : 0;
    
    // Update progress
    ProductSync::update_manual_import_progress( $api_rec_no, $api_count );
    
    // Check if we should continue
    $has_more = false;
    if ( self::$api->has_more() && ( self::$api->response_next_record !== $request_next_record ) ) 
      $has_more = true;
    
    // Check if target count reached
    $progress = ProductSync::get_manual_import_progress();
    if ( $count > 0 && $progress['total_processed'] >= $count ) 
      $has_more = false;
    
    // If no more batches, complete the import
    if ( ! $has_more ) :
      ProductSync::complete_manual_import();
      $batch_result['import_complete'] = true;
      Logger::info( sprintf( 'Manual import completed. Total processed: %d records', $progress['total_processed'] ) );
    else :
      $batch_result['import_complete'] = false;
      $batch_result['next_rec_no'] = $api_rec_no;
      Logger::info( sprintf( 'Batch processed. Continue from RecNo %d. Total processed so far: %d', $api_rec_no, $progress['total_processed'] ) );
    endif;
    
    // Add progress info to result
    $batch_result['progress'] = ProductSync::get_manual_import_progress();
    $batch_result['api_count'] = $api_count;
    $batch_result['api_rec_no'] = $api_rec_no;
    
    return $batch_result;
  }


  /**
   * Import a specific customer by AccNo
   */
  public static function import_customer_by_accno( string $accno ): array|\WP_Error {
    if ( empty( $accno ) )
      return new \WP_Error( 'missing_accno', __( 'AccNo is required for manual customer import.', 'df-fincon' ) );

    self::bootstrap_api();
    $response = self::$api->GetDebAccountsByAccNo( $accno, 0, 1 );

    if ( is_wp_error( $response ) )
      return $response;

    $customers = $response['Accounts'] ?? [];
    if ( empty( $customers ) )
      return new \WP_Error( 'customer_not_found', __( 'Customer not found for the provided AccNo.', 'df-fincon' ) );

    $summary = self::process_customers( $customers, false, false );
    $summary['api_count'] = 1;
    $summary['api_rec_no'] = $response['RecNo'] ?? 0;
    $summary['batch_complete'] = true;

    return $summary;
  }

  /**
   * Import customers based on FinCon API response.
   *
   * @param int $count
   * @param int $offset
   * @param bool $update_only_changed
   * @param bool $web_list_only
   * @param bool $create_if_missing Whether to create new customers if not found.
   */
  public static function import_customers( int $count, int $offset, bool $update_only_changed = false, bool $weblist_only = false, bool $create_if_missing = true ): array|\WP_Error {
    self::bootstrap_api();
    $response = self::$api->GetDebAccounts( '', '', $offset, $count );
    Logger::debug('response:', $response);
    if ( is_wp_error( $response ) )
      return $response;

    $customers = $response['Accounts'] ?? $response['result'] ?? [];
    if ( empty( $customers ) && isset( $response['Accounts'] ) )
      $customers = $response['Accounts'];

    $summary = self::process_customers( (array) $customers, $update_only_changed, $create_if_missing );
    $summary['api_count'] = isset( $response['Count'] ) ? (int) $response['Count'] : count( $customers );
    $summary['api_rec_no'] = isset( $response['RecNo'] ) ? (int) $response['RecNo'] : ( $offset + $summary['api_count'] );
    $summary['requested_count'] = $count;

    return $summary;
  }

  /**
   * Process customer payloads.
   *
   * @param array $customers Array of customer data from Fincon API.
   * @param bool $update_only_changed Whether to skip unchanged customers.
   * @param bool $create_if_missing Whether to create new customers if not found.
   * @return array Import summary.
   */
  public static function process_customers( array $customers, bool $update_only_changed = false, bool $create_if_missing = true ): array {
    $imported = 0;
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    Logger::debug('api_customers:', $customers);
    foreach ( $customers as $customer ) {
      try {
        $result = CustomerSync::create_or_update_customer( $customer, $update_only_changed, $create_if_missing );
        $imported++;
        if ( $result['status'] === CustomerSync::STATUS_CREATE )
          $created++;
        elseif ( $result['status'] === CustomerSync::STATUS_UPDATE )
          $updated++;
        else
          $skipped++;
      } catch ( \Throwable $exception ) {
        $skipped++;
        $errors[] = sprintf(
          'Failed to import customer %s: %s',
          $customer['AccNo'] ?? 'N/A',
          $exception->getMessage()
        );
      }
    }

    if ( $errors )
      Logger::error( 'Customer import finished with errors.', $errors );

    return [
      'total_fetched'  => count( $customers ),
      'imported_count' => $imported,
      'created_count'  => $created,
      'updated_count'  => $updated,
      'skipped_count'  => $skipped,
      'errors'         => $errors,
    ];
  }

  

}