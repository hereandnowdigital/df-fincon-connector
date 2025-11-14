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


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class FinconService {

  private static FinconApi $api;



  public function __construct() {
    self::$api = new FinconApi(['log_enabled' => false] );
  }

  public static function import_products( int $count, int $offset, bool $update_only_changed = false ): array|\WP_Error  {
    
    $api_response = self::$api::get_stock_items( $count, $offset );
    if ( is_wp_error( $api_response ) ) {
      Logger::error( sprintf( 'Product import failed during API fetch: %s', $api_response->get_error_message() ) );
      return $api_response;
    }

    $stock_items = $api_response['Stock'] ?? [];
    $imported_count = 0;
    $created_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $errors = [];

    if ( ! empty( $stock_items ) ) 
      foreach ( $stock_items as $item ) {

        $item_no = $item['ItemNo'] ?? 'N/A';
        $item_desc = $item['Description'] ?? 'No Description';
        
        if ( empty( $item_no ) || $item_no === 'N/A' ) {
          $skipped_count++;
          $errors[] = sprintf( 'Skipped item due to missing ItemNo at offset %d.', $offset + $imported_count + $skipped_count );
          continue;
        }

        try {
          $status = ProductSync::create_or_update_wc_product( $item, $update_only_changed );
            if ( $status === ProductSync::PROD_STATUS_CREATE ) 
              $created_count++;
            elseif ( $status === ProductSync::PROD_STATUS_UPDATE ) 
              $updated_count++;
            elseif ( $status === ProductSync::PROD_STATUS_SKIP ) 
              $skipped_count++;
            
            $imported_count++;

        } catch ( \Exception $e ) {
            $skipped_count++;
            $errors[] = sprintf( 'Failed to import Item %s (%s): %s', $item_no, $item_desc, $e->getMessage() );
        }
      }
    

      $summary = [
        'total_fetched' => count( $stock_items ),
        'imported_count' => $imported_count,
        'created_count' => $created_count,
        'updated_count' => $updated_count,
        'skipped_count' => $skipped_count,
        'next_rec_no' => $api_response['RecNo'] ?? $offset + $count, // The last record number returned by Fincon
        'errors' => $errors,
        'raw_response_summary' => [
            'Count' => $api_response['Count'] ?? 0,
            'RecNo' => $api_response['RecNo'] ?? 0,
        ],
      ];

      if ( ! empty( $errors ) ) 
        Logger::error( 'Product import finished with errors.', $summary );
      else 
        Logger::info( 'Product import successful.', $summary );
    
      wc_delete_product_transients();
      wp_cache_flush();
      //self::recount_terms();

      return $summary;

  }


}