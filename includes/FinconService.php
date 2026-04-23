<?php

namespace DF_FINCON;

use DF_FINCON\FinconApi;
use DF_FINCON\ProductSync;
use DF_FINCON\CustomerSync;
use DF_FINCON\Logger;

/**
 * Service layer for Fincon operations.
 * Provides high-level methods for importing products and customers.
 */
class FinconService {

  private static ?FinconApi $api = null;

  /**
   * Bootstrap the API instance with current settings.
   */
  private static function bootstrap_api(): void {
    if ( ! isset( self::$api ) ) :
      self::$api = new FinconApi();
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
    
    // Calculate how many items to actually process based on remaining count
    $items_to_process = $stock_items;
    $remaining_count = 0;
    
    if ( $count > 0 ) :
      $remaining_count = $count - $progress['total_processed'];
      if ( $remaining_count > 0 && count( $stock_items ) > $remaining_count ) :
        // Limit to only the number of items we need to reach the target count
        $items_to_process = array_slice( $stock_items, 0, $remaining_count );
        Logger::info( sprintf( 'Limiting batch processing: fetched %d items, processing only %d to reach target count %d', count( $stock_items ), $remaining_count, $count ) );
      endif;
    endif;
    
    $batch_result = ProductSync::process_import_products( $items_to_process );
    
    // Get API response metadata
    $api_count = isset( $response['Count'] ) ? (int) $response['Count'] : count( $stock_items );
    $api_rec_no = isset( $response['RecNo'] ) ? (int) $response['RecNo'] : 0;
    
    // Update progress with the actual number processed (not fetched)
    $actual_processed = count( $items_to_process );
    ProductSync::update_manual_import_progress( $api_rec_no, $actual_processed );
    
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
    $batch_result['requested_count'] = $count;
    $batch_result['actual_processed'] = $actual_processed;
    
    // Add raw response summary for JavaScript display
    $batch_result['raw_response_summary'] = [
      'Count' => $api_count,
      'RecNo' => $api_rec_no,
      'skipped_count' => $batch_result['skipped_count'] ?? 0,
      'total_fetched' => $batch_result['total_fetched'] ?? 0,
      'updated_count' => $batch_result['updated_count'] ?? 0,
      'actual_processed' => $actual_processed,
    ];
    
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
  public static function import_customers( int $count, int $offset, bool $update_only_changed = false, bool $weblist_only = false, bool $create_if_missing = true, ?CustomerCronLogger $logger = null ): array|\WP_Error {
    self::bootstrap_api();
    $response = self::$api->GetDebAccounts( '', '', $offset, $count );
    Logger::debug('response:', $response);
    if ( is_wp_error( $response ) )
      return $response;

    $customers = $response['Accounts'] ?? [];
    $summary = self::process_customers( $customers, $update_only_changed, $create_if_missing, $logger );
    $summary['api_count'] = $response['Count'] ?? count( $customers );
    $summary['api_rec_no'] = $response['RecNo'] ?? $offset + count( $customers );
    $summary['batch_complete'] = ! self::$api->has_more();

    return $summary;
  }

  /**
   * Process customers from Fincon API response.
   *
   * @param array $customers
   * @param bool $update_only_changed
   * @param bool $create_if_missing
   * @return array
   */
  public static function process_customers( array $customers, bool $update_only_changed = false, bool $create_if_missing = true, ?CustomerCronLogger $logger = null ): array {
    $created_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $errors = [];

    foreach ( $customers as $customer ) :
      $acc_no = $customer['AccNo'] ?? 'N/A';
      try {
        $result = CustomerSync::create_or_update_customer( $customer, $update_only_changed, $create_if_missing );
        $status = $result['status'] ?? CustomerSync::STATUS_SKIP;
        $user_id = $result['user_ids'][0] ?? 0;

        if ( $status === CustomerSync::STATUS_CREATE ) :
          $created_count++;
          $logger?->log_customer( $acc_no, $user_id, 'created' );
        elseif ( $status === CustomerSync::STATUS_UPDATE ) :
          $updated_count++;
          $logger?->log_customer( $acc_no, $user_id, 'updated' );
        else :
          $skipped_count++;
          $logger?->log_customer( $acc_no, $user_id, 'skipped' );
        endif;
      } catch ( \Exception $e ) {
        $skipped_count++;
        $errors[] = sprintf( 'Failed to import customer %s: %s', $acc_no, $e->getMessage() );
        $logger?->log_customer( $acc_no, 0, 'skipped', $e->getMessage() );
      }
    endforeach;

    $summary = [
      'total_fetched' => count( $customers ),
      'imported_count' => $created_count + $updated_count,
      'created_count' => $created_count,
      'updated_count' => $updated_count,
      'skipped_count' => $skipped_count,
      'errors' => $errors,
    ];

    if ( ! empty( $errors ) )
      Logger::error( 'Customer import finished with errors.', $summary );
    else
      Logger::info( 'Customer import successful.', $summary );

    return $summary;
  }

}