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
  const OPTIONS_NAME_API = FinconApi::OPTIONS_NAME;
  const OPTIONS_NAME_PRODUCTS = ProductSync::OPTIONS_NAME;
  const OPTIONS_NAME_CUSTOMERS = CustomerSync::OPTIONS_NAME;
  const OPTIONS_NAME_ORDERS = OrderSync::OPTIONS_NAME;
  const OPTIONS_GROUP_API = self::OPTIONS_NAME . '_api_group';
  const OPTIONS_GROUP_PRODUCTS = self::OPTIONS_NAME . '_products_group';
  const OPTIONS_GROUP_CUSTOMERS = self::OPTIONS_NAME . '_customers_group';
  const OPTIONS_GROUP_ORDERS = self::OPTIONS_NAME . '_orders_group';

  const TEST_NONCE  = 'df_fincon_test_connection_nonce';

  const IMPORT_NONCE  = 'df_fincon_product_import_nonce';

  const STOCK_LOCATIONS_NONCE = 'df_fincon_stock_locations_nonce';

  const SYNC_USER_NONCE = 'df_fincon_sync_user_nonce';

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
    add_action( 'admin_init', [ __CLASS__,'register_settings_api' ] );
    add_action( 'admin_init', [ __CLASS__,'register_settings_products' ] );
    add_action( 'admin_init', [ __CLASS__,'register_settings_customers' ] );
    add_action( 'admin_init', [ __CLASS__,'register_settings_orders' ] );
    add_action( 'wp_ajax_df_fincon_test_connection', [ __CLASS__,'ajax_test_connection' ] );
    add_action( 'wp_ajax_df_fincon_manual_import_products', [ __CLASS__,'ajax_manual_import_products' ] );
    add_action( 'wp_ajax_df_fincon_reset_import_progress', [ __CLASS__,'ajax_reset_import_progress' ] );
    add_action( 'wp_ajax_df_fincon_manual_import_customers', [ __CLASS__,'ajax_manual_import_customers' ] );
    add_action( 'wp_ajax_df_fincon_manual_sync_order', [ __CLASS__,'ajax_manual_sync_order' ] );
    add_action( 'wp_ajax_df_fincon_sync_user_by_accno', [ __CLASS__,'ajax_sync_user_by_accno' ] );
    add_action( 'wp_ajax_df_fincon_download_pdf', [ __CLASS__,'ajax_download_pdf' ] );
    add_action( 'wp_ajax_nopriv_df_fincon_download_pdf', [ __CLASS__,'ajax_download_pdf' ] );
    add_action( 'wp_ajax_df_fincon_fetch_pdf', [ __CLASS__,'ajax_fetch_pdf' ] );
    add_action( 'wp_ajax_df_fincon_stock_location_save', [ __CLASS__,'ajax_stock_location_save' ] );
    add_action( 'wp_ajax_df_fincon_stock_location_delete', [ __CLASS__,'ajax_stock_location_delete' ] );
    add_action( 'wp_ajax_df_fincon_stock_location_set_default', [ __CLASS__,'ajax_stock_location_set_default' ] );
    add_action( 'wp_ajax_df_fincon_stock_location_toggle_active', [ __CLASS__,'ajax_stock_location_toggle_active' ] );
    add_action( 'admin_enqueue_scripts', [ __CLASS__,'enqueue_scripts' ], 10 );
    add_action( 'admin_enqueue_scripts', [ __CLASS__,'enqueue_styles' ], 10 );
  }

  public static function register_menu(): void {
    add_menu_page(
      __( 'Fincon Connector', 'df-fincon' ),
      __( 'Fincon', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-settings',
      [ __CLASS__,'render_settings_page' ],
      'dashicons-admin-generic',
      50
    );

    add_submenu_page(
      'df-fincon-settings',
      __( 'Settings', 'df-fincon' ),
      __( 'Settings', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-plugin-settings',
      [ __CLASS__,'render_plugin_settings_page' ]
    );

    add_submenu_page(
      'df-fincon-settings',
      __( 'API Connection', 'df-fincon' ),
      __( 'API Connection', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-api-settings',
      [ __CLASS__,'render_api_settings_page' ]
    );

    add_submenu_page(
      'df-fincon-settings',
      __( 'Manual Product Import', 'df-fincon' ),
      __( 'Manual Product Import', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-import',
      [ __CLASS__,'render_import_page' ]
    );

    add_submenu_page(
      'df-fincon-settings',
      __( 'Manual Customer Import', 'df-fincon' ),
      __( 'Manual Customer Import', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-customer-import',
      [ __CLASS__,'render_customer_import_page' ]
    );

    add_submenu_page(
      'df-fincon-settings',
      __( 'Stock Locations', 'df-fincon' ),
      __( 'Stock Locations', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-stock-locations',
      [ __CLASS__,'render_stock_locations_page' ]
    );

    add_submenu_page(
      'df-fincon-settings',
      __( 'Cron Log Invoices', 'df-fincon' ),
      __( 'Cron Log Invoices', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-cron-log',
      [ __CLASS__,'render_cron_log_page' ]
    );

    add_submenu_page(
      'df-fincon-settings',
      __( 'Cron Log Products', 'df-fincon' ),
      __( 'Cron Log Products', 'df-fincon' ),
      'manage_woocommerce',
      'df-fincon-cron-log-products',
      [ __CLASS__,'render_cron_log_products_page' ]
    );

  }

  public static function register_settings_api(): void {
      register_setting( self::OPTIONS_GROUP_API, self::OPTIONS_NAME_API, [
        'sanitize_callback' => [ __CLASS__, 'sanitize_api_settings' ],
      ] );
      
      add_settings_section(
        self::OPTIONS_NAME,
        __( 'FinCon', 'df-fincon' ),
        null,
        self::OPTIONS_NAME_API
      );

      $fields = [
          'server_url' => __( 'Server URL', 'df-fincon' ),
          'server_port' => __( 'Server Port', 'df-fincon' ),
          'username' => __( 'FinCon Username', 'df-fincon' ),
          'password' => __( 'FinCon Password', 'df-fincon' ),
          'data_id' => __( 'Data ID', 'df-fincon' )
      ];

      foreach ( $fields as $id => $label )
        add_settings_field( $id, $label, [ __CLASS__,'render_field' ], self::OPTIONS_NAME_API, self::OPTIONS_NAME, [ 'id' => $id ] );
      
  }


  public static function register_settings_products(): void {
    register_setting( self::OPTIONS_GROUP_PRODUCTS, self::OPTIONS_NAME_PRODUCTS, [
      'sanitize_callback' => [ __CLASS__, 'sanitize_product_settings' ],
    ] );
    
    add_settings_section( 
      self::OPTIONS_NAME, 
      __( 'Product Import Settings', 'df-fincon' ), 
      null, 
      self::OPTIONS_NAME_PRODUCTS
    );
    
    $import_fields = [
        'import_batch_size' => __( 'Batch Size', 'df-fincon' ),
        'import_update_only_changed' => __( 'Update only changed products', 'df-fincon' ),
        'import_web_only' => __( 'Web Only', 'df-fincon' ),
        'product_cron_log_enabled' => __( 'Enable product cron log', 'df-fincon' ),
    ];

    foreach ( $import_fields as $id => $label )
      add_settings_field( $id, $label, [ __CLASS__,'render_field' ], self::OPTIONS_NAME_PRODUCTS, self::OPTIONS_NAME,[ 'id' => $id, 'options_group' => self::OPTIONS_NAME_PRODUCTS ] );
    
    // Schedule settings
    add_settings_field( 
      'sync_schedule_enabled', 
      __( 'Enable Scheduled Sync', 'df-fincon' ), 
      [ __CLASS__,'render_schedule_enabled_field' ], 
      self::OPTIONS_NAME_PRODUCTS, 
      self::OPTIONS_NAME
    );
    
    add_settings_field( 
      'sync_schedule_frequency', 
      __( 'Sync Frequency', 'df-fincon' ), 
      [ __CLASS__,'render_schedule_frequency_field' ], 
      self::OPTIONS_NAME_PRODUCTS, 
      self::OPTIONS_NAME
    );
    
    add_settings_field( 
      'sync_schedule_time', 
      __( 'Sync Time', 'df-fincon' ), 
      [ __CLASS__,'render_schedule_time_field' ], 
      self::OPTIONS_NAME_PRODUCTS, 
      self::OPTIONS_NAME
    );
    
    add_settings_field( 
      'sync_schedule_day', 
      __( 'Sync Day (Weekly only)', 'df-fincon' ), 
      [ __CLASS__,'render_schedule_day_field' ], 
      self::OPTIONS_NAME_PRODUCTS, 
      self::OPTIONS_NAME
    );
      
  }

  public static function register_settings_customers(): void {
    register_setting( self::OPTIONS_GROUP_CUSTOMERS, self::OPTIONS_NAME_CUSTOMERS, [
      'sanitize_callback' => [ __CLASS__, 'sanitize_customer_settings' ],
    ] );

    add_settings_section(
      self::OPTIONS_NAME,
      __( 'Customer Import Settings', 'df-fincon' ),
      null,
      self::OPTIONS_NAME_CUSTOMERS
    );

    $fields = [
      'customer_batch_size' => __( 'Batch Size', 'df-fincon' ),
      'customer_import_only_changed' => __( 'Import only customers updated on Fincon since the last customer import', 'df-fincon' ),
      'customer_weblist_only' => __( 'WebList only', 'df-fincon' ),
    ];

    foreach ( $fields as $id => $label )
      add_settings_field( $id, $label, [ __CLASS__,'render_field' ], self::OPTIONS_NAME_CUSTOMERS, self::OPTIONS_NAME, [ 'id' => $id, 'options_group' => self::OPTIONS_NAME_CUSTOMERS ] );
    
    // Customer schedule settings
    add_settings_field(
      'customer_sync_schedule_enabled',
      __( 'Enable Scheduled Customer Sync', 'df-fincon' ),
      [ __CLASS__,'render_customer_schedule_enabled_field' ],
      self::OPTIONS_NAME_CUSTOMERS,
      self::OPTIONS_NAME
    );
    
    add_settings_field(
      'customer_sync_schedule_frequency',
      __( 'Customer Sync Frequency', 'df-fincon' ),
      [ __CLASS__,'render_customer_schedule_frequency_field' ],
      self::OPTIONS_NAME_CUSTOMERS,
      self::OPTIONS_NAME
    );
    
    add_settings_field(
      'customer_sync_schedule_time',
      __( 'Customer Sync Time', 'df-fincon' ),
      [ __CLASS__,'render_customer_schedule_time_field' ],
      self::OPTIONS_NAME_CUSTOMERS,
      self::OPTIONS_NAME
    );
    
    add_settings_field(
      'customer_sync_schedule_day',
      __( 'Customer Sync Day (Weekly only)', 'df-fincon' ),
      [ __CLASS__,'render_customer_schedule_day_field' ],
      self::OPTIONS_NAME_CUSTOMERS,
      self::OPTIONS_NAME
    );
  }

  public static function register_settings_orders(): void {
    register_setting( self::OPTIONS_GROUP_ORDERS, self::OPTIONS_NAME_ORDERS, [
      'sanitize_callback' => [ __CLASS__, 'sanitize_order_settings' ],
    ] );

    add_settings_section(
      self::OPTIONS_NAME,
      __( 'Order Sync Settings', 'df-fincon' ),
      null,
      self::OPTIONS_NAME_ORDERS
    );

    $fields = [
      'order_sync_enabled' => __( 'Enable Fincon Order Sync', 'df-fincon' ),
      'b2c_debt_account' => __( 'Fincon debt account for B2C/Guests', 'df-fincon' ),
    ];

    foreach ( $fields as $id => $label )
      add_settings_field( $id, $label, [ __CLASS__,'render_field' ], self::OPTIONS_NAME_ORDERS, self::OPTIONS_NAME, [ 'id' => $id, 'options_group' => self::OPTIONS_NAME_ORDERS ] );
    
    // Add order status sync options
    add_settings_field(
      'order_sync_status_processing',
      __( 'Sync on "Processing" status', 'df-fincon' ),
      [ __CLASS__,'render_order_status_processing_field' ],
      self::OPTIONS_NAME_ORDERS,
      self::OPTIONS_NAME
    );
    
    add_settings_field(
      'order_sync_status_completed',
      __( 'Sync on "Completed" status', 'df-fincon' ),
      [ __CLASS__,'render_order_status_completed_field' ],
      self::OPTIONS_NAME_ORDERS,
      self::OPTIONS_NAME
    );
  }

  public static function render_field( array $args ): void {
    // Determine which options group we're rendering for
    $options_group = $args['options_group'] ?? self::OPTIONS_NAME_API;
    
    if ( $options_group === self::OPTIONS_NAME_PRODUCTS ) :
      $options = ProductSync::get_options();
    elseif ( $options_group === self::OPTIONS_NAME_CUSTOMERS ) :
      $options = CustomerSync::get_options();
    elseif ( $options_group === self::OPTIONS_NAME_ORDERS ) :
      $options = OrderSync::get_options();
    else :
      $options = FinconApi::get_options();
    endif;
    
    $value = esc_attr( $options[ $args['id'] ] ?? '' );
    $type = in_array( $args['id'], ['username', 'password', 'data_id', 'server_url'] ) ? $args['id'] : 'text';
    $input_type = match ($args['id']) {
      'password' => 'password',
      'import_update_only_changed' => 'checkbox',
      'import_web_only' => 'checkbox',
      'product_cron_log_enabled' => 'checkbox',
      'customer_import_only_changed' => 'checkbox',
      'customer_weblist_only' => 'checkbox',
      'order_sync_enabled' => 'checkbox',
      'order_sync_status_processing' => 'checkbox',
      'order_sync_status_completed' => 'checkbox',
      default => 'text',
    };
    
    if ( $input_type === 'checkbox' ) : 
      $checked = ! empty( $options[ $args['id'] ] ) ? 'checked' : '';
      printf(
        '<input type="checkbox" name="%s[%s]" value="1" %s />',
        $options_group,
        $args['id'],
        $checked
      );
    else :
      printf(
        '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
        $input_type,
        $args['id'],
        $options_group,
        $args['id'],
        $value
      );
    endif;
  }

  public static function render_schedule_enabled_field(): void {
    $options = ProductSync::get_options();
    $checked = ! empty( $options['sync_schedule_enabled'] ) ? 'checked' : '';
    printf(
      '<input type="checkbox" id="sync_schedule_enabled" name="%s[sync_schedule_enabled]" value="1" %s />',
      self::OPTIONS_NAME_PRODUCTS,
      $checked
    );
    echo '<p class="description">' . esc_html__( 'Enable automatic product synchronization on a schedule.', 'df-fincon' ) . '</p>';
  }

  public static function render_schedule_frequency_field(): void {
    $options = ProductSync::get_options();
    $frequency = $options['sync_schedule_frequency'] ?? 'daily';
    $frequencies = [
      'every_5_minutes' => __( 'Every 5 Minutes (Testing)', 'df-fincon' ),
      'hourly' => __( 'Hourly', 'df-fincon' ),
      'daily' => __( 'Daily', 'df-fincon' ),
      'weekly' => __( 'Weekly', 'df-fincon' ),
    ];
    
    printf( '<select id="sync_schedule_frequency" name="%s[sync_schedule_frequency]">', self::OPTIONS_NAME_PRODUCTS );
    foreach ( $frequencies as $value => $label ) :
      $selected = selected( $frequency, $value, false );
      printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $selected, esc_html( $label ) );
    endforeach;
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'How often to run the product sync.', 'df-fincon' ) . '</p>';
  }

  public static function render_schedule_time_field(): void {
    $options = ProductSync::get_options();
    $time = $options['sync_schedule_time'] ?? '23:00';
    printf(
      '<input type="time" id="sync_schedule_time" name="%s[sync_schedule_time]" value="%s" />',
      self::OPTIONS_NAME_PRODUCTS,
      esc_attr( $time )
    );
    echo '<p class="description">' . esc_html__( 'Time to run the sync (for daily and weekly schedules).', 'df-fincon' ) . '</p>';
  }

  public static function render_schedule_day_field(): void {
    $options = ProductSync::get_options();
    $day = $options['sync_schedule_day'] ?? '1';
    $days = [
      '0' => __( 'Sunday', 'df-fincon' ),
      '1' => __( 'Monday', 'df-fincon' ),
      '2' => __( 'Tuesday', 'df-fincon' ),
      '3' => __( 'Wednesday', 'df-fincon' ),
      '4' => __( 'Thursday', 'df-fincon' ),
      '5' => __( 'Friday', 'df-fincon' ),
      '6' => __( 'Saturday', 'df-fincon' ),
    ];
    
    printf( '<select id="sync_schedule_day" name="%s[sync_schedule_day]">', self::OPTIONS_NAME_PRODUCTS );
    foreach ( $days as $value => $label ) :
      $selected = selected( $day, $value, false );
      printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $selected, esc_html( $label ) );
    endforeach;
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Day of the week to run the sync (weekly schedule only).', 'df-fincon' ) . '</p>';
  }

  public static function render_customer_schedule_enabled_field(): void {
    $options = CustomerSync::get_options();
    $checked = ! empty( $options['customer_sync_schedule_enabled'] ) ? 'checked' : '';
    printf(
      '<input type="checkbox" id="customer_sync_schedule_enabled" name="%s[customer_sync_schedule_enabled]" value="1" %s />',
      self::OPTIONS_NAME_CUSTOMERS,
      $checked
    );
    echo '<p class="description">' . esc_html__( 'Enable automatic customer synchronization on a schedule.', 'df-fincon' ) . '</p>';
  }

  public static function render_customer_schedule_frequency_field(): void {
    $options = CustomerSync::get_options();
    $frequency = $options['customer_sync_schedule_frequency'] ?? 'daily';
    $frequencies = [
      'every_5_minutes' => __( 'Every 5 Minutes (Testing)', 'df-fincon' ),
      'hourly' => __( 'Hourly', 'df-fincon' ),
      'daily' => __( 'Daily', 'df-fincon' ),
      'weekly' => __( 'Weekly', 'df-fincon' ),
    ];
    
    printf( '<select id="customer_sync_schedule_frequency" name="%s[customer_sync_schedule_frequency]">', self::OPTIONS_NAME_CUSTOMERS );
    foreach ( $frequencies as $value => $label ) :
      $selected = selected( $frequency, $value, false );
      printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $selected, esc_html( $label ) );
    endforeach;
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'How often to run the customer sync.', 'df-fincon' ) . '</p>';
  }

  public static function render_customer_schedule_time_field(): void {
    $options = CustomerSync::get_options();
    $time = $options['customer_sync_schedule_time'] ?? '23:00';
    printf(
      '<input type="time" id="customer_sync_schedule_time" name="%s[customer_sync_schedule_time]" value="%s" />',
      self::OPTIONS_NAME_CUSTOMERS,
      esc_attr( $time )
    );
    echo '<p class="description">' . esc_html__( 'Time to run the sync (for daily and weekly schedules).', 'df-fincon' ) . '</p>';
  }

  public static function render_customer_schedule_day_field(): void {
    $options = CustomerSync::get_options();
    $day = $options['customer_sync_schedule_day'] ?? '1';
    $days = [
      '0' => __( 'Sunday', 'df-fincon' ),
      '1' => __( 'Monday', 'df-fincon' ),
      '2' => __( 'Tuesday', 'df-fincon' ),
      '3' => __( 'Wednesday', 'df-fincon' ),
      '4' => __( 'Thursday', 'df-fincon' ),
      '5' => __( 'Friday', 'df-fincon' ),
      '6' => __( 'Saturday', 'df-fincon' ),
    ];
    
    printf( '<select id="customer_sync_schedule_day" name="%s[customer_sync_schedule_day]">', self::OPTIONS_NAME_CUSTOMERS );
    foreach ( $days as $value => $label ) :
      $selected = selected( $day, $value, false );
      printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $selected, esc_html( $label ) );
    endforeach;
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Day of the week to run the sync (weekly schedule only).', 'df-fincon' ) . '</p>';
  }

  /**
   * Sanitize API settings
   *
   * @param array $input Raw input data
   * @return array Sanitized data
   */
  public static function sanitize_api_settings( mixed $input ): array {
    if ( ! is_array( $input ) )
      $input = [];

    $existing = FinconApi::get_options();
    $sanitized = $existing;
    
    $sanitized['server_url'] = isset( $input['server_url'] ) ? sanitize_text_field( $input['server_url'] ) : '';
    $sanitized['server_port'] = isset( $input['server_port'] ) ? sanitize_text_field( $input['server_port'] ) : '';
    $sanitized['username'] = isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '';
    $sanitized['password'] = isset( $input['password'] ) ? sanitize_text_field( $input['password'] ) : '';
    $sanitized['data_id'] = isset( $input['data_id'] ) ? sanitize_text_field( $input['data_id'] ) : '';
    
    return $sanitized;
  }

  /**
   * Sanitize product settings and reinitialize cron schedule
   *
   * @param array $input Raw input data
   * @return array Sanitized data
   */
  public static function sanitize_product_settings( mixed $input ): array {
    if ( ! is_array( $input ) )
      $input = [];

    $existing = ProductSync::get_options();
    $sanitized = $existing;
    
    $sanitized['sync_schedule_enabled'] = isset( $input['sync_schedule_enabled'] ) ? 1 : 0;
    
    $allowed_frequencies = [ 'every_5_minutes', 'hourly', 'daily', 'weekly' ];
    $sanitized['sync_schedule_frequency'] = isset( $input['sync_schedule_frequency'] ) && in_array( $input['sync_schedule_frequency'], $allowed_frequencies ) ? $input['sync_schedule_frequency'] : 'daily';
    
    $sanitized['sync_schedule_time'] = isset( $input['sync_schedule_time'] ) ? sanitize_text_field( $input['sync_schedule_time'] ) : '23:00';
    
    $sanitized['sync_schedule_day'] = isset( $input['sync_schedule_day'] ) ? absint( $input['sync_schedule_day'] ) : 1;
    
    $sanitized['import_batch_size'] = isset( $input['import_batch_size'] ) ? absint( $input['import_batch_size'] ) : 100;
    $sanitized['import_update_only_changed'] = isset( $input['import_update_only_changed'] ) ? 1 : 0;
    $sanitized['import_web_only'] = isset( $input['import_web_only'] ) ? 1 : 0;
    $sanitized['product_cron_log_enabled'] = isset( $input['product_cron_log_enabled'] ) ? 1 : 0;

    // Reinitialize cron schedule if enabled
    if ( $sanitized['sync_schedule_enabled'] ) {
      Cron::create()->add_cron_schedule();
    } else {
      Cron::create()->clear_cron_schedule();
    }
    
    return $sanitized;
  }

  /**
   * Sanitize customer settings
   *
   * @param array $input Raw input data
   * @return array Sanitized data
   */
  public static function sanitize_customer_settings(  $input ): array {
    if ( ! is_array( $input ) ) 
      $input = [];

    $existing = CustomerSync::get_options();
    $sanitized = $existing;
    
    $sanitized['customer_batch_size'] = isset( $input['customer_batch_size'] ) ? absint( $input['customer_batch_size'] ) : 100;
    $sanitized['customer_import_only_changed'] = isset( $input['customer_import_only_changed'] ) ? 1 : 0;
    $sanitized['customer_weblist_only'] = isset( $input['customer_weblist_only'] ) ? 1 : 0;
    
    // New cron settings
    $sanitized['customer_sync_schedule_enabled'] = isset( $input['customer_sync_schedule_enabled'] ) ? 1 : 0;
    
    $allowed_frequencies = [ 'every_5_minutes', 'hourly', 'daily', 'weekly' ];
    $sanitized['customer_sync_schedule_frequency'] = isset( $input['customer_sync_schedule_frequency'] ) && in_array( $input['customer_sync_schedule_frequency'], $allowed_frequencies ) ? $input['customer_sync_schedule_frequency'] : 'daily';
    
    $sanitized['customer_sync_schedule_time'] = isset( $input['customer_sync_schedule_time'] ) ? sanitize_text_field( $input['customer_sync_schedule_time'] ) : '23:00';
    
    $sanitized['customer_sync_schedule_day'] = isset( $input['customer_sync_schedule_day'] ) ? absint( $input['customer_sync_schedule_day'] ) : 1;

    // Reinitialize cron schedule if enabled
    $cron = Cron::create();
    if ( $sanitized['customer_sync_schedule_enabled'] ) {
      $cron->add_customer_cron_schedule();
    } else {
      $cron->clear_customer_cron_schedule();
    }
    
    return $sanitized;
  }

  /**
   * Render order status processing field
   *
   * @return void
   * @since 1.0.0
   */
  public static function render_order_status_processing_field(): void {
    $options = OrderSync::get_options();
    $checked = ! empty( $options['order_sync_status_processing'] ) ? 'checked' : '';
    printf(
      '<input type="checkbox" id="order_sync_status_processing" name="%s[order_sync_status_processing]" value="1" %s />',
      self::OPTIONS_NAME_ORDERS,
      $checked
    );
    echo '<p class="description">' . esc_html__( 'Sync orders to Fincon when order status changes to "Processing".', 'df-fincon' ) . '</p>';
  }

  /**
   * Render order status completed field
   *
   * @return void
   * @since 1.0.0
   */
  public static function render_order_status_completed_field(): void {
    $options = OrderSync::get_options();
    $checked = ! empty( $options['order_sync_status_completed'] ) ? 'checked' : '';
    printf(
      '<input type="checkbox" id="order_sync_status_completed" name="%s[order_sync_status_completed]" value="1" %s />',
      self::OPTIONS_NAME_ORDERS,
      $checked
    );
    echo '<p class="description">' . esc_html__( 'Sync orders to Fincon when order status changes to "Completed".', 'df-fincon' ) . '</p>';
  }

  /**
   * Sanitize order settings
   *
   * @param array $input Raw input data
   * @return array Sanitized data
   */
  public static function sanitize_order_settings( $input ): array {
    if ( ! is_array( $input ) )
      $input = [];
    $existing = OrderSync::get_options();
    $sanitized = $existing;
    
    $sanitized['order_sync_enabled'] = isset( $input['order_sync_enabled'] ) ? 1 : 0;
    $sanitized['b2c_debt_account'] = isset( $input['b2c_debt_account'] ) ? sanitize_text_field( $input['b2c_debt_account'] ) : '';
    $sanitized['order_sync_status_processing'] = isset( $input['order_sync_status_processing'] ) ? 1 : 0;
    $sanitized['order_sync_status_completed'] = isset( $input['order_sync_status_completed'] ) ? 1 : 0;
    
    return $sanitized;
  }

  public static function enqueue_scripts( string $hook ): void {
    // Only enqueue on our plugin pages and user edit pages
    $is_plugin_page = strpos( $hook, 'df-fincon' ) !== false;
    $is_user_page = in_array( $hook, [ 'user-edit.php', 'profile.php' ], true );
    
    if ( ! $is_plugin_page && ! $is_user_page )
      return;
    
    // Use file modification time for cache busting
    $admin_js_path = DF_FINCON_PLUGIN_DIR . 'assets/js/admin.js';
    $version = file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : DF_FINCON_VERSION;
    
    wp_enqueue_script(
      'df-fincon-admin',
      DF_FINCON_PLUGIN_URL . 'assets/js/admin.js',
      [ 'jquery' ],
      $version,
      true
    );
    
    wp_localize_script( 'df-fincon-admin', 'DF_FINCON_ADMIN', [
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'nonces' => [
        'test' => wp_create_nonce( self::TEST_NONCE ),
        'import' => wp_create_nonce( self::IMPORT_NONCE ),
        'stock_locations' => wp_create_nonce( self::STOCK_LOCATIONS_NONCE ),
        'sync_user' => wp_create_nonce( self::SYNC_USER_NONCE ),
      ],
      'messages' => [
        'importing' => __( 'Importing...', 'df-fincon' ),
        'import_failed' => __( 'Import failed', 'df-fincon' ),
        'network_error' => __( 'Network error occurred', 'df-fincon' ),
        'customer_importing' => __( 'Importing customers', 'df-fincon' ),
        'generic_error' => __( 'An error occurred', 'df-fincon' ),
        'syncing' => __( 'Syncing with Fincon...', 'df-fincon' ),
      ],
    ] );
  }

  public static function enqueue_styles( string $hook ): void {
    // Only enqueue on our plugin pages 
    $is_plugin_page = strpos( $hook, 'df-fincon' ) !== false;
    
    if ( ! $is_plugin_page )
      return;

    // Use file modification time for cache busting
    $admin_css_path = DF_FINCON_PLUGIN_DIR . 'assets/css/admin.css';
    $version = file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : DF_FINCON_VERSION;
    
    wp_enqueue_style(
      'df-fincon-admin',
      DF_FINCON_PLUGIN_URL . 'assets/css/admin.css',
      [ ],
      $version
    );
    
  }

  public static function render_settings_page(): void {
    require_once self::TEMPLATE_PATH . 'dashboard.php';
  }

  public static function render_plugin_settings_page(): void {
    require_once self::TEMPLATE_PATH . 'settings.php';
  }

  public static function render_api_settings_page(): void {
    require_once self::TEMPLATE_PATH . 'api-settings.php';
  }

  public static function render_import_page(): void {
    require_once self::TEMPLATE_PATH . 'product-import.php';
  }

  public static function render_customer_import_page(): void {
    require_once self::TEMPLATE_PATH . 'customer-import.php';
  }

  public static function render_cron_log_page(): void {
    // Handle clear log action
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'clear_log' && isset( $_GET['_wpnonce'] ) ) {
      if ( wp_verify_nonce( $_GET['_wpnonce'], 'clear_cron_log' ) ) {
        Cron::clear_logs();
        wp_safe_redirect( remove_query_arg( [ 'action', '_wpnonce' ] ) );
        exit;
      }
    }
    
    // Get cron logs
    $logs = Cron::get_logs();
    
    // Pass logs to template
    require_once self::TEMPLATE_PATH . 'cron-log.php';
  }

  public static function render_cron_log_products_page(): void {
    // Handle file deletion action
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_log' && isset( $_GET['file'] ) && isset( $_GET['_wpnonce'] ) ) {
      if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_product_cron_log' ) ) {
        $cron_logger = ProductCronLogger::create();
        $file_name = sanitize_text_field( $_GET['file'] );
        $deleted = $cron_logger->delete_log_file( $file_name );
        
        if ( $deleted ) {
          add_settings_error(
            'df_fincon_cron_log_products',
            'log_deleted',
            sprintf( __( 'Log file "%s" deleted successfully.', 'df-fincon' ), $file_name ),
            'success'
          );
        } else {
          add_settings_error(
            'df_fincon_cron_log_products',
            'log_delete_failed',
            sprintf( __( 'Failed to delete log file "%s".', 'df-fincon' ), $file_name ),
            'error'
          );
        }
        
        wp_safe_redirect( remove_query_arg( [ 'action', 'file', '_wpnonce' ] ) );
        exit;
      }
    }
    
    // Handle clear all logs action
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'clear_all_logs' && isset( $_GET['_wpnonce'] ) ) {
      if ( wp_verify_nonce( $_GET['_wpnonce'], 'clear_all_product_cron_logs' ) ) {
        $cron_logger = ProductCronLogger::create();
        $results = $cron_logger->delete_all_log_files();
        
        if ( ! empty( $results['deleted'] ) ) {
          add_settings_error(
            'df_fincon_cron_log_products',
            'logs_cleared',
            sprintf( __( 'Deleted %d log file(s).', 'df-fincon' ), count( $results['deleted'] ) ),
            'success'
          );
        }
        
        if ( ! empty( $results['errors'] ) ) {
          add_settings_error(
            'df_fincon_cron_log_products',
            'logs_clear_errors',
            sprintf( __( 'Failed to delete %d log file(s).', 'df-fincon' ), count( $results['errors'] ) ),
            'error'
          );
        }
        
        wp_safe_redirect( remove_query_arg( [ 'action', '_wpnonce' ] ) );
        exit;
      }
    }
    
    // Get log files
    $cron_logger = ProductCronLogger::create();
    $log_files = $cron_logger->get_log_files();
    
    // Get current log file content if viewing
    $current_log_content = '';
    $current_log_file = '';
    
    if ( isset( $_GET['view'] ) && ! empty( $_GET['view'] ) ) {
      $file_name = sanitize_text_field( $_GET['view'] );
      $current_log_content = $cron_logger->read_log_file( $file_name );
      $current_log_file = $file_name;
    }
    
    // Check if logging is enabled
    $logging_enabled = $cron_logger->is_enabled();
    
    // Pass data to template
    require_once self::TEMPLATE_PATH . 'cron-log-products.php';
  }

  public static function render_stock_locations_page(): void {
    $action = sanitize_text_field( $_GET['action'] ?? '' );
    $location_code = sanitize_text_field( $_GET['code'] ?? '' );
    
    // Show edit form for add/edit actions
    if ( in_array( $action, [ 'add', 'edit' ] ) ) {
      self::render_stock_location_edit_form( $action, $location_code );
      return;
    }
    
    // Default: show locations list
    self::render_stock_locations_list();
  }

  /**
   * Render the stock location edit form
   *
   * @param string $action 'add' or 'edit'
   * @param string $location_code Location code for edit mode
   * @return void
   */
  private static function render_stock_location_edit_form( string $action, string $location_code ): void {
    $is_edit = ( $action === 'edit' );
    $location = [];
    $errors = [];
    
    // Load location data for edit mode
    if ( $is_edit && $location_code ) {
      $location_manager = LocationManager::create();
      $location = $location_manager->get_location( $location_code );
      
      if ( empty( $location ) ) {
        wp_die( __( 'Location not found', 'df-fincon' ) );
      }
    }
    
    // Include the edit template
    require_once self::TEMPLATE_PATH . 'stock-location-edit.php';
  }

  /**
   * Render the stock locations list
   *
   * @return void
   */
  private static function render_stock_locations_list(): void {
    $location_manager = LocationManager::create();
    $locations = $location_manager->get_all_locations();
    
    // Check for messages from previous operations
    $message = '';
    $message_type = 'success';
    
    if ( ! empty( $_GET['message'] ) ) {
      $message = sanitize_text_field( $_GET['message'] );
      $message_type = sanitize_text_field( $_GET['message_type'] ?? 'success' );
    }
    
    // Include the list template
    require_once self::TEMPLATE_PATH . 'stock-locations.php';
  }

  public static function render_invoice_management_page(): void {
    require_once self::TEMPLATE_PATH . 'invoice-management.php';
  }

  public static function ajax_test_connection(): void {
    check_ajax_referer( self::TEST_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $fincon_api = new FinconApi();
    $result = $fincon_api->test_login();
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [
        'message' => $result->get_error_message(),
        'code' => $result->get_error_code(),
      ] );
    } else {
      wp_send_json_success( [
        'message' => __( 'Connection successful!', 'df-fincon' ),
        'connect_id' => $result,
      ] );
    }
  }

  public static function ajax_manual_import_products(): void {
    check_ajax_referer( self::IMPORT_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    // Determine if single product import or batch import
    $product_item_code = sanitize_text_field( $_POST['product_item_code'] ?? '' );
    $count = absint( $_POST['count'] ?? 0 );
    $resume = ! empty( $_POST['resume'] );
    
    if ( ! empty( $product_item_code ) ) {
      // Single product import
      $result = FinconService::import_product( $product_item_code );
      $message = __( 'Single product import completed.', 'df-fincon' );
    } else {
      // Batch import
      $result = FinconService::import_products( $count, $resume );
      $message = sprintf( __( 'Product import completed. %d products processed.', 'df-fincon' ), $result['total_processed'] ?? 0 );
    }
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [
        'message' => $result->get_error_message(),
        'code' => $result->get_error_code(),
      ] );
    } else {
      wp_send_json_success( [
        'message' => $message,
        'data' => $result,
      ] );
    }
  }

  public static function ajax_reset_import_progress(): void {
    check_ajax_referer( self::IMPORT_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    ProductSync::reset_manual_import_progress();
    
    wp_send_json_success( [
      'message' => __( 'Import progress has been reset.', 'df-fincon' ),
    ] );
  }

  public static function ajax_manual_import_customers(): void {
    check_ajax_referer( self::IMPORT_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    // Debug: Log ALL POST parameters for investigation
    Logger::debug( 'ALL POST parameters received:', $_POST );
    
    // Debug: Log received parameters
    $customer_accno = sanitize_text_field( $_POST['customer_accno'] ?? '' );
    $count = absint( $_POST['count'] ?? 0 );
    $offset = absint( $_POST['offset'] ?? 0 );
    $only_changed = ! empty( $_POST['only_changed'] );
    $weblist_only = ! empty( $_POST['webListOnly'] );
    
    Logger::debug( 'Parsed manual customer import parameters:', [
      'customer_accno' => $customer_accno,
      'count' => $count,
      'offset' => $offset,
      'only_changed' => $only_changed,
      'weblist_only' => $weblist_only,
      'POST_keys' => array_keys( $_POST ),
    ] );
    
    // If specific AccNo provided, import only that customer
    if ( ! empty( $customer_accno ) ) {
      Logger::info( sprintf( 'Manual import for specific customer AccNo: %s', $customer_accno ) );
      $result = FinconService::import_customer_by_accno( $customer_accno );
    } else {
      // Otherwise do batch import with provided parameters
      Logger::info( 'Manual batch customer import - no AccNo provided' );
      
      // Use count from form, default to 50 if not provided
      $requested_count = $count > 0 ? $count : 50;
      $requested_offset = $offset;
      
      Logger::debug( 'Manual batch import parameters:', [
        'requested_count' => $requested_count,
        'requested_offset' => $requested_offset,
        'only_changed' => $only_changed,
        'weblist_only' => $weblist_only,
      ] );
      
      // For manual imports, we want to skip creation if missing (only update existing)
      $result = FinconService::import_customers( $requested_count, $requested_offset, $only_changed, $weblist_only, false );
    }
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [
        'message' => $result->get_error_message(),
        'code' => $result->get_error_code(),
      ] );
    } else {
      // Handle different response formats
      $imported_count = $result['imported_count'] ?? $result['total_processed'] ?? 0;
      $message = sprintf( __( 'Customer import completed. %d customers processed.', 'df-fincon' ), $imported_count );
      
      wp_send_json_success( [
        'message' => $message,
        'data' => $result,
      ] );
    }
  }

  public static function ajax_manual_sync_order(): void {
    check_ajax_referer( 'df_fincon_manual_sync_order', 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $order_id = absint( $_POST['order_id'] ?? 0 );
    
    if ( ! $order_id ) {
      wp_send_json_error( [ 'message' => __( 'Invalid order ID', 'df-fincon' ) ] );
    }
    
    $order_sync = OrderSync::create();
    $result = $order_sync->sync_order( $order_id );
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [
        'message' => $result->get_error_message(),
        'code' => $result->get_error_code(),
      ] );
    } else {
      wp_send_json_success( [
        'message' => __( 'Order synced successfully to Fincon', 'df-fincon' ),
        'data' => $result,
      ] );
    }
  }

  public static function ajax_sync_user_by_accno(): void {
    check_ajax_referer( self::SYNC_USER_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $user_id = absint( $_POST['user_id'] ?? 0 );
    
    if ( ! $user_id ) {
      wp_send_json_error( [ 'message' => __( 'Invalid user ID', 'df-fincon' ) ] );
    }
    
    // Get the user's AccNo from meta
    $accno = get_user_meta( $user_id, CustomerSync::META_ACCNO, true );
    
    if ( empty( $accno ) ) {
      wp_send_json_error( [ 'message' => __( 'User does not have a Fincon AccNo', 'df-fincon' ) ] );
    }
    
    // Import the customer using existing functionality
    $result = FinconService::import_customer_by_accno( $accno );
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [
        'message' => $result->get_error_message(),
        'code' => $result->get_error_code(),
      ] );
    } else {
      $imported_count = $result['imported_count'] ?? $result['total_processed'] ?? 0;
      $message = sprintf( __( 'Customer sync completed. %d customer(s) processed.', 'df-fincon' ), $imported_count );
      
      wp_send_json_success( [
        'message' => $message,
        'data' => $result,
      ] );
    }
  }

  public static function ajax_download_pdf(): void {
    $order_id = absint( $_GET['order_id'] ?? 0 );
    $nonce = sanitize_text_field( $_GET['nonce'] ?? '' );
    
    if ( ! $order_id || ! wp_verify_nonce( $nonce, 'df_fincon_download_pdf_' . $order_id ) ) {
      wp_die( 'Invalid request', 403 );
    }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
      wp_die( 'Order not found', 404 );
    }
    
    $pdf_path = $order->get_meta( OrderSync::META_PDF_PATH );
    if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
      wp_die( 'PDF not found', 404 );
    }
    
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="fincon-invoice-' . $order_id . '.pdf"' );
    readfile( $pdf_path );
    exit;
  }

  public static function ajax_fetch_pdf(): void {
    check_ajax_referer( 'df_fincon_fetch_pdf', 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $order_id = absint( $_POST['order_id'] ?? 0 );
    $doc_no = sanitize_text_field( $_POST['doc_no'] ?? '' );
    
    if ( ! $order_id || ! $doc_no ) {
      wp_send_json_error( [ 'message' => __( 'Invalid parameters', 'df-fincon' ) ] );
    }
    
    try {
      $pdf_storage = PdfStorage::create();
      $result = $pdf_storage->fetch_and_save_pdf( $order_id, 'I', $doc_no );
      
      if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
      } else {
        wp_send_json_success( [ 'message' => __( 'PDF fetched and saved successfully', 'df-fincon' ) ] );
      }
    } catch ( \Exception $e ) {
      wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
  }

  public static function ajax_stock_location_save(): void {
    check_ajax_referer( self::STOCK_LOCATIONS_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $location_manager = LocationManager::create();
    
    // Extract location data from nested 'location' array
    $location_data = $_POST['location'] ?? [];
    $original_code = sanitize_text_field( $_POST['original_code'] ?? '' );
    
    // Determine if this is an add or update
    $code = sanitize_text_field( $location_data['code'] ?? '' );
    $is_update = ! empty( $original_code ) && $location_manager->location_exists( $original_code );
    
    if ( $is_update ) {
      // For updates, use original_code as the key to update
      $result = $location_manager->update_location( $original_code, $location_data );
    } else {
      $result = $location_manager->add_location( $location_data );
    }
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    } else {
      wp_send_json_success( [ 'message' => __( 'Location saved successfully', 'df-fincon' ) ] );
    }
  }

  public static function ajax_stock_location_delete(): void {
    check_ajax_referer( self::STOCK_LOCATIONS_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $location_code = sanitize_text_field( $_POST['location_code'] ?? '' );
    
    if ( ! $location_code ) {
      wp_send_json_error( [ 'message' => __( 'Invalid location code', 'df-fincon' ) ] );
    }
    
    $location_manager = LocationManager::create();
    $result = $location_manager->delete_location( $location_code );
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    } else {
      wp_send_json_success( [ 'message' => __( 'Location deleted successfully', 'df-fincon' ) ] );
    }
  }

  public static function ajax_stock_location_set_default(): void {
    check_ajax_referer( self::STOCK_LOCATIONS_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $location_code = sanitize_text_field( $_POST['location_code'] ?? '' );
    
    if ( ! $location_code ) {
      wp_send_json_error( [ 'message' => __( 'Invalid location code', 'df-fincon' ) ] );
    }
    
    $location_manager = LocationManager::create();
    $result = $location_manager->set_default_location( $location_code );
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    } else {
      wp_send_json_success( [ 'message' => __( 'Default location updated successfully', 'df-fincon' ) ] );
    }
  }

  public static function ajax_stock_location_toggle_active(): void {
    check_ajax_referer( self::STOCK_LOCATIONS_NONCE, 'nonce' );
    
    if ( ! current_user_can( 'manage_woocommerce' ) )
      wp_die( 'Unauthorized', 403 );
    
    $location_code = sanitize_text_field( $_POST['location_code'] ?? '' );
    $active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : false;
    
    if ( ! $location_code ) {
      wp_send_json_error( [ 'message' => __( 'Invalid location code', 'df-fincon' ) ] );
    }
    
    $location_manager = LocationManager::create();
    $result = $location_manager->toggle_location_active( $location_code, $active );
    
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    } else {
      wp_send_json_success( [ 'message' => __( 'Location status updated successfully', 'df-fincon' ) ] );
    }
  }
}