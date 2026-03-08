<?php
/**
 * Order Synchronization Class
 *
 * Manages the synchronization of WooCommerce orders to Fincon accounting system.
 * Creates sales orders in Fincon when WooCommerce orders are successfully paid.
 *
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * @subpackage Includes
 * Text Domain: df-fincon
 * @since   1.0.0
 */

namespace DF_FINCON;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class OrderSync {

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
  public const OPTIONS_NAME = Plugin::OPTIONS_NAME . '_ORDER';

  /**
   * Order meta key: Sync completed flag
   * 
   * @const string
   */
  public const META_SYNCED = '_fincon_synced';

  /**
   * Order meta key: Fincon order number
   * 
   * @const string
   */
  public const META_ORDER_NO = '_fincon_order_no';

  /**
   * Order meta key: Fincon receipt number
   * 
   * @const string
   */
  public const META_RECEIPT_NO = '_fincon_receipt_no';

  /**
   * Order meta key: Full API response JSON
   * 
   * @const string
   */
  public const META_SYNC_RESPONSE = '_fincon_sync_response';

  /**
   * Order meta key: Sync timestamp
   * 
   * @const string
   */
  public const META_SYNC_TIMESTAMP = '_fincon_sync_timestamp';

  /**
   * Order meta key: Sync error message
   *
   * @const string
   */
  public const META_SYNC_ERROR = '_fincon_sync_error';

  /**
   * Order meta key: Sync retry attempt count
   *
   * @const string
   */
  public const META_SYNC_RETRY_COUNT = '_fincon_sync_retry_count';

  /**
   * Maximum number of sync retry attempts before giving up
   *
   * @const int
   */
  public const MAX_SYNC_RETRIES = 5;

  /**
   * Order meta key: Fincon invoice document number (DocNo from SalesOrderDetail)
   *
   * @const string
   */
  public const META_INVOICE_DOCNO = '_fincon_invoice_docno';

  /**
   * Order meta key: Fincon invoice numbers (comma-separated from SalesOrderInfo.InvoiceNumbers)
   *
   * @const string
   * @since 1.1.0
   */
  public const META_INVOICE_NUMBERS = '_fincon_invoice_numbers';

  /**
   * Order meta key: Invoice status (pending/available/downloaded/error/multiple)
   *
   * @const string
   * @since 1.1.0
   */
  public const META_INVOICE_STATUS = '_fincon_invoice_status';

  /**
   * Order meta key: Last invoice check timestamp
   *
   * @const string
   * @since 1.1.0
   */
  public const META_INVOICE_LAST_CHECK = '_fincon_invoice_last_check';

  /**
   * Order meta key: Invoice check attempts count
   *
   * @const string
   * @since 1.1.0
   */
  public const META_INVOICE_CHECK_ATTEMPTS = '_fincon_invoice_check_attempts';

  /**
   * Order meta key: PDF available flag
   *
   * @const string
   * @since 1.1.0
   */
  public const META_PDF_AVAILABLE = '_fincon_pdf_available';

  /**
   * Order meta key: Multiple PDF paths (serialized array for multiple invoices)
   *
   * @const string
   * @since 1.1.0
   */
  public const META_PDF_PATHS = '_fincon_pdf_paths';

  /**
   * Order meta key: PDF document path (single PDF - backward compatibility)
   *
   * @const string
   */
  public const META_PDF_PATH = '_fincon_pdf_path';

  /**
   * Order meta key: Selected location code
   *
   * @const string
   * @since 1.0.0
   */
  public const META_LOCATION_CODE = '_fincon_location_code';

  /**
   * Order meta key: Selected location name
   *
   * @const string
   * @since 1.0.0
   */
  public const META_LOCATION_NAME = '_fincon_location_name';

  /**
   * Order meta key: Selected location rep code
   *
   * @const string
   * @since 1.0.0
   */
  public const META_REP_CODE = '_fincon_rep_code';

  /**
   * Order meta key: Price list used for the order
   *
   * @const string
   * @since 1.0.0
   */
  public const META_PRICE_LIST = '_fincon_price_list';

  /**
   * Order meta key: Price list label (human-readable)
   *
   * @const string
   * @since 1.0.0
   */
  public const META_PRICE_LIST_LABEL = '_fincon_price_list_label';

  /**
   * Order meta key: Customer type (retail/dealer)
   *
   * @const string
   * @since 1.0.0
   */
  public const META_CUSTOMER_TYPE = '_fincon_customer_type';

  /**
   * Default stock location (Johannesburg)
   *
   * @const string
   */
  private const DEFAULT_LOCNO = '00';

  /**
   * Default sales rep code
   *
   * @const string
   */
  private const DEFAULT_REPCODE = '079';

/**
   * Default currency code (South African Rand)
   *
   * @const string
   */
  private const DEFAULT_CURRENCY = 'ZAR';

  /**
   * Default exchange rate for local currency
   * 
   * @const float
   */
  private const DEFAULT_EXCHANGE_RATE = 1.0000000;

  /**
   * Default tax code (VAT)
   * 
   * @const int
   */
  private const DEFAULT_TAX_CODE = 1;

  /**
   * Payment method mapping: WC payment method → Fincon PayType
   * 
   * @const array
   */
  private const PAYMENT_METHOD_MAP = [
    'stripe' => 'C',      // Card
    'paypal' => 'T',      // Transfer (electronic)
    'cod' => 'T',         // Cash on delivery as transfer
    'bank_transfer' => 'T',
    'cheque' => 'T',
    'bacs' => 'T',
    'payfast' => 'T',
    'ozow' => 'T',
    'yoco' => 'C',
  ];




  /**
   * Constructor (private for singleton)
   */
  private function __construct() {
    
    self::register_actions();
    self::register_actions();

  }

  /**
   * Create an instance
   *
   * @return self
   * @since 1.0.0
   */
  public static function create(): self {
    if ( self::$instance === null )
      self::$instance = new self();

    return self::$instance;
  }

  private function register_actions() {

  }

  private function register_filters() {
    
  }

  /**
   * Get default location code from LocationManager
   *
   * @return string Default location code
   * @since 1.0.0
   */
  private function get_default_locno(): string {
    #EMTODO: Fix
    return self::DEFAULT_LOCNO;
    $default_location = LocationManager::create()->get_default_location();
    return $default_location['code'] ?? self::DEFAULT_LOCNO;
  }

  /**
   * Get default rep code from LocationManager
   *
   * @return string Default rep code
   * @since 1.0.0
   */
  private function get_default_repcode(): string {
    $default_location = LocationManager::create()->get_default_location();
    return $default_location['rep_code'] ?? self::DEFAULT_REPCODE;
  }

  /**
   * Get location code from order meta
   *
   * @param \WC_Order $order WooCommerce order object
   * @return string Location code
   * @since 1.0.0
   */
  private function get_order_locno( \WC_Order $order ): string {
    $location_code = $order->get_meta( self::META_LOCATION_CODE );
    if ( ! empty( $location_code ) ) {
      $location = LocationManager::create()->get_location( $location_code );
      if ( $location ) {
        return $location['code'];
      }
    }
    return $this->get_default_locno();
  }

  /**
   * Get rep code from order meta
   *
   * @param \WC_Order $order WooCommerce order object
   * @return string Rep code
   * @since 1.0.0
   */
  private function get_order_repcode( \WC_Order $order ): string {
    $location_code = $order->get_meta( self::META_LOCATION_CODE );
    if ( ! empty( $location_code ) ) {
      $location = LocationManager::create()->get_location( $location_code );
      if ( $location && ! empty( $location['rep_code'] ) ) {
        return $location['rep_code'];
      }
    }
    return $this->get_default_repcode();
  }

  

  /**
   * Sync order when payment is completed
   *
   * @param int $order_id WooCommerce order ID
   * @return void
   * @since 1.0.0
   */
  public static function sync_order_on_payment_complete( int $order_id ): void {
    Logger::debug( 'sync_order_on_payment_complete called', [
      'order_id' => $order_id,
      'hook' => 'woocommerce_payment_complete/woocommerce_order_status_complete',
      'backtrace' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ),
    ] );
    
    // Check if completed status sync is enabled
    $options = self::get_options();
    if ( empty( $options['order_sync_status_completed'] ) ) {
      Logger::debug( 'Completed status sync disabled in settings, skipping', [
        'order_id' => $order_id,
        'order_sync_status_completed' => $options['order_sync_status_completed'] ?? 'not set',
      ] );
      return;
    }
    
    $order_sync = self::create();
    $result = $order_sync->sync_order( $order_id );

    if ( is_wp_error( $result ) )
      Logger::error( 'Order sync failed on payment complete', [
        'order_id' => $order_id,
        'error' => $result->get_error_message(),
        'error_code' => $result->get_error_code(),
        'error_data' => $result->get_error_data(),
      ] );
  }

  /**
   * Sync order when status changes to processing
   *
   * @param int $order_id WooCommerce order ID
   * @return void
   * @since 1.0.0
   */
  public static function sync_order_on_status_processing( int $order_id ): void {
    Logger::debug( 'sync_order_on_status_processing called', [
      'order_id' => $order_id,
      'hook' => 'woocommerce_order_status_processing',
      'backtrace' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ),
    ] );
    
    // Check if processing status sync is enabled
    $options = self::get_options();
    if ( empty( $options['order_sync_status_processing'] ) ) {
      Logger::debug( 'Processing status sync disabled in settings, skipping', [
        'order_id' => $order_id,
        'order_sync_status_processing' => $options['order_sync_status_processing'] ?? 'not set',
      ] );
      return;
    }
    
    $order = wc_get_order( $order_id );
    
    // Only sync if not already synced and payment method is not COD
    if ( ! $order || $order->get_meta( self::META_SYNCED ) ) {
      Logger::debug( 'Order already synced or not found, skipping sync', [
        'order_id' => $order_id,
        'order_exists' => (bool) $order,
        'already_synced' => $order ? $order->get_meta( self::META_SYNCED ) : 'no order',
      ] );
      return;
    }

    $payment_method = $order->get_payment_method();
    
    // For COD orders, wait for payment_complete hook instead
    if ( $payment_method === 'cod' ) {
      Logger::debug( 'COD payment method, skipping sync until payment complete', [
        'order_id' => $order_id,
        'payment_method' => $payment_method,
      ] );
      return;
    }

    $order_sync = self::create();
    $result = $order_sync->sync_order( $order_id );

    if ( is_wp_error( $result ) )
      Logger::error( 'Order sync failed on status processing', [
        'order_id' => $order_id,
        'error' => $result->get_error_message(),
        'error_code' => $result->get_error_code(),
        'error_data' => $result->get_error_data(),
      ] );
  }

  /**
   * Main order sync method
   *
   * @param int $order_id WooCommerce order ID
   * @return array|\WP_Error Sync result or WP_Error on failure
   * @since 1.0.0
   */
  public function sync_order( int $order_id ): array|\WP_Error {
    $order = wc_get_order( $order_id );
    
    // Get price list information for logging
    $price_list = $order ? $order->get_meta( self::META_PRICE_LIST ) : null;
    $price_list_label = $order ? $order->get_meta( self::META_PRICE_LIST_LABEL ) : null;
    $customer_type = $order ? $order->get_meta( self::META_CUSTOMER_TYPE ) : null;
    
    Logger::info( 'Starting order sync', [
      'order_id' => $order_id,
      'price_list' => $price_list,
      'price_list_label' => $price_list_label,
      'customer_type' => $customer_type,
      'order_exists' => (bool) $order,
    ] );
    
    if ( ! $order )
      return new \WP_Error( 'invalid_order', sprintf( 'Order %d not found', $order_id ) );

    // Check if already synced
    if ( $order->get_meta( self::META_SYNCED ) ) {
      Logger::info( 'Order already synced, skipping', [
        'order_id' => $order_id,
        'price_list' => $price_list,
        'customer_type' => $customer_type,
      ] );
      return new \WP_Error( 'already_synced', sprintf( 'Order %d already synced to Fincon', $order_id ) );
    }
    
    // Increment retry count if there was a previous error
    $previous_error = $order->get_meta( self::META_SYNC_ERROR );
    if ( ! empty( $previous_error ) ) {
      $retry_count = (int) $order->get_meta( self::META_SYNC_RETRY_COUNT );
      $retry_count++;
      $order->update_meta_data( self::META_SYNC_RETRY_COUNT, $retry_count );
      Logger::info( 'Retrying failed order sync', [
        'order_id' => $order_id,
        'retry_attempt' => $retry_count,
        'previous_error' => $previous_error,
      ] );

      // Check if retry limit exceeded
      if ( $retry_count > self::MAX_SYNC_RETRIES ) {
        $error_message = sprintf( __( 'Maximum retry attempts (%d) reached. Please contact support.', 'df-fincon' ), self::MAX_SYNC_RETRIES );
        $order->update_meta_data( self::META_SYNC_ERROR, $error_message );
        $order->save();
        return new \WP_Error( 'max_retries_exceeded', $error_message );
      }
    }

    // Clear any previous error meta before new attempt
    $order->delete_meta_data( self::META_SYNC_ERROR );
    
    // Prepare order data for Fincon API
    $order_data = $this->prepare_order_data( $order );
    
    if ( is_wp_error( $order_data ) ) {
      Logger::error( 'Failed to prepare order data', [
        'order_id' => $order_id,
        'error' => $order_data->get_error_message(),
        'error_code' => $order_data->get_error_code(),
        'price_list' => $price_list,
      ] );
      return $order_data;
    }

    // Log dealer pricing information if applicable
    if ( ! empty( $price_list ) && $price_list > CustomerSync::PRICE_LIST_RETAIL ) {
      Logger::info( 'Syncing dealer order with price list', [
        'order_id' => $order_id,
        'price_list' => $price_list,
        'price_list_label' => $price_list_label,
        'customer_type' => $customer_type,
        'item_count' => count( $order_data['SalesOrderDetail'] ?? [] ),
        'has_pricing_summary' => isset( $order_data['_pricing_summary'] ),
      ] );
    }

    // Call Fincon API
    Logger::info( 'Calling Fincon API to create sales order', [
      'order_id' => $order_id,
      'price_list' => $price_list,
      'prepared_data_keys' => array_keys( $order_data ),
      'item_count' => count( $order_data['SalesOrderDetail'] ?? [] ),
      'is_dealer_order' => ! empty( $price_list ) && $price_list > CustomerSync::PRICE_LIST_RETAIL,
    ] );
    
    $api_response = $this->call_fincon_api( $order_data );
    Logger::info( 'Fincon API response received', [
      'order_id' => $order_id,
      'price_list' => $price_list,
      'is_wp_error' => is_wp_error( $api_response ),
      'error' => is_wp_error( $api_response ) ? $api_response->get_error_message() : 'None',
      'response_structure' => is_wp_error( $api_response ) ? [] : array_keys( $api_response ),
    ] );
    
    if ( is_wp_error( $api_response ) ) {
      Logger::error( 'Fincon API call failed', [
        'order_id' => $order_id,
        'price_list' => $price_list,
        'error' => $api_response->get_error_message(),
        'error_code' => $api_response->get_error_code(),
        'customer_type' => $customer_type,
      ] );
      $this->update_order_meta_on_error( $order, $api_response );
      return $api_response;
    }

    // Update order meta with successful response
    Logger::info( 'Updating order meta with successful sync response', [
      'order_id' => $order_id,
      'price_list' => $price_list,
      'fincon_order_no' => $api_response['SalesOrderInfo']['OrderNo'] ?? 'N/A',
      'fincon_receipt_no' => $api_response['SalesOrderPayment']['ReceiptNo'] ?? 'N/A',
      'customer_type' => $customer_type,
    ] );
    
    $this->update_order_meta_on_success( $order, $api_response );

    Logger::info( 'Order successfully synced to Fincon', [
      'order_id' => $order_id,
      'price_list' => $price_list,
      'price_list_label' => $price_list_label,
      'customer_type' => $customer_type,
      'fincon_order_no' => $api_response['SalesOrderInfo']['OrderNo'] ?? 'N/A',
      'fincon_receipt_no' => $api_response['SalesOrderPayment']['ReceiptNo'] ?? 'N/A',
      'sync_timestamp' => current_time( 'mysql' ),
      'is_dealer_order' => ! empty( $price_list ) && $price_list > CustomerSync::PRICE_LIST_RETAIL,
    ] );

    return $api_response;
  }

  /**
   * Prepare WooCommerce order data for Fincon API
   *
   * @param \WC_Order $order WooCommerce order object
   * @return array|\WP_Error Prepared data array or WP_Error
   * @since 1.0.0
   */
  private function prepare_order_data( \WC_Order $order ): array|\WP_Error {
    // Get order sync options
    $options = self::get_options();
    
    // Check if order sync is enabled
    if ( empty( $options['order_sync_enabled'] ) )
      return new \WP_Error( 'order_sync_disabled', 'Order sync is disabled in plugin settings.' );

    // Get customer AccNo from user meta
    $customer_id = $order->get_customer_id();
    $acc_no = $customer_id ? get_user_meta( $customer_id, CustomerSync::META_ACCNO, true ) : '';

    // If no AccNo, use B2C debt account for guests/retail customers
    if ( empty( $acc_no ) ) {
      $b2c_account = $options['b2c_debt_account'] ?? '';
      
      if ( empty( $b2c_account ) )
        return new \WP_Error( 'missing_b2c_account', sprintf(
          'Customer %s does not have Fincon account number and no B2C debt account is configured.',
          $customer_id ?: 'Guest'
        ) );
      
      $acc_no = $b2c_account;
    }

    // Get price list from order meta
    $price_list = $order->get_meta( self::META_PRICE_LIST );
    $price_list_label = $order->get_meta( self::META_PRICE_LIST_LABEL );
    $customer_type = $order->get_meta( self::META_CUSTOMER_TYPE );
    
    // Log price list information
    Logger::info( 'Preparing order data with price list', [
      'order_id' => $order->get_id(),
      'price_list' => $price_list,
      'price_list_label' => $price_list_label,
      'customer_type' => $customer_type,
      'customer_id' => $customer_id,
      'acc_no' => $acc_no,
    ] );

    // Format date as CCYYMMDD
    $order_date = $order->get_date_created();
    $date_required = $order_date ? $order_date->format( 'Ymd' ) : date( 'Ymd' );

    // Prepare order header
    $order_data = [
      'AccNo' => $acc_no,
      'LocNo' => $this->get_order_locno( $order ),
      'OrderType' => 'N', // Always 'N' for Normal
      'OrderDate' => $date_required,
      'DateRequired' => $date_required,
      'CustomerRef' => sprintf( 'WC-%d', $order->get_id() ),
      'RepCode' => $this->get_order_repcode( $order ),
      'CurrencyCode' => self::DEFAULT_CURRENCY,
      'ExchangeRate' => self::DEFAULT_EXCHANGE_RATE,
      'DebName' => $order->get_billing_company() ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
      'Addr1' => $order->get_billing_address_1(),
      'Addr2' => $order->get_billing_address_2(),
      'Addr3' => $order->get_billing_city(),
      'PCode' => $order->get_billing_postcode(),
      'DelName' => $order->get_shipping_company() ?: $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
      'DelAddr1' => $order->get_shipping_address_1(),
      'DelAddr2' => $order->get_shipping_address_2(),
      'DelAddr3' => $order->get_shipping_city(),
      'DelAddr4' => $order->get_shipping_state(),
      'DelPCode' => $order->get_shipping_postcode(),
      'DelInstruc1' => $order->get_shipping_method(),
      'DeliveryMethod' => $this->map_delivery_method( $order->get_shipping_method() ),
      'TaxNo' => $order->get_meta( '_billing_vat_number' ) ?: '',
      'Approved' => 'Y', // SetAsApproved=true since payment is complete
    ];

    // Add price list information to order data for logging
    if ( ! empty( $price_list ) ) {
      $order_data['_price_list_info'] = [
        'price_list' => $price_list,
        'price_list_label' => $price_list_label,
        'customer_type' => $customer_type,
      ];
    }

    // Prepare order items
    $order_items = [];
    $total_line_total_excl = 0;
    $total_retail_value = 0;
    
    foreach ( $order->get_items() as $item ) {
      /** @var \WC_Order_Item_Product $item */
      $product = $item->get_product();
      
      if ( ! $product )
        continue;

      $sku = $product->get_sku();
      
      if ( empty( $sku ) ) {
        Logger::warning( 'Product missing SKU, skipping order item', [
          'order_id' => $order->get_id(),
          'product_id' => $product->get_id(),
          'product_name' => $product->get_name(),
        ] );
        continue;
      }

      // Calculate line total based on price list
      $line_total_excl = (float) $item->get_total();
      $item_quantity = (float) $item->get_quantity();
      
      // If dealer price list, verify the line total matches dealer pricing
      if ( ! empty( $price_list ) && $price_list > CustomerSync::PRICE_LIST_RETAIL ) {
        $dealer_price = Woo::get_price_for_price_list( $product, (int) $price_list );
        $promo_price = Woo::get_promotional_price_for_price_list( $product, (int) $price_list );
        $effective_price = $promo_price ?: $dealer_price;
        
        if ( $effective_price !== null ) {
          $calculated_line_total = $effective_price * $item_quantity;
          $retail_price = Woo::get_price_for_price_list( $product, CustomerSync::PRICE_LIST_RETAIL );
          
          // Log price comparison for debugging
          Logger::debug( 'Price list comparison for order item', [
            'order_id' => $order->get_id(),
            'product_id' => $product->get_id(),
            'sku' => $sku,
            'price_list' => $price_list,
            'item_quantity' => $item_quantity,
            'item_total' => $line_total_excl,
            'dealer_price' => $dealer_price,
            'promo_price' => $promo_price,
            'effective_price' => $effective_price,
            'calculated_line_total' => $calculated_line_total,
            'retail_price' => $retail_price,
            'difference' => abs( $line_total_excl - $calculated_line_total ),
          ] );
          
          // Update line total if there's a significant difference (more than 0.01 for rounding)
          if ( abs( $line_total_excl - $calculated_line_total ) > 0.01 ) {
            Logger::warning( 'Line total mismatch for dealer pricing', [
              'order_id' => $order->get_id(),
              'product_id' => $product->get_id(),
              'sku' => $sku,
              'original_line_total' => $line_total_excl,
              'calculated_line_total' => $calculated_line_total,
              'difference' => $line_total_excl - $calculated_line_total,
            ] );
            
            // Use the calculated line total for Fincon sync
            $line_total_excl = $calculated_line_total;
          }
          
          // Track retail value for reporting
          if ( $retail_price !== null ) {
            $total_retail_value += $retail_price * $item_quantity;
          }
        }
      }
      
      $total_line_total_excl += $line_total_excl;

      $order_items[] = [
        'ItemNo' => $sku,
        'Quantity' => $item_quantity,
        'LineTotalExcl' => $line_total_excl,
        'Description' => substr( $product->get_name(), 0, 40 ),
        'TaxCode' => self::DEFAULT_TAX_CODE, // TODO: Map from WC tax class
      ];
    }

    if ( empty( $order_items ) )
      return new \WP_Error( 'no_valid_items', 'Order contains no items with valid SKUs' );

    $order_data['SalesOrderDetail'] = $order_items;

    // Add informational line items for payment and shipping methods
    $payment_method_title = $order->get_payment_method_title();
    $shipping_method_labels = [];
    
    foreach ( $order->get_shipping_methods() as $shipping_method ) {
      $shipping_method_labels[] = $shipping_method->get_method_title();
    }
    
    $shipping_method_info = ! empty( $shipping_method_labels )
      ? implode( ', ', $shipping_method_labels )
      : __( 'No shipping method', 'df-fincon' );
    
     #EM-TODO 
    // // Add shipping method as informational line item
    // $order_data['SalesOrderDetail'][] = [
    //   'ItemNo' => '',
    //   'Quantity' => 1,
    //   'LineTotalExcl' => '0',
    //   'Description' => 'Shipping Method: ' . substr( sanitize_text_field( $shipping_method_info ), 0, 40 ),
    //   'TaxCode' => self::DEFAULT_TAX_CODE,
    // ];
    
    // // Add payment method as informational line item
    // $order_data['SalesOrderDetail'][] = [
    //   'ItemNo' => '',
    //   'Quantity' => 1,
    //   'LineTotalExcl' => '0',
    //   'Description' => 'Payment' . substr( sanitize_text_field( $payment_method_title ), 0, 40 ),
    //   'TaxCode' => self::DEFAULT_TAX_CODE,
    // ];

    // Add pricing summary to order data for logging
    if ( ! empty( $price_list ) && $price_list > CustomerSync::PRICE_LIST_RETAIL ) {
      $order_data['_pricing_summary'] = [
        'price_list' => $price_list,
        'total_line_total_excl' => $total_line_total_excl,
        'total_retail_value' => $total_retail_value,
        'savings' => $total_retail_value > 0 ? $total_retail_value - $total_line_total_excl : 0,
        'savings_percentage' => $total_retail_value > 0 ? ( ($total_retail_value - $total_line_total_excl) / $total_retail_value ) * 100 : 0,
      ];
      
      Logger::info( 'Dealer pricing summary for order', [
        'order_id' => $order->get_id(),
        'price_list' => $price_list,
        'total_line_total_excl' => $total_line_total_excl,
        'total_retail_value' => $total_retail_value,
        'savings' => $total_retail_value - $total_line_total_excl,
        'item_count' => count( $order_items ),
      ] );
    }

    // Prepare payment data
    $payment_data = $this->prepare_payment_data( $order );
    if ( $payment_data )
      $order_data['SalesOrderPayment'] = $payment_data;

    return $order_data;
  }

  /**
   * Prepare payment data for Fincon API
   *
   * @param \WC_Order $order WooCommerce order object
   * @return array|null Payment data array or null if not applicable
   * @since 1.0.0
   */
  private function prepare_payment_data( \WC_Order $order ): ?array {
    $payment_method = $order->get_payment_method();
    
    // Map payment method to Fincon PayType
    $pay_type = self::PAYMENT_METHOD_MAP[$payment_method] ?? 'C'; // Default to Card
    
    $payment_data = [
      'PayType' => $pay_type,
      'Amount' => (float) $order->get_total(),
    ];

    // Add card number for card payments
    if ( $pay_type === 'C' ) {
      $transaction_id = $order->get_transaction_id();
      $card_no = '**** **** **** ****';
      $payment_data['CardNo'] = $card_no;
    }

    // Get price list from order meta
    $price_list = $order->get_meta( self::META_PRICE_LIST );
    
    // Validate LineTotalExcl matches B2B pricing for dealer accounts
    if ( ! empty( $price_list ) && $price_list > CustomerSync::PRICE_LIST_RETAIL ) {
      $this->validate_dealer_pricing( $order, $price_list );
    }
    
    return $payment_data;
  }

  /**
   * Validate that dealer pricing matches B2B pricing for order items
   *
   * @param \WC_Order $order WooCommerce order object
   * @param int $price_list Price list number (2-6 for dealer)
   * @return void
   * @since 1.0.0
   */
  private function validate_dealer_pricing( \WC_Order $order, int $price_list ): void {
    $order_id = $order->get_id();
    $total_mismatch = 0;
    $mismatch_items = [];
    
    foreach ( $order->get_items() as $item ) {
      /** @var \WC_Order_Item_Product $item */
      $product = $item->get_product();
      
      if ( ! $product )
        continue;
      
      $item_quantity = (float) $item->get_quantity();
      $item_total = (float) $item->get_total();
      
      // Get dealer price for this price list
      $dealer_price = Woo::get_price_for_price_list( $product, $price_list );
      $promo_price = Woo::get_promotional_price_for_price_list( $product, $price_list );
      $effective_price = $promo_price ?: $dealer_price;
      
      if ( $effective_price === null ) {
        // Product doesn't have price for this price list
        Logger::warning( 'Product missing price for dealer price list', [
          'order_id' => $order_id,
          'product_id' => $product->get_id(),
          'sku' => $product->get_sku(),
          'price_list' => $price_list,
          'item_quantity' => $item_quantity,
          'item_total' => $item_total,
        ] );
        continue;
      }
      
      $calculated_total = $effective_price * $item_quantity;
      $difference = abs( $item_total - $calculated_total );
      
      // Check for significant difference (more than 0.01 for rounding)
      if ( $difference > 0.01 ) {
        $mismatch_items[] = [
          'product_id' => $product->get_id(),
          'sku' => $product->get_sku(),
          'item_quantity' => $item_quantity,
          'item_total' => $item_total,
          'calculated_total' => $calculated_total,
          'difference' => $item_total - $calculated_total,
          'effective_price' => $effective_price,
        ];
        
        $total_mismatch += $difference;
        
        Logger::warning( 'Dealer pricing mismatch detected', [
          'order_id' => $order_id,
          'product_id' => $product->get_id(),
          'sku' => $product->get_sku(),
          'price_list' => $price_list,
          'item_quantity' => $item_quantity,
          'item_total' => $item_total,
          'calculated_total' => $calculated_total,
          'difference' => $item_total - $calculated_total,
          'effective_price' => $effective_price,
        ] );
      }
    }
    
    // Log summary of pricing validation
    if ( ! empty( $mismatch_items ) ) {
      Logger::warning( 'Dealer pricing validation found mismatches', [
        'order_id' => $order_id,
        'price_list' => $price_list,
        'total_mismatch' => $total_mismatch,
        'mismatch_item_count' => count( $mismatch_items ),
        'mismatch_items' => $mismatch_items,
      ] );
      
      // Add order note about pricing validation
      $note = sprintf(
        __( 'Dealer pricing validation: Found %d item(s) with pricing mismatches totaling %s.', 'df-fincon' ),
        count( $mismatch_items ),
        wc_price( $total_mismatch )
      );
      
      $order->add_order_note( $note, false, false );
    } else {
      Logger::info( 'Dealer pricing validation passed', [
        'order_id' => $order_id,
        'price_list' => $price_list,
        'item_count' => count( $order->get_items() ),
      ] );
    }
  }

  /**
   * Map WooCommerce shipping method to Fincon DeliveryMethod
   *
   * @param string $shipping_method WooCommerce shipping method
   * @return string Fincon DeliveryMethod code
   * @since 1.0.0
   */
  private function map_delivery_method( string $shipping_method ): string {
    $shipping_lower = strtolower( $shipping_method );
    
    if ( str_contains( $shipping_lower, 'collect' ) )
      return 'C'; // Collect
    
    if ( str_contains( $shipping_lower, 'freight' ) || str_contains( $shipping_lower, 'courier' ) )
      return 'R'; // Road Freight
    
    return 'D'; // Deliver (default)
  }

  /**
   * Call Fincon API to create sales order
   *
   * @param array $order_data Prepared order data
   * @return array|\WP_Error API response or WP_Error
   * @since 1.0.0
   */
  private function call_fincon_api( array $order_data ): array|\WP_Error {
    $fincon_api = new FinconApi();
    
    // Set SetAsApproved=true since payment is complete
    $api_response = $fincon_api->create_sales_order( $order_data, true );
    
    if ( is_wp_error( $api_response ) )
      return $api_response;

    // Validate response structure
    if ( empty( $api_response['SalesOrderInfo']['OrderNo'] ) )
      return new \WP_Error( 'invalid_api_response', 'Fincon API returned invalid response structure' );

    return $api_response;
  }

  /**
   * Update order meta on successful sync
   *
   * @param \WC_Order $order WooCommerce order object
   * @param array $api_response Fincon API response
   * @return void
   * @since 1.0.0
   */
  private function update_order_meta_on_success( \WC_Order $order, array $api_response ): void {
    Logger::debug( 'Starting update_order_meta_on_success', [
      'order_id' => $order->get_id(),
      'has_salesorderinfo' => isset( $api_response['SalesOrderInfo'] ),
      'has_salesorderpayment' => isset( $api_response['SalesOrderPayment'] ),
    ] );
    
    $order->update_meta_data( self::META_SYNCED, true );
    $order->update_meta_data( self::META_SYNC_TIMESTAMP, current_time( 'mysql' ) );
    
    $fincon_order_no = $api_response['SalesOrderInfo']['OrderNo'] ?? '';
    $fincon_receipt_no = $api_response['SalesOrderPayment']['ReceiptNo'] ?? '';
    
    // Extract invoice numbers from SalesOrderInfo.InvoiceNumbers (comma-separated string)
    $invoice_numbers = $api_response['SalesOrderInfo']['InvoiceNumbers'] ?? '';
    $invoice_numbers = trim( $invoice_numbers );
    
    // Extract DocNo from SalesOrderDetail (invoice document number) for backward compatibility
    $fincon_doc_no = '';
    if ( ! empty( $api_response['SalesOrderInfo']['SalesOrderDetail'] ) && is_array( $api_response['SalesOrderInfo']['SalesOrderDetail'] ) ) {
      $first_detail = reset( $api_response['SalesOrderInfo']['SalesOrderDetail'] );
      $fincon_doc_no = $first_detail['DocNo'] ?? '';
    }
    
    Logger::debug( 'Extracted Fincon details', [
      'order_id' => $order->get_id(),
      'fincon_order_no' => $fincon_order_no,
      'fincon_receipt_no' => $fincon_receipt_no,
      'fincon_doc_no' => $fincon_doc_no,
      'invoice_numbers' => $invoice_numbers,
      'meta_key_order_no' => self::META_ORDER_NO,
      'meta_key_receipt_no' => self::META_RECEIPT_NO,
      'meta_key_doc_no' => self::META_INVOICE_DOCNO,
      'meta_key_invoice_numbers' => self::META_INVOICE_NUMBERS,
    ] );
    
    if ( ! empty( $fincon_order_no ) )
      $order->update_meta_data( self::META_ORDER_NO, $fincon_order_no );
    
    if ( ! empty( $fincon_receipt_no ) )
      $order->update_meta_data( self::META_RECEIPT_NO, $fincon_receipt_no );
    
    // Store invoice numbers if available
    if ( ! empty( $invoice_numbers ) ) {
      $order->update_meta_data( self::META_INVOICE_NUMBERS, $invoice_numbers );
      
      // Determine initial invoice status
      $invoice_numbers_array = array_map( 'trim', explode( ',', $invoice_numbers ) );
      if ( count( $invoice_numbers_array ) > 1 ) {
        $invoice_status = 'multiple';
      } else {
        $invoice_status = 'available';
      }
      
      $order->update_meta_data( self::META_INVOICE_STATUS, $invoice_status );
      $order->update_meta_data( self::META_PDF_AVAILABLE, true );
    } else {
      // No invoice numbers yet - set as pending
      $order->update_meta_data( self::META_INVOICE_STATUS, 'pending' );
      $order->update_meta_data( self::META_PDF_AVAILABLE, false );
    }
    
    // Store DocNo for backward compatibility
    if ( ! empty( $fincon_doc_no ) )
      $order->update_meta_data( self::META_INVOICE_DOCNO, $fincon_doc_no );
    
    // Initialize invoice check tracking
    $order->update_meta_data( self::META_INVOICE_CHECK_ATTEMPTS, 0 );
    $order->update_meta_data( self::META_INVOICE_LAST_CHECK, current_time( 'mysql' ) );
    
    $order->update_meta_data( self::META_SYNC_RESPONSE, wp_json_encode( $api_response ) );
    
    // Clear any previous error
    $order->delete_meta_data( self::META_SYNC_ERROR );
    
    // Reset retry count on successful sync
    $order->update_meta_data( self::META_SYNC_RETRY_COUNT, 0 );
    
    // Save the order to persist meta data
    $order_id = $order->save();
    
    Logger::debug( 'Order saved after meta updates', [
      'order_id' => $order->get_id(),
      'save_returned' => $order_id,
      'invoice_numbers' => $invoice_numbers,
      'invoice_status' => $order->get_meta( self::META_INVOICE_STATUS ),
    ] );
    
    // Reload order to verify save (optional)
    $reloaded_order = wc_get_order( $order->get_id() );
    if ( $reloaded_order ) {
      $verified_synced = $reloaded_order->get_meta( self::META_SYNCED );
      $verified_order_no = $reloaded_order->get_meta( self::META_ORDER_NO );
      $verified_invoice_status = $reloaded_order->get_meta( self::META_INVOICE_STATUS );
      
      Logger::debug( 'Order meta verified after reload', [
        'order_id' => $order->get_id(),
        'meta_synced' => $verified_synced,
        'meta_order_no' => $verified_order_no,
        'meta_invoice_status' => $verified_invoice_status,
        'meta_synced_type' => gettype( $verified_synced ),
        'meta_order_no_type' => gettype( $verified_order_no ),
      ] );
    }
    
    // Add order note with Fincon details
    $this->add_sync_success_order_note( $order, $fincon_order_no, $fincon_receipt_no, $invoice_numbers );
    
    // DO NOT fetch PDF immediately - invoices are not available until manually approved in Fincon
    // PDF fetching will be handled by the InvoiceChecker cron job
    Logger::debug( 'Skipping immediate PDF fetch - invoices require manual approval in Fincon', [
      'order_id' => $order->get_id(),
      'invoice_numbers' => $invoice_numbers,
      'invoice_status' => $order->get_meta( self::META_INVOICE_STATUS ),
    ] );
  }

  /**
   * Add order note for successful sync
   *
   * @param \WC_Order $order WooCommerce order object
   * @param string $fincon_order_no Fincon order number
   * @param string $fincon_receipt_no Fincon receipt number
   * @param string $invoice_numbers Fincon invoice numbers (comma-separated)
   * @return void
   * @since 1.0.0
   */
  private function add_sync_success_order_note( \WC_Order $order, string $fincon_order_no, string $fincon_receipt_no, string $invoice_numbers = '' ): void {
    Logger::debug( 'Adding sync success order note', [
      'order_id' => $order->get_id(),
      'fincon_order_no' => $fincon_order_no,
      'fincon_receipt_no' => $fincon_receipt_no,
      'invoice_numbers' => $invoice_numbers,
    ] );
    
    $note_parts = [];
    
    $note_parts[] = __( 'Order synced to Fincon.', 'df-fincon' );
    
    // Add payment and shipping method information
    $payment_method_title = $order->get_payment_method_title();
    $shipping_method_labels = [];
    
    foreach ( $order->get_shipping_methods() as $shipping_method ) {
      $shipping_method_labels[] = $shipping_method->get_method_title();
    }
    
    $shipping_method_info = ! empty( $shipping_method_labels )
      ? implode( ', ', $shipping_method_labels )
      : __( 'No shipping method', 'df-fincon' );
    
    $note_parts[] = sprintf( __( 'Payment method: %s', 'df-fincon' ), $payment_method_title );
    $note_parts[] = sprintf( __( 'Shipping method: %s', 'df-fincon' ), $shipping_method_info );
    
    if ( ! empty( $fincon_order_no ) )
      $note_parts[] = sprintf( __( 'Fincon Order No: %s', 'df-fincon' ), $fincon_order_no );
    
    if ( ! empty( $fincon_receipt_no ) )
      $note_parts[] = sprintf( __( 'Fincon Receipt No: %s', 'df-fincon' ), $fincon_receipt_no );
    
    if ( ! empty( $invoice_numbers ) ) {
      $invoice_numbers_array = array_map( 'trim', explode( ',', $invoice_numbers ) );
      if ( count( $invoice_numbers_array ) > 1 ) {
        $note_parts[] = sprintf( __( 'Fincon Invoice Numbers: %s (multiple invoices)', 'df-fincon' ), $invoice_numbers );
      } else {
        $note_parts[] = sprintf( __( 'Fincon Invoice No: %s', 'df-fincon' ), $invoice_numbers );
      }
    } else {
      $note_parts[] = __( 'Invoice pending - will be available after manual approval in Fincon.', 'df-fincon' );
    }
    
    $note = implode( "\n", $note_parts );
    
    Logger::debug( 'Order note content', [
      'order_id' => $order->get_id(),
      'note' => $note,
    ] );
    
    $note_id = $order->add_order_note( $note, false, false ); // false = not customer note, false = added by system
    
    Logger::debug( 'Order note added', [
      'order_id' => $order->get_id(),
      'note_id' => $note_id,
      'has_note_id' => ! empty( $note_id ),
    ] );
  }

  /**
   * Update order meta on sync error
   *
   * @param \WC_Order $order WooCommerce order object
   * @param \WP_Error $error Error object
   * @return void
   * @since 1.0.0
   */
  private function update_order_meta_on_error( \WC_Order $order, \WP_Error $error ): void {
    Logger::debug( 'Updating order meta on error', [
      'order_id' => $order->get_id(),
      'error' => $error->get_error_message(),
    ] );
    
    $order->update_meta_data( self::META_SYNC_ERROR, $error->get_error_message() );
    $order->update_meta_data( self::META_SYNC_TIMESTAMP, current_time( 'mysql' ) );
    $order->save();
    
    Logger::debug( 'Order saved after error meta update', [
      'order_id' => $order->get_id(),
    ] );
    
    // Add error order note for visibility
    $this->add_sync_error_order_note( $order, $error );
  }

  /**
   * Add order note for sync error
   *
   * @param \WC_Order $order WooCommerce order object
   * @param \WP_Error $error Error object
   * @return void
   * @since 1.0.0
   */
  private function add_sync_error_order_note( \WC_Order $order, \WP_Error $error ): void {
    $note = sprintf(
      __( 'Fincon sync failed: %s', 'df-fincon' ),
      $error->get_error_message()
    );
    
    $order->add_order_note( $note, false, false );
  }

  /**
   * Fetch and save PDF document from Fincon
   *
   * @param \WC_Order $order WooCommerce order object
   * @param string $doc_no Fincon document number
   * @return void
   * @since 1.0.0
   */
  private function fetch_and_save_pdf( \WC_Order $order, string $doc_no ): void {
    try {
      $pdf_storage = PdfStorage::create();
      $result = $pdf_storage->fetch_and_save_pdf( $order->get_id(), 'I', $doc_no );
      
      if ( is_wp_error( $result ) ) {
        Logger::warning( 'Failed to fetch PDF from Fincon', [
          'order_id' => $order->get_id(),
          'doc_no' => $doc_no,
          'error' => $result->get_error_message(),
        ] );
        
        // Add note about PDF fetch failure
        $order->add_order_note(
          sprintf( __( 'Fincon PDF download failed: %s', 'df-fincon' ), $result->get_error_message() ),
          false,
          false
        );
      } else {
        Logger::info( 'PDF successfully fetched and saved', [
          'order_id' => $order->get_id(),
          'doc_no' => $doc_no,
          'file_path' => $result['path'] ?? '',
          'file_url' => $result['url'] ?? '',
        ] );
        
        // Add note about PDF success
        $order->add_order_note(
          __( 'Fincon invoice PDF downloaded and saved.', 'df-fincon' ),
          false,
          false
        );
      }
    } catch ( \Exception $e ) {
      Logger::error( 'Exception while fetching PDF', [
        'order_id' => $order->get_id(),
        'doc_no' => $doc_no,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ] );
    }
  }

  



  /**
   * Display Fincon info meta box content
   *
   * @param mixed $post_or_order WordPress post object or WC_Order object
   * @return void
   * @since 1.0.0
   */
  public static function display_order_meta_box( $post_or_order ): void {    
    $order = null;
    
    // Handle both WP_Post and WC_Order objects
    if ( $post_or_order instanceof \WP_Post ) {
      $order = wc_get_order( $post_or_order->ID );

    } elseif ( $post_or_order instanceof \WC_Order ) {
      $order = $post_or_order;
    } else {

      echo '<p>' . esc_html__( 'Invalid order data.', 'df-fincon' ) . '</p>';
      return;
    }
    
    if ( ! $order ) {
      echo '<p>' . esc_html__( 'Order not found.', 'df-fincon' ) . '</p>';
      return;
    }

    $synced = $order->get_meta( self::META_SYNCED );
    $order_no = $order->get_meta( self::META_ORDER_NO );
    $receipt_no = $order->get_meta( self::META_RECEIPT_NO );
    $sync_timestamp = $order->get_meta( self::META_SYNC_TIMESTAMP );
    $sync_error = $order->get_meta( self::META_SYNC_ERROR );
    $sync_response = $order->get_meta( self::META_SYNC_RESPONSE );
    
    // Get price list information
    $price_list = $order->get_meta( self::META_PRICE_LIST );
    $price_list_label = $order->get_meta( self::META_PRICE_LIST_LABEL );
    $customer_type = $order->get_meta( self::META_CUSTOMER_TYPE );
    
    // If price list label is not stored, generate it
    if ( ! empty( $price_list ) && empty( $price_list_label ) ) {
      $price_list_label = Woo::get_price_list_label( (int) $price_list );
      $order->update_meta_data( self::META_PRICE_LIST_LABEL, $price_list_label );
      $order->save();
    }
    
    // If customer type is not stored, determine it from price list
    if ( ! empty( $price_list ) && empty( $customer_type ) ) {
      $customer_type = (int) $price_list > CustomerSync::PRICE_LIST_RETAIL ? 'dealer' : 'retail';
      $order->update_meta_data( self::META_CUSTOMER_TYPE, $customer_type );
      $order->save();
    }
    
    Logger::debug( 'Retrieved order meta for Fincon box', [
      'order_id' => $order->get_id(),
      'synced' => $synced,
      'order_no' => $order_no,
      'receipt_no' => $receipt_no,
      'sync_timestamp' => $sync_timestamp,
      'sync_error' => $sync_error,
      'has_sync_response' => ! empty( $sync_response ),
      'price_list' => $price_list,
      'price_list_label' => $price_list_label,
      'customer_type' => $customer_type,
    ] );
    
    ?>
    <div class="fincon-order-info" style="border: 2px solid #0073aa; background: #f0f8ff; padding: 10px;">
      <!-- DEBUG: Meta box rendered for order <?php echo (int) $order->get_id(); ?> -->
      <h4 style="margin-top: 0; color: #0073aa;">Fincon Sync Information</h4>
      <table class="widefat">
        <tbody>
          <tr>
            <th><?php esc_html_e( 'Sync Status', 'df-fincon' ); ?></th>
            <td>
              <?php if ( $synced ) : ?>
                <span style="color: green;">✓ <?php esc_html_e( 'Synced', 'df-fincon' ); ?></span>
              <?php elseif ( $sync_error ) : ?>
                <span style="color: red;">✗ <?php esc_html_e( 'Failed', 'df-fincon' ); ?></span>
              <?php else : ?>
                <span style="color: gray;">○ <?php esc_html_e( 'Not Synced', 'df-fincon' ); ?></span>
              <?php endif; ?>
            </td>
          </tr>
          
          <?php if ( $order_no ) : ?>
          <tr>
            <th><?php esc_html_e( 'Fincon Order No', 'df-fincon' ); ?></th>
            <td><code><?php echo esc_html( $order_no ); ?></code></td>
          </tr>
          <?php endif; ?>
          
          <?php if ( $receipt_no ) : ?>
          <tr>
            <th><?php esc_html_e( 'Fincon Receipt No', 'df-fincon' ); ?></th>
            <td><code><?php echo esc_html( $receipt_no ); ?></code></td>
          </tr>
          <?php endif; ?>
          
          <?php if ( $sync_timestamp ) : ?>
          <tr>
            <th><?php esc_html_e( 'Last Sync', 'df-fincon' ); ?></th>
            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sync_timestamp ) ) ); ?></td>
          </tr>
          <?php endif; ?>
          
          <?php if ( $sync_error ) : ?>
          <tr>
            <th><?php esc_html_e( 'Error', 'df-fincon' ); ?></th>
            <td style="color: red;"><code><?php echo esc_html( $sync_error ); ?></code></td>
          </tr>
          <?php endif; ?>
          
          <?php if ( $sync_response ) : ?>
          <tr>
            <th><?php esc_html_e( 'API Response', 'df-fincon' ); ?></th>
            <td>
              <details>
                <summary><?php esc_html_e( 'View JSON', 'df-fincon' ); ?></summary>
                <pre style="max-height: 200px; overflow: auto; background: #f5f5f5; padding: 10px; font-size: 11px;"><?php
                  echo esc_html( json_encode( json_decode( $sync_response ), JSON_PRETTY_PRINT ) );
                ?></pre>
              </details>
            </td>
          </tr>
          <?php endif; ?>
           
          <?php
          // Get invoice tracking information
          $invoice_numbers = $order->get_meta( self::META_INVOICE_NUMBERS );
          $invoice_status = $order->get_meta( self::META_INVOICE_STATUS );
          $invoice_last_check = $order->get_meta( self::META_INVOICE_LAST_CHECK );
          $invoice_check_attempts = $order->get_meta( self::META_INVOICE_CHECK_ATTEMPTS );
          $pdf_available = $order->get_meta( self::META_PDF_AVAILABLE );
          $pdf_paths = $order->get_meta( self::META_PDF_PATHS );
          
          // Only show invoice section if order is synced
          if ( $synced ) :
          ?>
          <tr>
            <th><?php esc_html_e( 'Invoice Status', 'df-fincon' ); ?></th>
            <td>
              <?php
              $status_colors = [
                'pending' => '#ff9800',    // Orange
                'available' => '#4caf50',  // Green
                'downloaded' => '#2196f3', // Blue
                'error' => '#f44336',      // Red
                'multiple' => '#9c27b0',   // Purple
              ];
              
              $status_labels = [
                'pending' => __( 'Pending', 'df-fincon' ),
                'available' => __( 'Available', 'df-fincon' ),
                'downloaded' => __( 'Downloaded', 'df-fincon' ),
                'error' => __( 'Error', 'df-fincon' ),
                'multiple' => __( 'Multiple', 'df-fincon' ),
              ];
              
              $status_color = $status_colors[$invoice_status] ?? '#757575';
              $status_label = $status_labels[$invoice_status] ?? ucfirst( $invoice_status );
              ?>
              <span class="fincon-invoice-status-badge" style="
                display: inline-block;
                padding: 3px 8px;
                border-radius: 12px;
                background-color: <?php echo esc_attr( $status_color ); ?>;
                color: white;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
              ">
                <?php echo esc_html( $status_label ); ?>
              </span>
              
              <?php if ( $invoice_check_attempts > 0 ) : ?>
              <span class="description" style="margin-left: 10px; font-size: 12px;">
                <?php echo esc_html( sprintf( __( 'Checked %d time(s)', 'df-fincon' ), $invoice_check_attempts ) ); ?>
              </span>
              <?php endif; ?>
            </td>
          </tr>
          
          <?php if ( ! empty( $invoice_numbers ) ) : ?>
          <tr>
            <th><?php esc_html_e( 'Invoice Numbers', 'df-fincon' ); ?></th>
            <td>
              <code><?php echo esc_html( $invoice_numbers ); ?></code>
              <?php if ( strpos( $invoice_numbers, ',' ) !== false ) : ?>
              <span class="description" style="margin-left: 10px; font-size: 12px;">
                <?php esc_html_e( '(multiple invoices)', 'df-fincon' ); ?>
              </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          
          <?php if ( $invoice_last_check ) : ?>
          <tr>
            <th><?php esc_html_e( 'Last Check', 'df-fincon' ); ?></th>
            <td>
              <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $invoice_last_check ) ) ); ?>
            </td>
          </tr>
          <?php endif; ?>
          
          <?php if ( $pdf_available ) : ?>
          <tr>
            <th><?php esc_html_e( 'PDF Status', 'df-fincon' ); ?></th>
            <td>
              <span style="color: #4caf50;">✓ <?php esc_html_e( 'Available', 'df-fincon' ); ?></span>
            </td>
          </tr>
          <?php endif; ?>
          <?php endif; // End if synced ?>
        </tbody>
      </table>
      
      <?php
      // Get PDF information - enhanced for multiple PDFs
      $pdf_path = $order->get_meta( self::META_PDF_PATH );
      $invoice_docno = $order->get_meta( self::META_INVOICE_NUMBERS );
      $has_pdf = ! empty( $pdf_path ) && file_exists( $pdf_path );
      $has_docno = ! empty( $invoice_docno );
      
      // Check for multiple PDFs
      $pdf_paths = $order->get_meta( self::META_PDF_PATHS );
      $has_multiple_pdfs = ! empty( $pdf_paths ) && is_array( $pdf_paths ) && count( $pdf_paths ) > 0;
      
      // Add PDF download section
      if ( $has_pdf || $has_docno || $has_multiple_pdfs ) :
      ?>
      <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
        <h4 style="margin-top: 0; color: #0073aa;"><?php esc_html_e( 'Fincon Invoice PDF', 'df-fincon' ); ?></h4>
        
        <?php if ( $invoice_numbers ) : ?>
        <p>
          <strong><?php esc_html_e( 'Invoice Numbers:', 'df-fincon' ); ?></strong>
          <code><?php echo esc_html( $invoice_numbers ); ?></code>
        </p>
        <?php endif; ?>
        
        <?php if ( $has_multiple_pdfs ) : ?>
        <p>
          <strong><?php esc_html_e( 'Multiple PDFs:', 'df-fincon' ); ?></strong>
          <ul style="margin: 5px 0; padding-left: 20px;">
            <?php foreach ( $pdf_paths as $index => $pdf_info ) : ?>
            <li>
              <?php
              $pdf_url = add_query_arg( [
                'action' => 'df_fincon_download_pdf',
                'order_id' => $order->get_id(),
                'invoice_index' => $index,
                'nonce' => wp_create_nonce( 'df_fincon_download_pdf_' . $order->get_id() ),
              ], admin_url( 'admin-ajax.php' ) );
              ?>
              <a href="<?php echo esc_url( $pdf_url ); ?>"
                 class="button button-small"
                 target="_blank"
                 style="text-decoration: none; margin-right: 10px;">
                <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 3px;"></span>
                <?php echo esc_html( sprintf( __( 'Invoice %d', 'df-fincon' ), $index + 1 ) ); ?>
              </a>
              <?php if ( ! empty( $pdf_info['invoice_no'] ) ) : ?>
              <code><?php echo esc_html( $pdf_info['invoice_no'] ); ?></code>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </p>
        <?php elseif ( $has_pdf ) : ?>
        <p>
          <?php
          // Get PDF download URL via Admin AJAX endpoint
          $pdf_url = add_query_arg( [
            'action' => 'df_fincon_download_pdf',
            'order_id' => $order->get_id(),
            'nonce' => wp_create_nonce( 'df_fincon_download_pdf_' . $order->get_id() ),
          ], admin_url( 'admin-ajax.php' ) );
          ?>
          <a href="<?php echo esc_url( $pdf_url ); ?>"
             class="button button-primary"
             target="_blank"
             style="text-decoration: none;">
            <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
            <?php esc_html_e( 'Download Invoice PDF', 'df-fincon' ); ?>
          </a>
          <span class="description" style="margin-left: 10px;">
            <?php esc_html_e( 'Download the Fincon invoice as PDF', 'df-fincon' ); ?>
          </span>
        </p>
        <?php elseif ( $has_docno ) : ?>
        <p>
          <button type="button"
                  class="button button-secondary"
                  id="fincon-fetch-pdf"
                  data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                  data-doc-no="<?php echo esc_attr( $invoice_docno ); ?>">
            <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
            <?php esc_html_e( 'Fetch Invoice PDF', 'df-fincon' ); ?>
          </button>
          <span class="spinner" style="float: none; margin-left: 5px;"></span>
          <span class="description" style="margin-left: 10px;">
            <?php esc_html_e( 'Download PDF from Fincon', 'df-fincon' ); ?>
          </span>
          <div id="fincon-pdf-result" style="margin-top: 10px; display: none;"></div>
        </p>
        <?php endif; ?>
        
        <?php if ( $synced ) : ?>
        <p style="margin-top: 15px;">
          <button type="button"
                  class="button button-secondary"
                  id="fincon-check-invoice"
                  data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                  data-order-no="<?php echo esc_attr( $order_no ); ?>">
            <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
            <?php esc_html_e( 'Check Invoice Status', 'df-fincon' ); ?>
          </button>
          <span class="spinner" style="float: none; margin-left: 5px;"></span>
          <span class="description" style="margin-left: 10px;">
            <?php esc_html_e( 'Manually check if invoice is available in Fincon', 'df-fincon' ); ?>
          </span>
          <div id="fincon-invoice-check-result" style="margin-top: 10px; display: none;"></div>
        </p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      
      <?php // Show sync button for any unsynced order (including failed syncs) ?>
      <?php if ( ! $synced ) : ?>
      <?php
        $retry_count = (int) $order->get_meta( self::META_SYNC_RETRY_COUNT );
        $max_retries_exceeded = $sync_error && $retry_count > self::MAX_SYNC_RETRIES;
      ?>
      <p style="margin-top: 15px;">
        <button type="button" class="button button-secondary <?php echo $sync_error ? 'fincon-sync-retry' : ''; ?>"
                id="fincon-manual-sync"
                data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                <?php if ( $sync_error ) : ?>
                title="<?php echo esc_attr( sprintf( __( 'Previous error: %s (Retry attempt %d)', 'df-fincon' ), $sync_error, $retry_count ) ); ?>"
                <?php if ( $max_retries_exceeded ) : ?> disabled <?php endif; ?>
                <?php endif; ?>>
          <?php if ( $sync_error ) : ?>
          <span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 5px;"></span>
          <?php endif; ?>
          <?php echo $sync_error ? esc_html__( 'Retry Sync to Fincon', 'df-fincon' ) : esc_html__( 'Sync to Fincon Now', 'df-fincon' ); ?>
        </button>
        <span class="spinner" style="float: none; margin-left: 5px;"></span>
        <div id="fincon-sync-result" style="margin-top: 10px; display: none;"></div>
      </p>
      <?php endif; ?>
    </div>
    
    <style>
    .fincon-sync-retry {
      border-color: #dc3232 !important;
      color: #dc3232 !important;
    }
    .fincon-sync-retry:hover {
      border-color: #a00 !important;
      color: #a00 !important;
      background-color: #fff2f2;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
      $('#fincon-manual-sync').on('click', function() {
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $result = $('#fincon-sync-result');
        var orderId = $button.data('order-id');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide().empty();
        
        $.post(ajaxurl, {
          action: 'df_fincon_manual_sync_order',
          order_id: orderId,
          nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_manual_sync_order' ) ); ?>'
        }, function(response) {
          $spinner.removeClass('is-active');
          
          if (response.success) {
            $result.html('<div style="color: green;">' + response.data.message + '</div>').show();
            // Wait 2 seconds before reload to show success message
            setTimeout(function() {
              location.reload();
            }, 2000);
          } else {
            $result.html('<div style="color: red;">' + response.data.message + '</div>').show();
            $button.prop('disabled', false);
          }
        }).fail(function() {
          $spinner.removeClass('is-active');
          $result.html('<div style="color: red;"><?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?></div>').show();
          $button.prop('disabled', false);
        });
      });
      
      // PDF fetch functionality
      $('#fincon-fetch-pdf').on('click', function() {
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $result = $('#fincon-pdf-result');
        var orderId = $button.data('order-id');
        var docNo = $button.data('doc-no');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide().empty();
        
        $.post(ajaxurl, {
          action: 'df_fincon_fetch_pdf',
          order_id: orderId,
          doc_no: docNo,
          nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_fetch_pdf' ) ); ?>'
        }, function(response) {
          $spinner.removeClass('is-active');
          
          if (response.success) {
            $result.html('<div style="color: green;">' + response.data.message + '</div>').show();
            // Reload after a short delay to show the download button
            setTimeout(function() {
              location.reload();
            }, 1500);
          } else {
            $result.html('<div style="color: red;">' + response.data.message + '</div>').show();
            $button.prop('disabled', false);
          }
        }).fail(function() {
          $spinner.removeClass('is-active');
          $result.html('<div style="color: red;"><?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?></div>').show();
          $button.prop('disabled', false);
        });
      });
      
      // Invoice check functionality
      $('#fincon-check-invoice').on('click', function() {
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $result = $('#fincon-invoice-check-result');
        var orderId = $button.data('order-id');
        var orderNo = $button.data('order-no');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide().empty();
        
        $.post(ajaxurl, {
          action: 'df_fincon_check_invoice_status',
          order_id: orderId,
          order_no: orderNo,
          nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_check_invoice_status' ) ); ?>'
        }, function(response) {
          $spinner.removeClass('is-active');
          
          if (response.success) {
            var message = response.data.message;
            var status = response.data.status || '';
            var invoice_numbers = response.data.invoice_numbers || '';
            
            var html = '<div style="color: green;">' + message + '</div>';
            
            if (status) {
              html += '<div style="margin-top: 5px;"><strong>Status:</strong> ' + status + '</div>';
            }
            
            if (invoice_numbers) {
              html += '<div style="margin-top: 5px;"><strong>Invoice Numbers:</strong> ' + invoice_numbers + '</div>';
            }
            
            if (response.data.pdf_available) {
              html += '<div style="margin-top: 5px; color: #4caf50;">✓ PDF is now available for download</div>';
            }
            
            $result.html(html).show();
            
            // Reload after a short delay to show updated status
            setTimeout(function() {
              location.reload();
            }, 2000);
          } else {
            var errorMessage = response.data.message || '<?php esc_html_e( 'Unknown error occurred', 'df-fincon' ); ?>';
            $result.html('<div style="color: red;">' + errorMessage + '</div>').show();
            $button.prop('disabled', false);
          }
        }).fail(function() {
          $spinner.removeClass('is-active');
          $result.html('<div style="color: red;"><?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?></div>').show();
          $button.prop('disabled', false);
        });
      });
    });
    </script>
    <?php
  }

  /**
   * Get order sync options
   *
   * @return mixed
   * @since 1.0.0
   */
  public static function get_options( $option_key = '' ): mixed {
    $defaults = [
      'order_sync_enabled' => 0,
      'b2c_debt_account' => '',
      'order_sync_status_processing' => 0,
      'order_sync_status_completed' => 0,
    ];
    
    $options = get_option( self::OPTIONS_NAME, [] );
    $options = wp_parse_args( $options, $defaults );
    
    if ( ! empty( $option_key ) )
      if ( array_key_exists( $option_key, $options ) )
        return $options[$option_key];
      else
        return null;
    return $options;
  }

}