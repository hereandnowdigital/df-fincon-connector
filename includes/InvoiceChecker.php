<?php
/**
 * Invoice Checker Class
 *
 * Handles checking for invoice availability in Fincon and fetching PDFs.
 * Manages cron-based checking of pending invoices and updates order meta.
 *
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * @subpackage Includes
 * Text Domain: df-fincon
 * @since   1.1.0
 */

namespace DF_FINCON;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class InvoiceChecker {

  /**
   * Singleton instance
   * 
   * @var self|null
   */
  private static ?self $instance = null;

  /**
   * Options name
   * 
   * @const string
   */
  public const OPTIONS_NAME = Plugin::OPTIONS_NAME . '_INVOICE_CHECKER';

  /**
   * Default batch size for checking invoices
   * 
   * @const int
   */
  private const DEFAULT_BATCH_SIZE = 10;

  /**
   * Maximum check attempts before giving up
   * 
   * @const int
   */
  private const MAX_CHECK_ATTEMPTS = 10;

  /**
   * Minimum time between checks (in seconds)
   * 
   * @const int
   */
  private const MIN_CHECK_INTERVAL = 300; // 5 minutes

  /**
   * Constructor (private for singleton)
   */
  private function __construct() {
    // Private constructor to enforce singleton pattern
    $this->init_hooks();
  }

  /**
   * Create an instance
   *
   * @return self
   * @since 1.1.0
   */
  public static function create(): self {
    if ( self::$instance === null )
      self::$instance = new self();

    return self::$instance;
  }

  /**
   * Initialize hooks
   *
   * @return void
   * @since 1.1.0
   */
  private function init_hooks(): void {
    // Cron action is registered by Cron::register_cron_hook()
    // which calls Cron::invoice_check_scheduled() which then calls $this->check_pending_invoices()
    
    // Add AJAX endpoints - match the actions used in OrderSync.php JavaScript
    add_action( 'wp_ajax_df_fincon_check_invoice_status', [ $this, 'ajax_check_invoice' ] );
    add_action( 'wp_ajax_df_fincon_fetch_pdf', [ $this, 'ajax_fetch_invoice_pdf' ] );
    
    // Add AJAX endpoints for invoice management page
    add_action( 'wp_ajax_df_fincon_bulk_check_invoices', [ $this, 'ajax_bulk_check_invoices' ] );
    add_action( 'wp_ajax_df_fincon_bulk_check_invoices_batch', [ $this, 'ajax_bulk_check_invoices_batch' ] );
    add_action( 'wp_ajax_df_fincon_schedule_invoice_check', [ $this, 'ajax_schedule_invoice_check' ] );
    add_action( 'wp_ajax_df_fincon_unschedule_invoice_check', [ $this, 'ajax_unschedule_invoice_check' ] );

    add_action( 'wp_ajax_df_fincon_rescan_sales_order', [ $this, 'ajax_rescan_sales_order' ] );

  }

  /**
   * Get invoice checker options
   *
   * @return array Options array
   * @since 1.1.0
   */
  public static function get_options(): array {
    $defaults = [
      'enabled' => true,
      'batch_size' => self::DEFAULT_BATCH_SIZE,
      'check_interval' => 900, // 15 minutes
      'max_attempts' => self::MAX_CHECK_ATTEMPTS,
      'auto_fetch_pdf' => true,
    ];
    
    $options = get_option( self::OPTIONS_NAME, [] );
    return wp_parse_args( $options, $defaults );
  }

  /**
   * Get orders with pending invoices
   *
   * @param int $limit Maximum number of orders to return
   * @return array Array of order IDs with pending invoices
   * @since 1.1.0
   */
  public function get_orders_with_pending_invoices( int $limit = 10 ): array {
    // Try to use wc_get_orders for better compatibility with HPOS and standard WooCommerce APIs
    // Fall back to direct SQL if wc_get_orders is not available (unlikely)
    if ( function_exists( 'wc_get_orders' ) ) {
      $meta_query = [
        'relation' => 'OR',
        [
          'key'   => OrderSync::META_INVOICE_STATUS,
          'value' => 'pending',
        ],
        [
          'relation' => 'AND',
          [
            'key'   => OrderSync::META_INVOICE_STATUS,
            'value' => 'available',
          ],
          [
            'key'   => OrderSync::META_PDF_AVAILABLE,
            'value' => '0',
            'compare' => '=',
          ],
        ],
      ];
      
      $args = [
        'limit'      => $limit,
        'orderby'    => 'ID',
        'order'      => 'DESC',
        'return'     => 'ids',
        'type'       => 'shop_order',
        'status'     => array_keys( \wc_get_order_statuses() ),
        'meta_query' => $meta_query,
      ];
      
      $order_ids = \wc_get_orders( $args );
      
      Logger::debug( 'Found orders with pending/available invoices via wc_get_orders', [
        'total_orders' => count( $order_ids ),
        'order_ids'    => $order_ids,
        'args'         => $args,
      ] );
      
      return $order_ids;
    }
    
    // Fallback: original SQL queries (kept for backward compatibility)
    global $wpdb;
    
    $order_ids = [];
    
    // Query for orders with pending invoice status
    $sql = $wpdb->prepare( "
      SELECT DISTINCT pm.post_id
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
      WHERE pm.meta_key = %s
      AND pm.meta_value = 'pending'
      AND p.post_type IN ('shop_order', 'shop_order_refund')
      AND p.post_status NOT IN ('trash', 'auto-draft')
      ORDER BY pm.post_id DESC
      LIMIT %d
    ", OrderSync::META_INVOICE_STATUS, $limit );
    
    Logger::debug( 'Pending invoices SQL query', [
      'sql' => $sql,
      'meta_key' => OrderSync::META_INVOICE_STATUS,
      'limit' => $limit,
    ] );
    
    $results = $wpdb->get_col( $sql );
    
    Logger::debug( 'Pending invoices query results', [
      'results_count' => count( $results ),
      'results' => $results,
    ] );
    
    if ( ! empty( $results ) ) {
      $order_ids = array_map( 'intval', $results );
    }
    
    // Also check for orders with 'available' status that haven't been downloaded yet
    $sql_available = $wpdb->prepare( "
      SELECT DISTINCT pm.post_id
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
      LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = %s
      WHERE pm.meta_key = %s
      AND pm.meta_value = 'available'
      AND (pm2.meta_value IS NULL OR pm2.meta_value = '0' OR pm2.meta_value = '')
      AND p.post_type IN ('shop_order', 'shop_order_refund')
      AND p.post_status NOT IN ('trash', 'auto-draft')
      ORDER BY pm.post_id DESC
      LIMIT %d
    ", OrderSync::META_PDF_AVAILABLE, OrderSync::META_INVOICE_STATUS, $limit );
    
    Logger::debug( 'Available invoices SQL query', [
      'sql' => $sql_available,
      'meta_key_pdf' => OrderSync::META_PDF_AVAILABLE,
      'meta_key_invoice' => OrderSync::META_INVOICE_STATUS,
      'limit' => $limit,
    ] );
    
    $results_available = $wpdb->get_col( $sql_available );
    
    Logger::debug( 'Available invoices query results', [
      'results_count' => count( $results_available ),
      'results' => $results_available,
    ] );
    
    if ( ! empty( $results_available ) ) {
      $order_ids_available = array_map( 'intval', $results_available );
      $order_ids = array_merge( $order_ids, $order_ids_available );
      $order_ids = array_unique( $order_ids );
    }
    
    Logger::debug( 'Found orders with pending/available invoices', [
      'total_orders' => count( $order_ids ),
      'order_ids' => $order_ids,
    ] );
    
    return $order_ids;
  }

  /**
   * Check invoice status for a specific order
   *
   * @param int $order_id WooCommerce order ID
   * @return array|\WP_Error Check result or WP_Error
   * @since 1.1.0
   */
  public function check_invoice_status( int $order_id ): array|\WP_Error {
    $order = \wc_get_order( $order_id );
    
    if ( ! $order )
      return new \WP_Error( 'invalid_order', sprintf( 'Order %d not found', $order_id ) );
    
    $fincon_order_no = $order->get_meta( OrderSync::META_ORDER_NO );
    
    if ( empty( $fincon_order_no ) )
      return new \WP_Error( 'no_fincon_order', sprintf( 'Order %d has no Fincon order number', $order_id ) );
    
    // Get sales order info from Fincon API
    $fincon_api = new FinconApi();
    $api_response = $fincon_api->get_sales_order_by_order_no( $fincon_order_no );
    
    if ( is_wp_error( $api_response ) ) {
      Logger::error( 'Failed to get sales order info from Fincon', [
        'order_id' => $order_id,
        'fincon_order_no' => $fincon_order_no,
        'error' => $api_response->get_error_message(),
      ] );
      return $api_response;
    }
    
    // Handle different response structures
    // The API might return SalesOrders array or direct SalesOrderInfo
    $sales_order_info = null;
    
    if ( isset( $api_response['SalesOrderInfo'] ) ) {
      // Direct SalesOrderInfo (from create_sales_order response)
      $sales_order_info = $api_response['SalesOrderInfo'];
    } elseif ( isset( $api_response['SalesOrders'] ) && is_array( $api_response['SalesOrders'] ) && ! empty( $api_response['SalesOrders'] ) ) {
      // SalesOrders array (from GetSalesOrdersByOrderNo)
      $sales_order_info = $api_response['SalesOrders'][0];
    } else {
      // Try to use the response directly
      $sales_order_info = $api_response;
    }
    
    // Extract invoice numbers
    $invoice_numbers = $sales_order_info['InvoiceNumbers'] ?? '';
    $invoice_numbers = trim( $invoice_numbers );
    
    Logger::debug( 'Invoice check result', [
      'order_id' => $order_id,
      'fincon_order_no' => $fincon_order_no,
      'invoice_numbers' => $invoice_numbers,
      'has_invoice_numbers' => ! empty( $invoice_numbers ),
    ] );
    
    // Update order meta
    $current_invoice_numbers = $order->get_meta( OrderSync::META_INVOICE_NUMBERS );
    $current_status = $order->get_meta( OrderSync::META_INVOICE_STATUS );
    $check_attempts = (int) $order->get_meta( OrderSync::META_INVOICE_CHECK_ATTEMPTS );
    
    $check_attempts++;
    $order->update_meta_data( OrderSync::META_INVOICE_CHECK_ATTEMPTS, $check_attempts );
    $order->update_meta_data( OrderSync::META_INVOICE_LAST_CHECK, current_time( 'mysql' ) );
    
    $result = [
      'order_id' => $order_id,
      'fincon_order_no' => $fincon_order_no,
      'invoice_numbers' => $invoice_numbers,
      'previous_invoice_numbers' => $current_invoice_numbers,
      'check_attempts' => $check_attempts,
      'status_changed' => false,
    ];
    
    if ( ! empty( $invoice_numbers ) ) {
      // Invoice numbers are now available
      if ( $current_invoice_numbers !== $invoice_numbers ) {
        $order->update_meta_data( OrderSync::META_INVOICE_NUMBERS, $invoice_numbers );
        $result['status_changed'] = true;
      }

      // Determine invoice status
      $invoice_numbers_array = array_map( 'trim', explode( ',', $invoice_numbers ) );
      if ( count( $invoice_numbers_array ) > 1 ) {
        $new_status = 'multiple';
      } else {
        $new_status = 'available';
      }

      if ( $current_status !== $new_status ) {
        $order->update_meta_data( OrderSync::META_INVOICE_STATUS, $new_status );
        $order->update_meta_data( OrderSync::META_PDF_AVAILABLE, true );
        $result['status_changed'] = true;
        $result['new_status'] = $new_status;
      }

      // Lifecycle: sales order approved
      // $sales_order_info is already resolved above from the API response
      $so_approved = trim( $sales_order_info['Approved'] ?? 'N' );
      if ( $so_approved === 'Y' ) {
        OrderSync::transition_lifecycle(
          $order,
          'sales_order_approved',
          'Fincon: Sales order approved.'
        );
      }

      // Lifecycle: invoice created (only write note on first detection)
      if ( $result['status_changed'] ) {
        $first_inv = trim( $invoice_numbers_array[0] );
        OrderSync::transition_lifecycle(
          $order,
          'invoice_created',
          sprintf( 'Fincon: Invoice %s generated. PDF available.', $first_inv )
        );
      }

    } else {
      // Still no invoice numbers
      if ( $check_attempts >= self::MAX_CHECK_ATTEMPTS ) {
        $order->update_meta_data( OrderSync::META_INVOICE_STATUS, 'error' );
        $order->update_meta_data( OrderSync::META_PDF_AVAILABLE, false );

        $note = sprintf(
          __( 'Invoice check failed after %d attempts. Manual intervention required.', 'df-fincon' ),
          $check_attempts
        );
        $order->add_order_note( $note, false, false );

        $result['new_status']     = 'error';
        $result['status_changed'] = true;
      }
    }
    
    $order->save();
    
    Logger::info( 'Invoice check completed', $result );
    
    return $result;
  }

  /**
   * Fetch PDF for available invoice
   *
   * @param int $order_id WooCommerce order ID
   * @return array|\WP_Error Fetch result or WP_Error
   * @since 1.1.0
   */
  public function fetch_invoice_pdf( int $order_id ): array|\WP_Error {
    $order = \wc_get_order( $order_id );
    
    if ( ! $order )
      return new \WP_Error( 'invalid_order', sprintf( 'Order %d not found', $order_id ) );
    
    $invoice_numbers = $order->get_meta( OrderSync::META_INVOICE_NUMBERS );
    $invoice_status = $order->get_meta( OrderSync::META_INVOICE_STATUS );
    
    if ( empty( $invoice_numbers ) )
      return new \WP_Error( 'no_invoice_numbers', sprintf( 'Order %d has no invoice numbers', $order_id ) );
    
    if ( ! in_array( $invoice_status, [ 'available', 'multiple' ], true ) )
      return new \WP_Error( 'invalid_status', sprintf( 'Order %d invoice status is %s, not available for download', $order_id, $invoice_status ) );
    
    $invoice_numbers_array = array_map( 'trim', explode( ',', $invoice_numbers ) );
    
    // Fetch PDFs using PdfStorage
    $pdf_storage = PdfStorage::create();
    $result = $pdf_storage->fetch_and_save_multiple_pdfs( $order_id, 'I', $invoice_numbers_array );
    
    if ( is_wp_error( $result ) )
      return $result;
    
    // Update order status to 'downloaded' if successful
    if ( ! empty( $result['successful'] ) ) {
      $order->update_meta_data( OrderSync::META_INVOICE_STATUS, 'downloaded' );
      $order->update_meta_data( OrderSync::META_PDF_AVAILABLE, true );
      $order->save();
      
      // Add order note
      $success_count = count( $result['successful'] );
      $note = sprintf(
        _n(
          'Fincon invoice PDF downloaded successfully.',
          '%d Fincon invoice PDFs downloaded successfully.',
          $success_count,
          'df-fincon'
        ),
        $success_count
      );
      $order->add_order_note( $note, false, false );
    }

    Logger::info( 'Invoice PDF fetch completed', [
      'order_id' => $order_id,
      'invoice_numbers' => $invoice_numbers,
      'successful' => count( $result['successful'] ?? [] ),
      'failed' => count( $result['failed'] ?? [] ),
    ] );
    
    OrderSync::transition_lifecycle(
      $order,
      'invoice_downloaded',
      'Fincon: Invoice PDF downloaded and attached to order.'
    );

    return $result;
  }

  /**
   * Check pending invoices (cron job)
   *
   * @return array Results of the check
   * @since 1.1.0
   */
  public function check_pending_invoices(): array {
    $options = self::get_options();
    
    if ( empty( $options['enabled'] ) ) {
      Logger::debug( 'Invoice checking is disabled, skipping cron job' );
      return [ 'skipped' => true, 'reason' => 'disabled' ];
    }
    
    $batch_size = (int) ( $options['batch_size'] ?? self::DEFAULT_BATCH_SIZE );
    $auto_fetch = ! empty( $options['auto_fetch_pdf'] );
    
    $order_ids = $this->get_orders_with_pending_invoices( $batch_size );
    
    if ( empty( $order_ids ) ) {
      Logger::debug( 'No orders with pending invoices found' );
      return [ 'processed' => 0, 'message' => 'No pending invoices' ];
    }
    
    $results = [
      'total' => count( $order_ids ),
      'checked' => [],
      'fetched' => [],
      'errors' => [],
    ];
    
    foreach ( $order_ids as $order_id ) {
      $order_obj = wc_get_order( $order_id );
      if ( $order_obj ) {
        $this->resolve_quote_to_sales_order( $order_obj );
        // Reload after potential save in resolver
        $order_obj = wc_get_order( $order_id );
      }
      try {
        // Check invoice status
        $check_result = $this->check_invoice_status( $order_id );
        
        if ( is_wp_error( $check_result ) ) {
          $results['errors'][] = [
            'order_id' => $order_id,
            'error' => $check_result->get_error_message(),
          ];
          continue;
        }
        
        $results['checked'][] = [
          'order_id' => $order_id,
          'invoice_numbers' => $check_result['invoice_numbers'] ?? '',
          'status_changed' => $check_result['status_changed'] ?? false,
        ];
        
        // Auto-fetch PDF if enabled and invoice is available
        if ( $auto_fetch && ! empty( $check_result['invoice_numbers'] ) ) {
          $fetch_result = $this->fetch_invoice_pdf( $order_id );
          
          if ( ! is_wp_error( $fetch_result ) ) {
            $results['fetched'][] = [
              'order_id' => $order_id,
              'successful' => count( $fetch_result['successful'] ?? [] ),
              'failed' => count( $fetch_result['failed'] ?? [] ),
            ];
          }
        }
      } catch ( \Exception $e ) {
        $results['errors'][] = [
          'order_id' => $order_id,
          'error' => $e->getMessage(),
          'exception' => get_class( $e ),
        ];
        
        Logger::error( 'Exception during invoice check', [
          'order_id' => $order_id,
          'exception' => $e->getMessage(),
          'trace' => $e->getTraceAsString(),
        ] );
      }
    }
    
    Logger::info( 'Invoice check cron job completed', $results );
    
    return $results;
  }

  /**
   * AJAX endpoint: Check invoice status
   *
   * @return void
   * @since 1.1.0
   */
  public function ajax_check_invoice(): void {
    // Security check
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( -1 );
    
    check_ajax_referer( 'df_fincon_check_invoice_status', 'nonce' );
    
    $order_id = (int) ( $_POST['order_id'] ?? 0 );
    $order_no = sanitize_text_field( $_POST['order_no'] ?? '' );
    
    if ( ! $order_id ) {
      wp_send_json_error( [ 'message' => __( 'Invalid order ID', 'df-fincon' ) ] );
      return;
    }
    
    $result = $this->check_invoice_status( $order_id );
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [
        'message' => $result->get_error_message(),
        'error_code' => $result->get_error_code(),
      ] );
      return;
    }
    
    wp_send_json_success( [
      'message' => __( 'Invoice check completed', 'df-fincon' ),
      'data' => $result,
    ] );
  }

  /**
   * AJAX endpoint: Fetch invoice PDF
   *
   * @return void
   * @since 1.1.0
   */
  public function ajax_fetch_invoice_pdf(): void {
    // Security check
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( -1 );
    
    check_ajax_referer( 'df_fincon_fetch_pdf', 'nonce' );
    
    $order_id = (int) ( $_POST['order_id'] ?? 0 );
    $doc_no = sanitize_text_field( $_POST['doc_no'] ?? '' );
    
    if ( ! $order_id ) {
      wp_send_json_error( [ 'message' => __( 'Invalid order ID', 'df-fincon' ) ] );
      return;
    }
    
    // If doc_no is provided, use the old single PDF method
    // Otherwise, use the new multiple PDF method
    if ( ! empty( $doc_no ) ) {
      // Old method - single PDF by doc_no
      $pdf_storage = PdfStorage::create();
      $result = $pdf_storage->fetch_and_save_pdf( $order_id, 'I', $doc_no );
    } else {
      // New method - fetch all available invoices
      $result = $this->fetch_invoice_pdf( $order_id );
    }
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [
        'message' => $result->get_error_message(),
        'error_code' => $result->get_error_code(),
      ] );
      return;
    }
    
    wp_send_json_success( [
      'message' => __( 'Invoice PDF fetch completed', 'df-fincon' ),
      'data' => $result,
    ] );
  }


  /**
   * Get invoice statistics
   *
   * @return array Invoice statistics
   * @since 1.1.0
   */
  public function get_invoice_statistics(): array {
    global $wpdb;
    
    $stats = [
      'total_orders' => 0,
      'pending' => 0,
      'available' => 0,
      'downloaded' => 0,
      'multiple' => 0,
      'error' => 0,
      'with_pdf' => 0,
    ];
    
    // Get total synced orders
    $total_query = $wpdb->prepare( "
      SELECT COUNT(DISTINCT pm.post_id)
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
      WHERE pm.meta_key = %s
      AND pm.meta_value = '1'
      AND p.post_type IN ('shop_order', 'shop_order_refund')
      AND p.post_status NOT IN ('trash', 'auto-draft')
    ", OrderSync::META_SYNCED );
    
    $stats['total_orders'] = (int) $wpdb->get_var( $total_query );
    
    // Get counts by invoice status
    $status_query = $wpdb->prepare( "
      SELECT pm.meta_value as status, COUNT(DISTINCT pm.post_id) as count
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
      WHERE pm.meta_key = %s
      AND p.post_type IN ('shop_order', 'shop_order_refund')
      AND p.post_status NOT IN ('trash', 'auto-draft')
      GROUP BY pm.meta_value
    ", OrderSync::META_INVOICE_STATUS );
    
    $status_results = $wpdb->get_results( $status_query, ARRAY_A );
    
    foreach ( $status_results as $row ) {
      $status = $row['status'];
      $count = (int) $row['count'];
      
      if ( isset( $stats[$status] ) ) {
        $stats[$status] = $count;
      }
    }
    
    // Get orders with PDFs
    $pdf_query = $wpdb->prepare( "
      SELECT COUNT(DISTINCT pm.post_id)
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
      WHERE pm.meta_key = %s
      AND pm.meta_value = '1'
      AND p.post_type IN ('shop_order', 'shop_order_refund')
      AND p.post_status NOT IN ('trash', 'auto-draft')
    ", OrderSync::META_PDF_AVAILABLE );
    
    $stats['with_pdf'] = (int) $wpdb->get_var( $pdf_query );
    
    return $stats;
  }

  /**
   * AJAX endpoint: Bulk check all pending invoices
   *
   * @return void
   * @since 1.1.0
   */
  public function ajax_bulk_check_invoices(): void {
    // Security check
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( -1 );
    
    check_ajax_referer( 'df_fincon_bulk_check_invoices', 'nonce' );
    
    $result = $this->check_pending_invoices();
    
    if ( isset( $result['skipped'] ) && $result['skipped'] ) {
      wp_send_json_success( [
        'message' => __( 'Invoice checking is disabled or no pending invoices found', 'df-fincon' ),
        'data' => $result,
      ] );
      return;
    }
    
    $checked_count = count( $result['checked'] ?? [] );
    $updated_count = 0;
    
    // Count how many had status changes
    foreach ( $result['checked'] as $check ) {
      if ( ! empty( $check['status_changed'] ) ) {
        $updated_count++;
      }
    }
    
    wp_send_json_success( [
      'message' => sprintf(
        __( 'Bulk check completed: %d invoices checked, %d updated', 'df-fincon' ),
        $checked_count,
        $updated_count
      ),
      'data' => [
        'checked_count' => $checked_count,
        'updated' => $updated_count,
        'fetched' => count( $result['fetched'] ?? [] ),
        'errors' => count( $result['errors'] ?? [] ),
      ],
    ] );
  }

  /**
   * AJAX endpoint: Bulk check invoices in batches with progress
   *
   * @return void
   * @since 1.1.0
   */
  public function ajax_bulk_check_invoices_batch(): void {
    // Security check
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( -1 );
    
    check_ajax_referer( 'df_fincon_bulk_check_invoices_batch', 'nonce' );
    
    $batch_size = (int) ( $_POST['batch_size'] ?? self::DEFAULT_BATCH_SIZE );
    $offset = (int) ( $_POST['offset'] ?? 0 );
    
    // Get total pending orders
    global $wpdb;
    $total_query = $wpdb->prepare( "
      SELECT COUNT(DISTINCT pm.post_id)
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
      WHERE pm.meta_key = %s
      AND pm.meta_value = 'pending'
      AND p.post_type IN ('shop_order', 'shop_order_refund')
      AND p.post_status NOT IN ('trash', 'auto-draft')
    ", OrderSync::META_INVOICE_STATUS );
    
    $total_pending = (int) $wpdb->get_var( $total_query );
    
    if ( $total_pending === 0 ) {
      wp_send_json_success( [
        'completed' => true,
        'message' => __( 'No pending invoices found', 'df-fincon' ),
        'final_message' => __( 'Bulk check completed: No pending invoices found', 'df-fincon' ),
        'processed' => 0,
        'total' => 0,
        'updated' => 0,
        'progress' => 100,
      ] );
      return;
    }
    
    // Get orders for this batch
    $order_ids = $this->get_orders_with_pending_invoices( $batch_size );
    
    if ( empty( $order_ids ) ) {
      wp_send_json_success( [
        'completed' => true,
        'message' => __( 'No more pending invoices to check', 'df-fincon' ),
        'final_message' => __( 'Bulk check completed', 'df-fincon' ),
        'processed' => $offset,
        'total' => $total_pending,
        'updated' => 0,
        'progress' => 100,
      ] );
      return;
    }
    
    $processed = $offset;
    $updated = 0;
    
    // Process each order in the batch
    foreach ( $order_ids as $order_id ) {
      $result = $this->check_invoice_status( $order_id );
      
      if ( ! is_wp_error( $result ) && ! empty( $result['status_changed'] ) ) {
        $updated++;
      }
      
      $processed++;
    }
    
    $progress = min( 100, round( ( $processed / $total_pending ) * 100 ) );
    $completed = $processed >= $total_pending;
    
    if ( $completed ) {
      $message = sprintf(
        __( 'Bulk check completed: %d invoices checked, %d updated', 'df-fincon' ),
        $processed,
        $updated
      );
    } else {
      $message = sprintf(
        __( 'Processing batch: %d of %d invoices checked', 'df-fincon' ),
        $processed,
        $total_pending
      );
    }
    
    wp_send_json_success( [
      'completed' => $completed,
      'message' => $message,
      'final_message' => $completed ? $message : '',
      'processed' => $processed,
      'total' => $total_pending,
      'updated' => $updated,
      'progress' => $progress,
      'next_offset' => $processed,
    ] );
  }

  /**
   * AJAX endpoint: Schedule invoice check cron job
   *
   * @return void
   * @since 1.1.0
   */
  public function ajax_schedule_invoice_check(): void {
    // Security check
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( -1 );
    
    check_ajax_referer( 'df_fincon_schedule_invoice_check', 'nonce' );
    
    // Schedule the cron job
    Cron::schedule_invoice_check();
    
    $next_run = wp_next_scheduled( Cron::INVOICE_CHECK_HOOK );
    
    if ( $next_run ) {
      $next_run_formatted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run );
      wp_send_json_success( [
        'message' => sprintf(
          __( 'Invoice check scheduled successfully. Next check: %s', 'df-fincon' ),
          $next_run_formatted
        ),
      ] );
    } else {
      wp_send_json_error( [
        'message' => __( 'Failed to schedule invoice check. Please check plugin settings.', 'df-fincon' ),
      ] );
    }
  }

  /**
   * AJAX endpoint: Unschedule invoice check cron job
   *
   * @return void
   * @since 1.1.0
   */
  public function ajax_unschedule_invoice_check(): void {
    // Security check
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( -1 );
    
    check_ajax_referer( 'df_fincon_unschedule_invoice_check', 'nonce' );
    
    // Unschedule the cron job
    $timestamp = wp_next_scheduled( Cron::INVOICE_CHECK_HOOK );
    if ( $timestamp ) {
      wp_unschedule_event( $timestamp, Cron::INVOICE_CHECK_HOOK );
    }
    
    wp_send_json_success( [
      'message' => __( 'Invoice check unscheduled successfully.', 'df-fincon' ),
    ] );
  }

 /**
   * Check whether a Fincon quote has been converted to a sales order by the
   * Fincon sales team, and if so persist the sales order number and advance
   * the lifecycle state.
   *
   * This runs inside the existing cron loop BEFORE the invoice-polling step.
   * Once META_ORDER_NO is populated, the existing invoice logic takes over
   * unchanged.
   *
   * @param \WC_Order $order The WooCommerce order to check.
   * @return void
   * @since 1.2.0
   */
  public function resolve_quote_to_sales_order( \WC_Order $order ): void {
    $quote_no = $order->get_meta( OrderSync::META_QUOTE_NO );
    $order_no = $order->get_meta( OrderSync::META_ORDER_NO );
 
    // Skip: no quote on this order, or we already have the sales order number
    if ( empty( $quote_no ) || ! empty( $order_no ) )
      return;
 
    // Enforce the poll-attempt cap to avoid hammering the API indefinitely
    $attempts = (int) $order->get_meta( OrderSync::META_SALES_ORDER_LOOKUP_ATTEMPTS );
 
    if ( $attempts >= OrderSync::MAX_SALES_ORDER_LOOKUP_ATTEMPTS ) {
      // Cap reached – stop polling. The meta-box "Re-scan" button resets this.
      Logger::debug( 'Sales order lookup attempt cap reached – waiting for manual re-scan.', [
        'order_id' => $order->get_id(),
        'quote_no' => $quote_no,
        'attempts' => $attempts,
      ] );
 
      // Write a once-only note when the cap is first hit
      $lifecycle_state = $order->get_meta( OrderSync::META_LIFECYCLE_STATE );
      if ( $lifecycle_state === 'quote_created' ) {
        OrderSync::transition_lifecycle(
          $order,
          'quote_created', // stay in same state – transition() is idempotent, so we
                           // manually add a note without changing state
        );
        // Add a one-time cap note directly
        $last_cap_note = $order->get_meta( '_fincon_sales_order_cap_noted' );
        if ( empty( $last_cap_note ) ) {
          $order->add_order_note(
            sprintf(
              'Fincon: Stopped polling for sales order after %d attempts. Use "Re-scan for Sales Order" button to retry.',
              $attempts
            ),
            false,
            false
          );
          $order->update_meta_data( '_fincon_sales_order_cap_noted', current_time( 'mysql' ) );
          $order->save();
        }
      }
      return;
    }
 
    // Increment attempt counter before the API call
    $order->update_meta_data( OrderSync::META_SALES_ORDER_LOOKUP_ATTEMPTS, $attempts + 1 );
    $order->save();
 
    // Resolve the AccNo we used when creating the quote
    $acc_no = $this->resolve_order_acc_no( $order );
    if ( empty( $acc_no ) ) {
      Logger::warning( 'Cannot resolve AccNo for sales order lookup.', [
        'order_id' => $order->get_id(),
        'quote_no' => $quote_no,
      ] );
      return;
    }
 
    $fincon_api = new FinconApi();
    $order_date = $order->get_date_created();
    $min_date   = $order_date ? $order_date->format( 'Ymd' ) : date( 'Ymd' );
    $response = $fincon_api->get_sales_orders_by_date( $min_date );
 
    if ( is_wp_error( $response ) ) {
      Logger::warning( 'Sales order lookup API call failed.', [
        'order_id' => $order->get_id(),
        'quote_no' => $quote_no,
        'error'    => $response->get_error_message(),
      ] );
      return;
    }
 
    // Search for a sales order that references our quote number or our WC order ID
    $wc_customer_ref = sprintf( 'WC-%d', $order->get_id() );
    $matched         = null;
 
    foreach ( $response['SalesOrders'] ?? [] as $so ) {
      $so_quote_no  = trim( $so['QuoteNo']     ?? '' );
      $so_cust_ref  = trim( $so['CustomerRef'] ?? '' );

      if ( $so_quote_no === $quote_no || $so_cust_ref === $wc_customer_ref ) {
          $matched = $so;
          break;
      }
    }
 
    if ( ! $matched ) {
      Logger::debug( 'No matching sales order found for quote yet.', [
        'order_id'    => $order->get_id(),
        'quote_no'    => $quote_no,
        'acc_no'      => $acc_no,
        'so_count'    => count( $response['SalesOrders'] ?? [] ),
      ] );
      return; // Try again on the next cron tick
    }
 
    $found_order_no = trim( $matched['OrderNo']  ?? '' );
    $is_approved    = ( trim( $matched['Approved'] ?? 'N' ) ) === 'Y';
    $invoice_nos    = trim( $matched['InvoiceNumbers'] ?? '' );
 
    if ( empty( $found_order_no ) ) {
      Logger::warning( 'Matched sales order has empty OrderNo – skipping.', [
        'order_id' => $order->get_id(),
        'quote_no' => $quote_no,
      ] );
      return;
    }
 
    // Persist the linked sales order number
    $order->update_meta_data( OrderSync::META_ORDER_NO, $found_order_no );
    $order->save();
 
    OrderSync::transition_lifecycle(
      $order,
      'sales_order_created',
      sprintf(
        'Fincon: Sales order %s created (linked to quote %s).',
        $found_order_no,
        $quote_no
      )
    );
 
    Logger::info( 'Sales order resolved from quote.', [
      'order_id'       => $order->get_id(),
      'quote_no'       => $quote_no,
      'found_order_no' => $found_order_no,
      'is_approved'    => $is_approved,
    ] );
 
    // Advance to approved if already approved at time of discovery
    if ( $is_approved ) {
      OrderSync::transition_lifecycle(
        $order,
        'sales_order_approved',
        'Fincon: Sales order approved.'
      );
    }
 
    // If invoice numbers are already populated, advance that state too
    if ( ! empty( $invoice_nos ) ) {
      $order->update_meta_data( OrderSync::META_INVOICE_NUMBERS, $invoice_nos );
      $order->update_meta_data( OrderSync::META_INVOICE_STATUS,  'available' );
      $order->save();
 
      $first_inv = explode( ',', $invoice_nos )[0];
      OrderSync::transition_lifecycle(
        $order,
        'invoice_created',
        sprintf( 'Fincon: Invoice %s generated.', trim( $first_inv ) )
      );
    }
  }

 /**
   * Return the AccNo used when creating the quote, falling back to the
   * configured B2C debt account for guest / retail orders.
   *
   * @param \WC_Order $order
   * @return string AccNo, or empty string if not determinable.
   * @since 1.2.0
   */
  private function resolve_order_acc_no( \WC_Order $order ): string {
    $customer_id = $order->get_customer_id();
    $acc_no      = $customer_id
      ? (string) get_user_meta( $customer_id, CustomerSync::META_ACCNO, true )
      : '';
 
    if ( ! empty( $acc_no ) )
      return $acc_no;
 
    $options = OrderSync::get_options();
    return (string) ( $options['b2c_debt_account'] ?? '' );
  }

 /**
   * AJAX endpoint: Re-scan a single order for its linked sales order.
   *
   * Resets the attempt counter so the resolver will try again immediately,
   * regardless of whether the cap was previously reached.
   *
   * Action : df_fincon_rescan_sales_order
   * Nonce  : df_fincon_rescan_sales_order_{order_id}
   *
   * @return void
   * @since 1.2.0
   */
  public function ajax_rescan_sales_order(): void {
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( -1 );
 
    $order_id = absint( $_POST['order_id'] ?? 0 );
    $nonce    = sanitize_text_field( $_POST['nonce'] ?? '' );
 
    if ( ! $order_id || ! wp_verify_nonce( $nonce, 'df_fincon_rescan_sales_order_' . $order_id ) ) {
      wp_send_json_error( [ 'message' => __( 'Invalid request.', 'df-fincon' ) ] );
      return;
    }
 
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
      wp_send_json_error( [ 'message' => __( 'Order not found.', 'df-fincon' ) ] );
      return;
    }
 
    // Reset the cap so the resolver will run
    $order->update_meta_data( OrderSync::META_SALES_ORDER_LOOKUP_ATTEMPTS, 0 );
    $order->delete_meta_data( '_fincon_sales_order_cap_noted' );
    $order->save();
 
    // Run the resolver immediately
    $this->resolve_quote_to_sales_order( $order );
 
    // Reload to get latest meta
    $order = wc_get_order( $order_id );
    $found_order_no = $order->get_meta( OrderSync::META_ORDER_NO );
 
    if ( ! empty( $found_order_no ) ) {
      wp_send_json_success( [
        'message'  => sprintf(
          __( 'Sales order %s found and linked.', 'df-fincon' ),
          $found_order_no
        ),
        'order_no' => $found_order_no,
      ] );
    } else {
      wp_send_json_success( [
        'message'  => __( 'Re-scan complete – no matching sales order found in Fincon yet.', 'df-fincon' ),
        'order_no' => '',
      ] );
    }
  }

}