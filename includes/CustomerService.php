<?php
/**
 * Customer Service handling FinCon customer imports.
 *
 * @package df-fincon-connector
 */

namespace DF_FINCON;

use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class CustomerService {



  /**
   * Import a single batch of customers (used by cron/manual sync).
   *
   * @param bool $update_only_changed
   * @param bool $web_list_only
   * @return array|WP_Error
   */
  public static function import_batch( bool $update_only_changed = false, bool $web_list_only = true ): array|\WP_Error {
    $batch_state = CustomerSync::get_batch_state();

    if ( empty( $batch_state['in_progress'] ) ) {
      $options = CustomerSync::get_options();
      $batch_size = isset( $options['customer_batch_size'] ) ? (int) $options['customer_batch_size'] : 100;
      if ( $batch_size <= 0 )
        $batch_size = 100;
      CustomerSync::start_batch_import( $batch_size );
      $batch_state = CustomerSync::get_batch_state();
    }

    $options = CustomerSync::get_options();
    $batch_size = $batch_state['batch_size'] ?? ( $options['customer_batch_size'] ?? 100 );
    $start_rec_no = $batch_state['last_rec_no'] ?? 0;

    $result = self::import_customers( $batch_size, $start_rec_no, $update_only_changed, $web_list_only );

    if ( is_wp_error( $result ) ) {
      CustomerSync::complete_batch_import();
      return $result;
    }

    $api_count = $result['api_count'] ?? 0;
    $api_rec_no = $result['api_rec_no'] ?? 0;
    $requested_count = $result['requested_count'] ?? $batch_size;

    CustomerSync::update_batch_progress( $api_rec_no, $api_count );

    if ( $api_count < $requested_count ) {
      CustomerSync::complete_batch_import();
      $result['batch_complete'] = true;
    } else {
      $result['batch_complete'] = false;
    }

    $result['batch_state'] = CustomerSync::get_batch_state();
    return $result;
  }

  /**
   * Import a batch of customers from Fincon API.
   *
   * @param int $batch_size Number of customers to fetch per batch.
   * @param int $start_rec_no Record number to start from (pagination).
   * @param bool $update_only_changed Whether to skip unchanged customers.
   * @param bool $web_list_only Whether to only import customers with WebList flag.
   * @return array|WP_Error Result with api_count, api_rec_no, requested_count, and import summary.
   * @since 0.1.0
   */
  public static function import_customers( int $batch_size, int $start_rec_no, bool $update_only_changed = false, bool $web_list_only = true ): array|\WP_Error {
    // Delegate to FinconService which already implements the API call and processing.
    // Pass create_if_missing = false to only update existing customers (as per requirement).
    return FinconService::import_customers( $batch_size, $start_rec_no, $update_only_changed, $web_list_only, false );
  }


}

