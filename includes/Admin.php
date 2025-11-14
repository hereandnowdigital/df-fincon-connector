<?php
  /**
   * WordPress Admin dashboard Functionality
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

class Admin {
  const OPTIONS_NAME = Plugin::OPTIONS_NAME;
  const TEST_NONCE  = 'df_fincon_test_connection_nonce';

  const IMPORT_NONCE  = 'df_fincon_product_import_nonce';

  const TEMPLATE_PATH = DF_FINCON_PLUGIN_DIR . 'templates/admin/';

  private static ?self $instance = null;
  
  private function __construct( ) {
    self::init();
  }  

 public static function create(  ): self {
    if ( self::$instance === null ) {
      self::$instance = new self( );
    }
    return self::$instance;
  }

  private static function init() {
    self::register_actions();
  }

  private static function register_actions(): void {
    add_action( 'admin_menu', [ __CLASS__,'register_menu' ] );
    add_action( 'admin_init', [ __CLASS__,'register_settings' ] );
    add_action( 'wp_ajax_df_fincon_test_connection', [ __CLASS__,'ajax_test_connection' ] );
    add_action( 'wp_ajax_df_fincon_manual_import_products', [ __CLASS__,'ajax_manual_import_products' ] );
    add_action( 'admin_enqueue_scripts', [ __CLASS__,'enqueue_scripts' ] );
  } 

  public static function register_menu(): void {
    add_menu_page(
      __( 'FinCon Connector', 'df-fincon' ),
      __( 'FinCon Connector', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-settings',
      [ __CLASS__,'render_settings_page' ],
      'dashicons-admin-generic',
      50
    );

    add_submenu_page(
        'df-fincon-settings',
        __( 'Product Import', 'df-fincon' ),
        __( 'Product Import', 'df-fincon' ),
        'manage_woocommerce',
        'df-fincon-import',
        [ __CLASS__,'render_import_page' ]
    );

  }

  public static function register_settings(): void {
      register_setting( 'df_fincon_settings_group', FinconApi::OPTIONS_NAME );
      
      add_settings_section( 'df_fincon_api_settings', __( 'API Connection Settings', 'df-fincon' ), null, 'df-fincon-settings-page' );

      $fields = [
          'server_url' => __( 'Server URL', 'df-fincon' ),
          'server_port' => __( 'Server Port', 'df-fincon' ),
          'username' => __( 'FinCon Username', 'df-fincon' ),
          'password' => __( 'FinCon Password', 'df-fincon' ),
          'data_id' => __( 'Data ID', 'df-fincon' )
      ];

      foreach ( $fields as $id => $label ) {
        add_settings_field( $id, $label, [ __CLASS__,'render_field' ], 'df-fincon-settings-page', 'df_fincon_api_settings', [ 'id' => $id ] );
      }
  }

  public static function render_field( array $args ): void {
    $options = FinconApi::get_options();
    $value = esc_attr( $options[ $args['id'] ] ?? '' );
    $type = in_array( $args['id'], ['username', 'password', 'data_id', 'server_url'] ) ? $args['id'] : 'text';
    $input_type = match ($args['id']) {
      'password' => 'password',
      default => 'text',
    };
    
    if ( $input_type === 'checkbox' ) : 
      $checked = ! empty( $options[ $args['id'] ] ) ? 'checked' : '';
      printf(
        '<input type="checkbox" name="%s[%s]" value="1" %s />',
        self::OPTIONS_NAME,
        $args['id'],
        $checked
      );
    else :
      printf(
        '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
        $input_type,
        $args['id'],
        FinconApi::OPTIONS_NAME,
        $args['id'],
        $value
      );
    endif;
  }

  public static function render_settings_page(): void {
    $template = SELF::TEMPLATE_PATH . 'settings.php';
    if ( !file_exists(  $template) ) 
      return;

    $options = FinconApi::get_options();
    $api_test_nonce = wp_create_nonce( self::TEST_NONCE );
    include $template;    
  }

  public static function render_import_page(): void {
    $template = SELF::TEMPLATE_PATH . 'import.php';
    if ( !file_exists(  $template) ) 
      return;
    
    include $template;    
  }

  public static function enqueue_scripts( $hook ): void {
    if ( ! str_contains( $hook, 'df-fincon' ) ) 
        return;

    $js_path = DF_FINCON_PLUGIN_DIR . 'assets/js/admin.js';
    $version = file_exists( $js_path ) ? filemtime( $js_path ) : DF_FINCON_VERSION;

    wp_enqueue_script( 'df-fincon-admin', DF_FINCON_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], $version, true );
    wp_localize_script( 'df-fincon-admin', 'DF_FINCON_ADMIN', [
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'nonces'   => [
          'test'   => wp_create_nonce( self::TEST_NONCE ),
          'import' => wp_create_nonce( self::IMPORT_NONCE ),
      ],
      'messages' => [
        'testing' => __( 'Testing connection', 'df-fincon' ),
        'generic_error' => __( 'Connection test failed: Unknown error.', 'df-fincon' ),
        'network_error' => __( 'Network error occurred. Check URL and connectivity.', 'df-fincon' ),
      ],
    ] );
  }

  public static function ajax_test_connection(): void {
    check_ajax_referer( self::TEST_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
      wp_send_json_error( [ 'message' => __( 'Permission denied.', 'df-fincon' ) ], 403 );
    }

    $api = new FinconAPI();
    $result = $api->test_login( true ); // Force a refresh to validate credentials

    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    wp_send_json_success( [
      'message' => __( 'Connection successful! ConnectID obtained.', 'df-fincon' ),
      'details' => $result,
    ] );
  }

  public static function ajax_manual_import_products(): void {
    check_ajax_referer( self::IMPORT_NONCE, 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
      wp_send_json_error( [ 'message' => __( 'Permission denied.', 'df-fincon' ) ], 403 );
    }

    $count  = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 10;
    $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

    if ( $count > 50 ) {
        $count = 50;
    }
    
    $service = new FinconService(); 
    $result = $service->import_products( $count, $offset ); 

    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [ 
          'message' => __( 'Import failed: ', 'df-fincon' ) . $result->get_error_message(),
          'details' => $result->get_error_data(),
      ] );
    }

    wp_send_json_success( [
      'message' => sprintf( __( 'Successfully attempted to import %d products starting at offset %d.', 'df-fincon' ), $count, $offset ),
      'details' => $result, // This could be a summary of imported/skipped products
    ] );
  }

}