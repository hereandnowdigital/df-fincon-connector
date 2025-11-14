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



  /**
   * 
   * 
   * @const string
   */
  private const CONFIG_DEFAULTS = [
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
   * Built from configuration options saved in WP Admin Dashboard in the Fincon Connector > API settings
   * 
   * @var string
   */
  private static string $base_url = '';

  private static bool $log_enabled;

  public function __construct( array $config_override = ['log_enabled' => true] ) {
    self::$configs = self::get_configs();

    // #EM-TODO: Move LOG Enabled to plugin options settings page
    self::$log_enabled = self::$configs['log_enabled'] ?? false;

    self::$base_url = self::build_base_url();
    self::$connect_id = get_transient( self::CONNECTID_TRANSIENT_KEY );
    self::$client = new Client( self::$log_enabled );
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

  public static function get_options( ) {
    return get_option( self::OPTIONS_NAME, [] );
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
  public static function get_connect_id(): string | WP_Error  {
    return (string) (self::$connect_id ?? self::create_connect_id() );
  }


  /**
   * Creates a new Fincon session via a login request and save the returned ConnectID as a transient
   * @return string | WP_Error The ConnectID 
   */
  public static function create_connect_id(): string | WP_Error {
    $login_result = self::login( true );
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
  public static function set_connect_id( string $connect_id = '' ) {
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
  public static function clear_connect_id( ) {
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
  private static function get_request( string $function, array $parameters = [], $skip_connect_id = false ): array|\WP_Error {
    return self::send_request( 'GET', $function, $parameters, [], $skip_connect_id );
  }

  /**
   * Structure and send POST request
   *
   * @param string $function
   * @param array $parameters
   * @return void
   */
  private static function post_request( string $function, array $query_params = [], array $body_params = [ ], $skip_connect_id = false ): array|\WP_Error {
    return self::send_request( 'POST', $function, $query_params, $body_params, $skip_connect_id );
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
  private static function send_request( string $method, string $function_name, array $query_params = [],  array $request_body = [], $skip_connect_id = false): array|\WP_Error {
    $path = self::build_path( $function_name, $query_params, $skip_connect_id );
    
    self::$client->method = $method;
    self::$client->path = $path;
    self::$client->request_body = $request_body;
    self::$client->auth_header = self::build_auth_header();
  
    $result = self::$client->request();
    $code = self::$client->code;

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
    
    if ( !$skip_connect_id ) 
      if ( self::is_invalid_connect_id_response( $result ) ):
        $connect_id_result = self::create_connect_id();
        if ( is_wp_error( $connect_id_result ) ) :
          $error = $connect_id_result;
          return $error;
        endif;

        //rebuild path with update connect_id
        $path = self::build_path( $function_name, $query_params, $skip_connect_id );
        //retry request
        $result = self::$client->request();

      endif;

    if ( is_wp_error($result) ) :
      $error = $result;
      Logger::error( $error->get_error_message() );
      return $error;
    endif;

    $data = $result['result'][0] ?? [];

    if ( ! is_array( $data ) || empty( $data ) ) :
        return new \WP_Error( 
            'fincon_parse_error', 
            __( 'Fincon API returned an invalid or empty response structure.', 'df-fincon' ),
            [ 'raw_body' => $result ?? 'N/A' ]
        );
    endif;

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
  private static function build_path( string $function_name, array $parameters = [], $skip_connect_id = false ): string {
    // Function name must be enclosed in quotes and URL encoded
    $path =  self::$base_url  . '%22' . $function_name . '%22' . '/';

    if ( !$skip_connect_id ) :
      if ( empty( self::$connect_id ) )
        self::create_connect_id();
      $path .= self::$connect_id . '/';
    endif;

    // Append parameters as raw path segments.
    if ( ! empty( $parameters ) ) 
        $path .= implode( '/', $parameters );
    
    // Trailing slash for consistency
    return rtrim( $path, '/' ) . '/';
  }

  private static function build_auth_header(): string {
    $username = self::$configs['username'] ?? '';
    $password = self::$configs['password'] ?? '';
    $auth_header = 'Basic ' . base64_encode( $username . ':' . $password );
    return $auth_header;
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
    if (  is_wp_error( $result ) || ( $code < 200 || $code >= 300 ) || ( isset( $response['result'][0]['ErrorInfo'] ) && ! empty( $response['result'][0]['ErrorInfo'] ) ) ) {
      Logger::error( $log_message );
    } else {
      Logger::debug( $log_message );
    }
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
  public static function login( $clear_connect_id = true ): array|\WP_Error {
    $function = 'Login';
    $method = 'GET';
    $parameters = [
        self::$configs['data_id'] ?? '',
        self::$configs['username'] ?? '',
        self::$configs['password'] ?? '',
        '0',
    ];
    $skip_connect_id = true;

    if ( $clear_connect_id ) 
      self::clear_connect_id();

    $result = self::get_request( $function, $parameters, $skip_connect_id );
    Logger::debug('Login response: ', $result );

    if ( is_wp_error( $result ) ) 
      return $result;
    
    Logger::debug('$result["ConnectID"]',  $result['ConnectID'] );
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
  public static function test_login( bool $force_new = true ): string | WP_Error {
    if ( $force_new ) 
      FinconApi::clear_connect_id();
    
    $login_result = self::login( $force_new );

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

  public static function get_stock_items( int $count = 10, int $start_offset = 0  ): array|\WP_Error {
    $function = 'GetStock';
    $parameters = [
        '', 
        '', 
        '00,01,03',
        'true',
        $start_offset, 
        $count, 
    ];

    $result = self::get_request( $function, $parameters ) ?? [];
    
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

  public static function get_stock_items_changed( int $count = 10, int $start_offset = 0, string $fromDate = '20200101'  ): array|\WP_Error {
    $function = 'GetStock';
    $parameters = [
        $fromDate, 
        '00:00:00', 
        '00,01,03',
        'true',
        $start_offset, 
        $count, 
    ];

    $result = self::get_request( $function, $parameters ) ?? [];
    
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

}
