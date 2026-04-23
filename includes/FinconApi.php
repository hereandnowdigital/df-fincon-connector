<?php
  /**
   * Fincon API Client
   *
   * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
   * @package df-fincon-connector
   * Text Domain: df-fincon
   * */

  namespace DF_FINCON;
  use \WP_Error;

  // Exit if accessed directly.
  if ( ! defined( 'ABSPATH' ) )
    exit;

class FinconApi {

  /**
   * Options name
   * 
   * @const string
   */
  public const OPTIONS_NAME = Plugin::OPTIONS_NAME . '_API';

  /**
   * Fincon base path, appended to host and port
   * 
   * @var string
   */
  private const BASE_PATH = '/datasnap/rest/FinconAPI/';
  
  /**
   * Connect ID transient key
   * 
   * @const string
   */
  private const CONNECTID_TRANSIENT_KEY = Plugin::SLUG . '_connect_id';
  
  /** 
   * ConnectID TTL 
   * 
   * @const int
   */
  private const CONNECT_ID_TTL = 300; 

  public mixed $error = null;

  public int $batch_size = 100;
  public int $current_result_count = 0;
  public int $total_result_count = 0;
  public string $request_next_record = '0';

  public string $response_next_record = '0';


  /**
   * 
   * 
   * @const string
   */
  private const CONFIG_DEFAULTS = [
    'log_enabled' => false,
    'server_url' => '',
    'server_port' => '4090',
    'username' => '',
    'password' => '',
    'data_id' => 'Test'
  ];


  /**
   * Value of the ConnectID transient
   *
   * @var string|null
   */
  private static ?string $connect_id = null;


  /**
   * Client class instance
   * 
   * @var Client
   */ 
  private static Client $client;

  /**
   * Fincon API configuration values
   * 
   * @var array
   */
  private static array $configs = [];

  /**
   * Fincon API instance base URL
   * Format http://<host>:<port><base_path>
   * 
   * Built from configuration options saved in WP Admin Dashboard in the Fincon Connector > API Connection
   * 
   * @var string
   */
  private static string $base_url = '';

  private static bool $log_enabled;

  public function __construct( array $config_override = ['log_enabled' => true] ) {
    self::$configs = self::get_configs();
    self::$log_enabled = self::$configs['log_enabled'] ?? false;
    self::$base_url = self::build_base_url();
    self::$connect_id = get_transient( self::CONNECTID_TRANSIENT_KEY );
    self::$client = new Client( self::$log_enabled );
  }


  private static function get_base_url(): string {
      if ( empty( self::$base_url ) ) {
        self::$configs  = self::get_configs();
        self::$base_url = self::build_base_url();
      }
      return self::$base_url;
  }

  /*
  * Retrieves saved API configuration options.
  *
  * @return array The array of plugin options
  */
  public static function get_configs( array $config_override = [] ): array {
    $options = wp_parse_args( self::get_options(), self::CONFIG_DEFAULTS );
    return wp_parse_args( $config_override, $options );
  }

  public static function get_options( ): array {
    $options = get_option( self::OPTIONS_NAME, [] );

    if ( ! is_array( $options ) ) 
      $options = [];
    
    return $options;
  }

  public static function get_option( string $option_key ): mixed {
    $options = get_option( self::OPTIONS_NAME, [] );
    return $options[$option_key] ?? null;
  }

  private static function build_base_url(): string {
    $server_url = self::$configs['server_url'] ?? '';
    $port       = self::$configs['server_port'] ?? '4090';
    $base_api_url = rtrim( $server_url, '/' ) . ':' . $port . self::BASE_PATH;
    return $base_api_url;
  }

  /**
   * Retrieves the current active Fincon ConnectID.
   *
   * @return string The ConnectID or an empty string if expired or not set.
   */
  public function get_connect_id(): string | WP_Error  {
    $connect_id = self::$connect_id ?? $this->create_connect_id();
    if ( is_wp_error( $connect_id ) ) 
      return $connect_id;
   
    return (string) $connect_id;
  }


  /**
   * Creates a new Fincon session via a login request and save the returned ConnectID as a transient
   * @return string | WP_Error The ConnectID 
   */
  public function create_connect_id(): string | WP_Error {
    $login_result = $this->login( true );
    if ( is_wp_error( $login_result ) ) :
      $error = $login_result;
      Logger::error( sprintf( 'Connect ID creation failed: %s', $login_result->get_error_message() ) );
      return $error;
    endif;  
    $connect_id = $login_result['ConnectID'] ?? '';
    self::set_connect_id( $connect_id );
    return $connect_id;
  }

  /**
   * Save connect_id as transient
   * if connect_id is empty delete transient
   * 
   * @param string $connect_id
   */
  public static function set_connect_id( string $connect_id = '' ): void {
    if ( empty( $connect_id ) ):
      self::clear_connect_id();
    else:
      self::$connect_id = $connect_id;
      set_transient( self::CONNECTID_TRANSIENT_KEY, self::$connect_id, self::CONNECT_ID_TTL );
    endif;
  }


  /**
   * Cleart connect_id transient
   * if connect_id is empty delete transient
   * 
   * @param string $connect_id
   */
  public static function clear_connect_id( ): void {
    self::$connect_id = null;
    delete_transient( self::CONNECTID_TRANSIENT_KEY );
  }

  /**
   * Helper to determine if a ConnectID is currently active/set.
   *
   * @return bool
   */
    public static function has_active_session(): bool {
      return ! empty( self::$connect_id );
    }

  /**
   * Structure and send GET request
   *
   * @param string $function
   * @param array $parameters
   * @return void
   */
  private function get_request( string $function, array $parameters = [], bool $skip_connect_id = false ): array|\WP_Error {
    return $this->send_request( 'GET', $function, $parameters, [], $skip_connect_id );
  }

  /**
   * Structure and send POST request
   *
   * @param string $function
   * @param array $parameters
   * @return void
   */
  private function post_request( string $function, array $query_params = [], array $body_params = [ ], bool $skip_connect_id = false ): array|\WP_Error {
    return $this->send_request( 'POST', $function, $query_params, $body_params, $skip_connect_id );
  }

  /**
   * Executes the API request and returns the parsed result or WP_Error.
   * @param string $function_name The Fincon API function name.
   * @param string $method HTTP method (GET/POST).
   * @param array $query_params Request parameters.
   * @param array $body_params Request parameters.
   * @param bool $skip_connect_id If true, ConnectID is not added as the first parameter (used only for Login/Logout).
   * 
   * @return array|\WP_Error The Fincon API result array or a WP_Error object.
   */
  private function send_request( string $method, string $function_name, array $query_params = [],  array $request_body = [], $skip_connect_id = false): array|\WP_Error {
    if ( ! $skip_connect_id && empty( self::$connect_id ) ) {
      $connect_id_result = $this->create_connect_id();
      if ( is_wp_error( $connect_id_result ) )
        return $connect_id_result;
    }

    $path = self::build_path( $function_name, $query_params, $skip_connect_id );
    self::$client->method = $method;
    self::$client->path = $path;
    self::$client->request_body = $request_body;
    self::$client->auth_header = self::build_auth_header();
  
    $result = self::$client->request();
    $code = self::$client->response_code;

    self::log_api_call(
      $function_name,
      $method, 
      $path,
      $code,
      $request_body,
      $result
    );

    if (is_wp_error( $result )) 
      return $result;
    
    if ( !$skip_connect_id ) {
      if ( self::is_invalid_connect_id_response( $result ) ) :
          $connect_id_result = self::create_connect_id();
          
          if ( is_wp_error( $connect_id_result ) ) :
              $error = $connect_id_result;
              return $error;
          endif;

          // Rebuild path with updated connect_id
          $path = self::build_path( $function_name, $query_params, $skip_connect_id );

          /** * FIX: We must re-assign the original request properties to the client.
           * Otherwise, the client still contains the POST body and method from the login() call.
           */
          self::$client->method       = $method;
          self::$client->path         = $path;
          self::$client->request_body = $request_body; // Restores the original empty array/params
          self::$client->auth_header  = self::build_auth_header();

          // Retry request
          $result = self::$client->request();
      endif;
    }

    if ( is_wp_error($result) ) :
      $error = $result;
      Logger::error( $error->get_error_message() );
      return $error;
    endif;

    $this->error = $result['ErrorInfo'];

    $data = $result['result'][0] ?? [];
    $this->current_result_count = $data['Count'] ?? 0;
    $this->total_result_count += $this->current_result_count;
    $this->response_next_record = $data['RecNo'] ?? '';

    if ( ! is_array( $data ) || empty( $data ) )
      return new \WP_Error(
          'fincon_parse_error',
          __( 'Fincon API returned an invalid or empty response structure.', 'df-fincon' ),
          [ 'raw_body' => $result ?? 'N/A' ]
      );

    // Check for Fincon API errors in result[0]['ErrorInfo']
    if ( ! empty( $data['ErrorInfo'] ) )
      return new \WP_Error( 'fincon_api_error', $data['ErrorInfo'] );
    
    // Also check top-level ErrorInfo for backward compatibility
    if ( ! empty( $result['ErrorInfo'] ) )
      return new \WP_Error( 'fincon_api_error', $result['ErrorInfo'] );
    
    return $data;
  }

  /**
   * Builds the Fincon API resource path (e.g., /%22Login%22/param1/param2/).
   *
   * @param string $function_name The Fincon API function name (e.g., "Login").
   * @param array $parameters Associative array of parameters to append to the URL.
   * @return string The resource path to append to the base URL.
   */
  private function build_path( string $function_name, array $parameters = [], $skip_connect_id = false ): string {
    if ( empty( self::get_base_url() ) ) {
        Logger::error( 'build_path() called with empty $base_url — FinconApi may not have been properly instantiated.' );
        // Rebuild it defensively
        self::$configs = self::get_configs();
        self::$base_url = self::build_base_url();
    }

    // Function name must be enclosed in quotes and URL encoded
    $path =  self::get_base_url()  . '%22' . $function_name . '%22' . '/';

    if ( !$skip_connect_id ) :
      if ( empty( self::$connect_id ) )
        $this->create_connect_id();
      $path .= self::$connect_id . '/';
    endif;

    // Append parameters as raw path segments.
    if ( ! empty( $parameters ) ) {
      $encoded = array_map( function( $param ) {
          return rawurlencode( (string) $param );
      }, $parameters );
      $path .= implode( '/', $encoded );
    }
    // Trailing slash for consistency
    $path = rtrim( $path, '/' ) . '/';
    Logger::info('build_path:', $path);
    return $path;
  }

  private static function build_auth_header(): string {
    $username = self::$configs['username'] ?? '';
    $password = self::$configs['password'] ?? '';
    $auth_header = 'Basic ' . base64_encode( $username . ':' . $password );
    return $auth_header;
  }

  public function has_more(): bool {
    return ( $this->current_result_count === $this->batch_size );
  }

  /**
   * Logs the API request and response details.
   *
   * @param string $function_name The Fincon API function being called.
   * @param array $request_details Details of the request sent (path, method, body).
   * @param array|\WP_Error $response_result The result of the client request.
   */
  private static function log_api_call(  string $function_name, string $method, string $path, string $code, array $request_body, mixed $result ): void {
    if ( !self::$log_enabled )
      return;

    $log_message = "--- API CALL: {$function_name} ---\n";

    // --- Sensitive Data Redaction for Login call ---
    if ( $function_name === 'Login' ) {
        // Pattern matches: (/DataID/UserName/)(Password/)(UseAltExt/)
        $logged_path = preg_replace( 
            '/(\/DataID\/.*?\/?.*?\/?)(.+?)(\/.+?\/)$/', 
            '$1[PASSWORD REDACTED]$3', 
            $path, 
            1 
        );
    }
    
    $log_message .= 'Resource Path: ' . $logged_path . "\n";
    $log_message .= 'Method: ' . $method . "\n";

    if ( ! empty( $request_body ) ) 
      $log_message .= 'Request Body: ' . print_r( $request_body, true ) . "\n";
    
    $log_message .= 'Response Code: ' . ($code ?? 'N/A' ) . "\n";
    
    if ( is_wp_error( $result ) )
      $log_message .= 'Error: ' . $result->get_error_message();
    else
      $log_message .= 'Response: ' . "\n" . print_r( $result ?? '[]', true );

    // Check for HTTP error status or Fincon's internal ErrorInfo
    if (  is_wp_error( $result ) || ( $code < 200 || $code >= 300 ) || ( isset( $response['result'][0]['ErrorInfo'] ) && ! empty( $response['result'][0]['ErrorInfo'] ) ) ) 
      Logger::error( $log_message );
    else 
      Logger::debug( $log_message );
    
  }

  private static function is_invalid_connect_id_response( $result ): bool {
  
    // Handle Expired/Invalid ConnectID error
    if ( is_wp_error( $result ) )
      if ( str_contains( $result->get_error_message(), 'Invalid connect ID' ) ) 
        return true;
    
    return false;

  }

  /**
   * Attempts to authenticate and get a new ConnectID.
   * API function: "Login" 
   * Method: GET
   * Parameters: 
   *  - DataID, 
   *  - UserName, 
   *  - Password, 
   *  - UseAltExt
   * 
   * @param string $clear_connect_id The ConnectID to log out.
   * @return array|\WP_Error Returns ConnectID and APIVersion on success, or WP_Error.
   */
  public function login( $clear_connect_id = true ): array | \WP_Error {
    $function = 'Login';
    $skip_connect_id = true;

    if ( $clear_connect_id )
        self::clear_connect_id();

    $body_params = [
        '_parameters' => [
            self::$configs['data_id'] ?? '',
            self::$configs['username'] ?? '',
            self::$configs['password'] ?? '',
            false,
        ]
    ];

    $result = $this->post_request( $function, [], $body_params, $skip_connect_id );

    if ( is_wp_error( $result ) )
        return $result;

    if ( ! isset( $result['ConnectID'] ) || empty( $result['ConnectID'] ) )
        return new \WP_Error( 'fincon_login_failed', __( 'Login failed: ConnectID was not returned.', 'df-fincon' ) );

    self::set_connect_id( $result['ConnectID'] );

    return $result;
  }

  /**
   * Invalidates the current session on the Fincon side.
   * 
   * @param string $connect_id The ConnectID to log out.
   * @return bool|\WP_Error True on successful logout, or WP_Error.
   */
  public function logout( string $connect_id ): bool|\WP_Error {
      

      // if ( is_wp_error( $result ) ) {
      //     Logger::error( sprintf( 'Logout failed for ID %s: %s', $connect_id, $result->get_error_message() ) );
      //     return $result;
      // }
      
      // Clear local cache regardless of server success response
      //FinconService::clear_connect_id();

      return true;
  }

  /**
   * Tests the connection by attempting a login.
   * 
   * @param bool $force_new If true, forces a new login attempt, ignoring existing ConnectID.
   * @return array|\WP_Error ConnectID and APIVersion on success, or WP_Error.
   */
  public function test_login( bool $force_new = true ): string | WP_Error {
    if ( $force_new ) 
      FinconApi::clear_connect_id();
    
    $login_result = $this->login( $force_new );

    $login_test_message = '';

    if ( is_wp_error( $login_result ) ) :
      $login_test_message = sprintf( 'Connection Test Failed: %s', $login_result->get_error_message() ); 
      Logger::error( $login_test_message );
      return $login_result;
    endif; 
    
    $login_test_message = sprintf( 'Connection Test Successful. ConnectID: %s', $login_result['ConnectID'] ); 
    Logger::info( $login_test_message );
    return $login_test_message ;
  }

  /**
   * Get all stock
   * API function: "Login" 
   * Method: GET
   * Parameters: 
   *  - MinItemNo (blank for all)
   *  - MaxItemNo (blank for all)
   *  - LocNo (0 for default location)
   *  - WebOnly (false to include all items)
   *  - RecNo (Start offset)
   *  - Count (Number of records to fetch)
   * 
   * @param string $clear_connect_id The ConnectID to log out.
   * @return array|\WP_Error Returns ConnectID and APIVersion on success, or WP_Error.
   */

  public function get_stock_items( string $start_item_no = '', string $end_item_no = '', string $next_record = '0' ): array|\WP_Error {
    $function = 'GetStock';
    
    // Get web_only setting from product sync options (default to true for backward compatibility)
    $options = ProductSync::get_options();
    $web_only = ! empty( $options['import_web_only'] ) ? 1 : 0;
    $parameters = [
      $start_item_no,
      $end_item_no,
      LocationManager::create()->get_location_codes_string(),
      $web_only,
      $next_record,
      $this->batch_size,
    ];

    $result = $this->get_request( $function, $parameters );
    
    if ( is_wp_error( $result ) )
      return $result;

    if ( ! isset( $result['Stock'] ) || ! is_array( $result['Stock'] ) )
      return new \WP_Error(
          'fincon_stock_parse_error',
          __( 'Fincon API returned stock data in an unexpected format.', 'df-fincon' ),
          [ 'raw_response' => $result ]
      );
    
    return $result;

  }

  /**
   * Get debtor accounts (customers)
   */
  public function GetDebAccounts( string $min_acc_no = '', string $max_acc_no = '', int $next_rec_no = 0, int $count = 100 ): array|\WP_Error {
    $function = 'GetDebAccounts';

    $parameters = [
      $min_acc_no,
      $max_acc_no,
      $next_rec_no,
      $count,
    ];

    $result = $this->get_request( $function, $parameters );

    if ( is_wp_error( $result ) )
      return $result;

    if ( empty( $result ) )
      return new \WP_Error(
        'fincon_debtor_parse_error',
        __( 'Fincon API returned customer data in an unexpected format.', 'df-fincon' ),
        [ 'raw_response' => $result ]
      );

    if ( isset( $result['Debtors'] ) )
      return $result;

    // Some installations might still return Stock for backwards compatibility.
    if ( isset( $result['Stock'] ) ) {
      $result['Debtors'] = $result['Stock'];
      unset( $result['Stock'] );
    }

    return $result;
  }

/**
   * Get debtor accounts (customers)
   */
  public function GetDebAccountsByAccNo( string $acc_no, int $next_rec_no = 0, int $count = 1 ): array|\WP_Error {
    $function = 'GetDebAccounts';

    $parameters = [
      $acc_no,
      $acc_no,
      $next_rec_no,
      $count,
    ];

    $result = $this->get_request( $function, $parameters );
    if ( is_wp_error( $result ) )
      return $result;

    if ( empty( $result ) )
      return new \WP_Error(
        'fincon_debtor_parse_error',
        __( 'Fincon API returned customer data in an unexpected format.', 'df-fincon' ),
        [ 'raw_response' => $result ]
      );

    if ( isset( $result['Debtors'] ) )
      return $result;

    // Some installations might still return Stock for backwards compatibility.
    if ( isset( $result['Stock'] ) ) {
      $result['Debtors'] = $result['Stock'];
      unset( $result['Stock'] );
    }

    return $result;
  }

  /**
   * Get stock updated since FromDate
   * API function: "Login"
   * Method: GET
   * Parameters:
   *  - FromDate (format yyyymmdd)
   *  - FromTime (format hh:mm:ss)
   *  - LocNo (0 for default location)
   *  - WebOnly (false to include all items)
   *  - RecNo (Start offset)
   *  - Count (Number of records to fetch)
   *
   * @param string $clear_connect_id The ConnectID to log out.
   * @return array|\WP_Error Returns ConnectID and APIVersion on success, or WP_Error.
   */

  public function get_stock_items_changed( int $count = 10, int $start_offset = 0, string $fromDate = '20200101'  ): array|\WP_Error {
    $function = 'GetStock';
    $parameters = [
        $fromDate,
        '00:00:00',
        LocationManager::create()->get_location_codes_string(),
        'true',
        $start_offset,
        $count,
    ];

    $result = $this->get_request( $function, $parameters );
    
    if ( is_wp_error( $result ) )
      return $result;

    if ( ! isset( $result['Stock'] ) || ! is_array( $result['Stock'] ) )
      return new \WP_Error(
          'fincon_stock_parse_error',
          __( 'Fincon API returned stock data in an unexpected format.', 'df-fincon' ),
          [ 'raw_response' => $result ]
      );
    
    return $result;

  }

  /**
   * Create a sales order in Fincon
   * API function: "CreateSalesOrder"
   * Method: POST
   * Parameters:
   *  - ConnectID
   *  - SetAsApproved (true/false)
   *
   * @param array $order_data Sales order data including SalesOrderDetail array
   * @param bool $set_as_approved Whether to set the order as approved (default: true for paid orders)
   * @return array|\WP_Error API response with SalesOrderInfo or WP_Error
   * @since 1.0.0
   */
  public function create_sales_order( array $order_data, bool $set_as_approved = true ): array|\WP_Error {
    $function = 'CreateSalesOrder';
    $query_params = [
      $set_as_approved ? 'true' : 'false',
    ];
    
    $result = $this->post_request( $function, $query_params, $order_data );
    
    if ( is_wp_error( $result ) )
      return $result;

    // The API returns result array with SalesOrderInfo
    if ( ! isset( $result['SalesOrderInfo'] ) || empty( $result['SalesOrderInfo'] ) )
      return new \WP_Error(
        'fincon_sales_order_parse_error',
        __( 'Fincon API returned sales order data in an unexpected format.', 'df-fincon' ),
        [ 'raw_response' => $result ]
      );

    return $result;
  }

 /**
   * Create a quotation in Fincon.
   *
   * API function : "CreateQuotation"
   * Method       : POST
   * Path params  : ConnectID
   * Body         : {QuotationRecord, "QuotationDetail":[{QuotationDetailRecord}],
   *                 "GroupDetail":[{GroupDescriptionRecord}]}
   *
   * QuoteNo, Status and UnitCost are read-only on the Fincon side.
   * QuoteDate is always set to today by Fincon – do not send it.
   * Only local-currency debtor accounts are allowed (max 1 000 detail lines).
   *
   * @param array $quote_data  Associative array matching QuotationRecord +
   *                           "QuotationDetail" key containing line items.
   * @return array|\WP_Error   API response with QuotationInfo or WP_Error.
   * @since 1.2.0
   */
  public function create_quotation( array $quote_data ): array|\WP_Error {
    $function    = 'CreateQuotation';
    $query_params = []; // ConnectID is injected by send_request() / build_path()
 
    $result = $this->post_request( $function, $query_params, $quote_data );
 
    if ( is_wp_error( $result ) )
      return $result;
 
    if ( ! isset( $result['QuotationInfo'] ) || empty( $result['QuotationInfo'] ) )
      return new \WP_Error(
        'fincon_quotation_parse_error',
        __( 'Fincon API returned quotation data in an unexpected format.', 'df-fincon' ),
        [ 'raw_response' => $result ]
      );
 
    return $result;
  }
 
  /**
   * Expire (or reactivate) a Fincon quotation.
   *
   * API function : "SetQuotationExpired"
   * Method       : POST / GET
   * Path params  : ConnectID / QuoteNo / Expired
   *
   * Set $expired = true  → status changes to Expired.
   * Set $expired = false → status changes back to Active.
   *
   * @param string $quote_no  Fincon quotation number (e.g. "Q-00125").
   * @param bool   $expired   True to expire, false to reactivate. Default true.
   * @return array|\WP_Error  Response containing QuotationExpiredResultRecord or WP_Error.
   * @since 1.2.0
   */
  public function set_quotation_expired( string $quote_no, bool $expired = true ): array|\WP_Error {
    $function     = 'SetQuotationExpired';
    // API path: .../SetQuotationExpired/ConnectID/QuoteNo/Expired/
    $query_params = [
      $quote_no,
      $expired ? 'true' : 'false',
    ];
 
    $result = $this->post_request( $function, $query_params, [] );
 
    if ( is_wp_error( $result ) )
      return $result;
 
    return $result;
  }
 
  /**
   * Retrieve sales orders for a debtor account – used to resolve a stored
   * QuoteNo to the sales order the Fincon team created from it.
   *
   * API function : "GetSalesOrdersByAccNo"
   * Method       : GET
   * Path params  : ConnectID / MinAccNo / MaxAccNo / LocNo /
   *                OutstandingOnly / ListTransactions / RecNo / Count
   *
   * NOTE: OutstandingOnly is intentionally set to FALSE here so that orders
   * which have already been invoiced (and are therefore no longer
   * "outstanding") are still returned. This prevents a timing gap where the
   * cron misses a rapid quote→order→invoice sequence.
   *
   * @param string $acc_no   Debtor account number.
   * @param string $loc_no   Stock location filter. '' = all locations.
   * @param int    $count    Max records to return. Default 100.
   * @return array|\WP_Error API response with SalesOrders array or WP_Error.
   * @since 1.2.0
   */
  public function get_sales_orders_by_acc_no(
    string $acc_no,
    string $loc_no  = '',
    int    $count   = 100
  ): array|\WP_Error {
    $function     = 'GetSalesOrdersByAccNo';
    $query_params = [
      $acc_no,    // MinAccNo
      $acc_no,    // MaxAccNo
      $loc_no,    // LocNo  ('' = all)
      'false',    // OutstandingOnly – FALSE so invoiced orders remain visible
      'false',    // ListTransactions – detail lines not needed for matching
      '0',        // RecNo
      (string) $count,
    ];
 
    $result = $this->get_request( $function, $query_params );
 
    if ( is_wp_error( $result ) )
      return $result;
 
    if ( ! isset( $result['SalesOrders'] ) )
      return new \WP_Error(
        'fincon_sales_orders_parse_error',
        __( 'Fincon API returned sales orders in an unexpected format.', 'df-fincon' ),
        [ 'raw_response' => $result ]
      );
 
    return $result;
  }

  /**
   * Get PDF document from Fincon
   * API function: "GetDocumentPdf"
   * Method: GET
   * Parameters:
   *  - ConnectID
   *  - DocType (e.g., 'I' for Invoice, 'C' for Credit Note)
   *  - DocNo (document number)
   *  - LayoutName (optional, blank for default)
   *  - NoImages (optional, false to include images)
   *
   * @param string $doc_type Document type (e.g., 'I' for Invoice)
   * @param string $doc_no Document number
   * @param string $layout_name Layout name (optional, default empty)
   * @param bool $no_images Whether to exclude images (optional, default false)
   * @return array|\WP_Error API response with DocumentPdf base64 string or WP_Error
   * @since 1.0.0
   */
  public function get_document_pdf( string $doc_type, string $doc_no, string $layout_name = '', bool $no_images = false ): array|\WP_Error {
    $function = 'GetDocumentPdf';
    $query_params = [
      $doc_type,
      $doc_no,
      $layout_name,
      $no_images ? 'true' : 'false',
    ];
    
    $result = $this->get_request( $function, $query_params );
    
    if ( is_wp_error( $result ) )
      return $result;

    // The API returns result array with DocumentInfo containing DocumentPdf
    if ( ! isset( $result['DocumentInfo'] ) || empty( $result['DocumentInfo'] ) )
      return new \WP_Error(
        'fincon_document_pdf_parse_error',
        __( 'Fincon API returned document PDF data in an unexpected format.', 'df-fincon' ),
        [ 'raw_response' => $result ]
      );

    return $result;
  }

  /**
   * Get sales order by order number
   * API function: "GetSalesOrdersByOrderNo"
   * Method: GET
   * Parameters:
   *  - ConnectID
   *  - MinOrderNo (minimum order number)
   *  - MaxOrderNo (maximum order number)
   *  - LocNo (stock location, blank for all)
   *  - OutstandingOnly (true/false)
   *  - ListTransactions (true/false)
   *  - RecNo (record number, 0 for start)
   *  - Count (number of records to fetch)
   *
   * @param string $order_no Sales order number
   * @param string $loc_no Stock location (optional, default '')
   * @param bool $outstanding_only Whether to only include outstanding orders (optional, default false)
   * @param bool $list_transactions Whether to include detail transactions (optional, default true)
   * @param int $rec_no Record number (optional, default 0)
   * @param int $count Number of records (optional, default 1)
   * @return array|\WP_Error API response with SalesOrderInfo or WP_Error
   * @since 1.1.0
   */
  public function get_sales_order_by_order_no(
    string $order_no,
    string $loc_no = '',
    bool $outstanding_only = false,
    bool $list_transactions = true,
    int $rec_no = 0,
    int $count = 1
  ): array|\WP_Error {
    $function = 'GetSalesOrdersByOrderNo';
    $query_params = [
      $order_no,
      $order_no,
      $loc_no,
      $outstanding_only ? 'true' : 'false',
      $list_transactions ? 'true' : 'false',
      $rec_no,
      $count,
    ];
    
    $result = $this->get_request( $function, $query_params );
    
    if ( is_wp_error( $result ) )
      return $result;

    // The API returns result array with SalesOrders
    if ( ! isset( $result['SalesOrders'] ) || ! is_array( $result['SalesOrders'] ) || empty( $result['SalesOrders'] ) )
      return new \WP_Error(
        'fincon_sales_order_not_found',
        __( 'Sales order not found or no data returned.', 'df-fincon' ),
        [ 'raw_response' => $result ]
      );

    // Return the first sales order
    return $result['SalesOrders'][0] ?? $result;
  }

}
