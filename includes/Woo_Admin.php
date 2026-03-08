<?php
  /**
   * WooCommerce Admin Functionality
   *
   * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
   * @package df-fincon-connector
   * Text Domain: df-fincon
   * */

  namespace DF_FINCON;

  use TypeError;
  
  // Exit if accessed directly.
  if ( ! defined( 'ABSPATH' ) )
    exit;

class Woo_Admin {

  /**
   * Instance
   * @var self
   */
  private static ?self $instance = null;
  

  /**
   * PRODUCT meta field keys
   */

  private const PRODUCT_LAST_SYNC_META_KEY = ProductSync::FINCON_PRODUCT_CHANGED_META_KEY;

  private const PRODUCT_LAST_SYNC_DATETIME_META_KEY = ProductSync::LAST_SYNC_DATETIME_META_KEY;

  private const PRODUCT_META_FINCON_DATA = ProductSync::PRODUCT_META_FINCON_DATA;

  private const PRODUCT_META_SELLING_PRICE = ProductSync::PRODUCT_META_SELLING_PRICES;

  // PRODUCT_META_STOCK is now dynamic via ProductSync::get_stock_meta_mapping()

  /**
   * USER meta field keys
   */
  private const USER_ROLE_RETAIL = CustomerSync::ROLE_RETAIL;
  private const USER_ROLE_DEALER = CustomerSync::ROLE_DEALER;
  private const USER_META_ACCNO = CustomerSync::META_ACCNO;
  private const USER_META_PRICE_STRUCTURE = CustomerSync::META_PRICE_STRUCTURE;
  
  private const USER_META_ON_HOLD = CustomerSync::META_ON_HOLD;
  private const USER_META_CHANGED_TIMESTAMP = CustomerSync::META_CHANGED_TIMESTAMP;
  private const USER_META_LAST_SYNC_TIMESTAMP = CustomerSync::META_LAST_SYNC_TIMESTAMP;
  
  /**
   * Price list history meta keys
   */
  private const USER_META_PRICE_LIST_HISTORY = '_fincon_price_list_history';
  private const USER_META_PRICE_LIST_OVERRIDE = '_fincon_price_list_override';
  private const USER_META_PRICE_LIST_OVERRIDE_REASON = '_fincon_price_list_override_reason';
  private const USER_META_PRICE_LIST_OVERRIDE_DATE = '_fincon_price_list_override_date';

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
    self::register_actions_products();
    self::register_actions_users();
    self::register_actions_orders();
    self::register_filters_products();
    self::register_filters_users();
    
    // Add admin styles
    add_action( 'admin_head', [ __CLASS__, 'add_order_list_styles' ] );
  }

  private static function register_actions_products(): void {
    add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'display_fincon_product_data_panel' ] );
    add_action( 'woocommerce_product_options_pricing', [__CLASS__, 'display_selling_prices_fields']);
    add_action( 'woocommerce_admin_process_product_object', [__CLASS__, 'save_selling_prices_fields']);
    add_action( 'woocommerce_product_options_inventory_product_data', [__CLASS__, 'display_stock_fields']);
    add_action( 'woocommerce_admin_process_product_object', [__CLASS__, 'save_stock_fields' ]);
    add_action( 'woocommerce_product_options_pricing', [__CLASS__, 'display_price_list_prices']);
  }

  private static function register_actions_users(): void {
    add_action( 'init', [ __CLASS__, 'register_roles' ] );
    add_action( 'show_user_profile', [ __CLASS__, 'display_fincon_customer_data' ], 20 );
    add_action( 'edit_user_profile', [ __CLASS__, 'display_fincon_customer_data' ], 20 );
    add_action( 'personal_options_update', [ __CLASS__, 'save_user_fields' ] );
    add_action( 'edit_user_profile_update', [ __CLASS__, 'save_user_fields' ] );
  }

  private static function register_actions_orders() {
    add_action( 'add_meta_boxes', [ __CLASS__, 'add_order_meta_boxes' ] );
    
    // Order list view enhancements - Traditional CPT
    add_action( 'manage_shop_order_posts_custom_column', [ __CLASS__, 'display_order_price_list_column' ], 20, 2 );
    add_action( 'manage_shop_order_posts_custom_column', [ __CLASS__, 'display_order_fincon_columns' ], 20, 2 );
    add_filter( 'manage_edit-shop_order_columns', [ __CLASS__, 'add_order_fincon_columns' ] );
    add_filter( 'manage_edit-shop_order_sortable_columns', [ __CLASS__, 'add_order_price_list_sortable_column' ] );
    
    // Order list view enhancements - HPOS
    add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ __CLASS__, 'display_order_price_list_column_hpos' ], 20, 2 );
    add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ __CLASS__, 'display_order_fincon_columns_hpos' ], 20, 2 );
    add_filter( 'manage_woocommerce_page_wc-orders_columns', [ __CLASS__, 'add_order_fincon_columns' ] );
    add_filter( 'manage_woocommerce_page_wc-orders_sortable_columns', [ __CLASS__, 'add_order_price_list_sortable_column_hpos' ] );
    
    add_filter( 'default_hidden_columns', [ __CLASS__, 'default_hidden_order_columns' ], 10, 2 );
    add_action( 'restrict_manage_posts', [ __CLASS__, 'add_price_list_filter_dropdown' ], 20 );
    add_action( 'restrict_manage_posts', [ __CLASS__, 'add_fincon_order_search_field' ], 20 );
    add_filter( 'request', [ __CLASS__, 'filter_orders_by_price_list' ] );
    add_filter( 'request', [ __CLASS__, 'filter_orders_by_fincon_order' ] );
    add_filter( 'posts_join', [ __CLASS__, 'filter_orders_by_price_list_join' ], 10, 2 );
    add_filter( 'posts_where', [ __CLASS__, 'filter_orders_by_price_list_where' ], 10, 2 );
    add_filter( 'posts_join', [ __CLASS__, 'filter_orders_by_fincon_order_join' ], 10, 2 );
    add_filter( 'posts_where', [ __CLASS__, 'filter_orders_by_fincon_order_where' ], 10, 2 );
  }

  private static function register_filters_products(): void {
    add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'add_fincon_product_data_tab' ] );
  }

  private static function register_filters_users(): void {
    add_filter( 'woocommerce_customer_meta_fields', [ __CLASS__, 'register_customer_meta_fields' ] );
    // User list columns
    add_filter( 'manage_users_columns', [ __CLASS__, 'add_user_columns' ] );
    add_filter( 'manage_users_custom_column', [ __CLASS__, 'display_user_column' ], 10, 3 );
    // Search
    add_filter( 'pre_get_users', [ __CLASS__, 'extend_user_search' ] );
  }

  /**
   * Adds the new "FinCon" tab to the WooCommerce Product Data metabox.
   *
   * @param array $product_data_tabs Existing tabs array.
   * @return array Modified tabs array.
   */
  public static function add_fincon_product_data_tab( array $product_data_tabs ): array {
    $product_data_tabs['fincon_sync'] = array(
      'label'    => __( 'FinCon Data', 'df-fincon' ),
      'target'   => 'fincon_product_data',
      'class'    => array( 'show_if_simple', 'show_if_variable' ),
      'priority' => 80, 
    );
    return $product_data_tabs;
  }

  /**
   * Displays the content within the new "FinCon" product data panel.
   * This is where we display the sync metadata.
   */
  public static function display_fincon_product_data_panel(): void {
    global $post;
    
    $product = wc_get_product( $post->ID );
    
    $fincon_timestamp = $product->get_meta( self::PRODUCT_LAST_SYNC_META_KEY, true );
    $sync_timestamp = $product->get_meta( self::PRODUCT_LAST_SYNC_DATETIME_META_KEY, true );
    $item_no = $product->get_meta( "ItemNo", true );

    echo '<div id="fincon_product_data" class="panel woocommerce_options_panel">';
    echo '<div class="options_group">';
    echo '<h3>' . __( 'FinCon Synchronization Data', 'df-fincon' ) . '</h3>';
        
    if ( ! empty( $fincon_timestamp ) ) {
        $formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fincon_timestamp );
        
        printf(
            esc_html__( 'FinCon product changed timestamp: %s', 'df-fincon' ),
            '<strong>' . esc_html( $formatted_date ) . '</strong>'
        );
        
    } else {
        echo '<p>' . esc_html__( 'This product has not yet been synchronized with the FinCon API.', 'df-fincon' ) . '</p>';
    }
    echo '<br/><br/>';
    if ( ! empty( $sync_timestamp ) ) {
        $formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sync_timestamp );
        
        printf(
            esc_html__( 'Product last synchronized: %s', 'df-fincon' ),
            '<strong>' . esc_html( $formatted_date ) . '</strong>'
        );
    } else {
        echo '<p>' . esc_html__( 'This product has not yet been synchronized by the plugin.', 'df-fincon' ) . '</p>';
    }

echo '<h3>' . __( 'FinCon Data', 'df-fincon' ) . '</h3>';
    echo '<table class="widefat striped fincon-meta-table">';
    echo '<tbody>';

    foreach ( self::PRODUCT_META_FINCON_DATA as $meta_key => $label ) {
        $value = $product->get_meta( $meta_key );
        if ( $value !== '' ) {
            echo '<tr>';
            echo '<th>' . esc_html( $label ) . '</th>';
            echo '<td>' . esc_html( $value ) . '</td>';
            echo '</tr>';
        }
    }
  
    echo '</tbody>';
    echo '</table>';

    echo '</div>'; // .options_group
    echo '</div>'; // .panel
  }

  public static function display_selling_prices_fields(): void {
    echo '<div class="options_group">';
      foreach ( self::PRODUCT_META_SELLING_PRICE as $field_id => $label ):
        woocommerce_wp_text_input( array(
          'id'                => $field_id,
          'label'             => __( $label, 'df-fincon' ),
          'desc_tip'          => true,
          'type'              => 'number',
          'custom_attributes' => array(
              'step' => '0.01',
              'min'  => '0',
          ),
        ) );
      endforeach;
    echo '</div>';
  }

  /**
   * Display all 6 price list prices as read-only fields on product edit screen
   *
   * @return void
   * @since 1.0.0
   */
  public static function display_price_list_prices(): void {
    global $post;
    
    if ( ! $post || ! $post->ID )
      return;
    
    $product = wc_get_product( $post->ID );
    if ( ! $product )
      return;
    
    // Get currency symbol and format
    $currency_symbol = get_woocommerce_currency_symbol();
    $price_format = get_woocommerce_price_format();
    
    echo '<div class="options_group">';
    echo '<h3>' . esc_html__( 'FinCon Price Lists', 'df-fincon' ) . '</h3>';
    
    // Display all 6 price lists
    for ( $i = 1; $i <= 6; $i++ ) :
      $price_key = "SellingPrice{$i}";
      $price_value = $product->get_meta( $price_key, true );
      $formatted_price = $price_value ? wc_price( $price_value ) : '—';
      
      // Check for promotional price using CustomerSync helper functions
      $promo_price_key = CustomerSync::get_promo_price_meta_key( $i );
      $promo_flag_key = CustomerSync::get_promo_flag_meta_key( $i );
      $promo_price = $product->get_meta( $promo_price_key, true );
      $promo_active = $product->get_meta( $promo_flag_key, true ) === 'yes';
      
      // Get promotional dates if available
      $promo_from_date = $product->get_meta( 'ProFromDate', true );
      $promo_to_date = $product->get_meta( 'ProToDate', true );
      
      echo '<div class="price-list-row">';
      echo '<p class="form-field">';
      echo '<label>' . esc_html__( "Price List {$i}", 'df-fincon' ) . '</label>';
      echo '<span class="price-value">' . $formatted_price . '</span>';
      
      // Show promotional price if active (ignore price list 1 as it's the default WooCommerce sale price)
      if ( $i !== 1 && $promo_active && $promo_price ) :
        $formatted_promo_price = wc_price( $promo_price );
        echo '<span class="promo-price">' . esc_html__( 'Promotional: ', 'df-fincon' ) . $formatted_promo_price . '</span>';
        
        // Show promotional period if available
        if ( $promo_from_date || $promo_to_date ) :
          $from_date_formatted = $promo_from_date ? date_i18n( get_option( 'date_format' ), strtotime( substr( $promo_from_date, 0, 4 ) . '-' . substr( $promo_from_date, 4, 2 ) . '-' . substr( $promo_from_date, 6, 2 ) ) ) : '';
          $to_date_formatted = $promo_to_date ? date_i18n( get_option( 'date_format' ), strtotime( substr( $promo_to_date, 0, 4 ) . '-' . substr( $promo_to_date, 4, 2 ) . '-' . substr( $promo_to_date, 6, 2 ) ) ) : '';
          
          if ( $from_date_formatted && $to_date_formatted ) :
            echo '<span class="promo-period">' . sprintf( esc_html__( '(%s - %s)', 'df-fincon' ), $from_date_formatted, $to_date_formatted ) . '</span>';
          elseif ( $from_date_formatted ) :
            echo '<span class="promo-period">' . sprintf( esc_html__( '(From %s)', 'df-fincon' ), $from_date_formatted ) . '</span>';
          endif;
        endif;
      endif;
      
      echo '</p>';
      echo '</div>';
    endfor;
    
    echo '</div>';
    
    // Add some CSS for styling
    echo '<style>
      .price-list-row .form-field {
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 10px;
      }
      .price-list-row .form-field:last-child {
        border-bottom: none;
      }
      .price-list-row .price-value {
        font-weight: bold;
        font-size: 14px;
        color: #333;
        display: block;
        margin-top: 5px;
      }
      .price-list-row .promo-price {
        display: block;
        color: #d63638;
        font-weight: bold;
        margin-top: 3px;
      }
      .price-list-row .promo-period {
        display: block;
        color: #666;
        font-size: 12px;
        font-style: italic;
        margin-top: 2px;
      }
    </style>';
  }

  public static function save_selling_prices_fields( $product ): void {
    foreach ( self::PRODUCT_META_SELLING_PRICE as $field_id => $label ): 
      $value    = isset( $_POST[ $field_id ] ) ? wc_clean( wp_unslash( $_POST[ $field_id ] ) ) : '';
      if ( $value !== '' ) :
        $product->update_meta_data( $field_id, $value );
      else :
        $product->delete_meta_data( $field_id );
      endif;
    endforeach;
  }


  public static function display_stock_fields(): void {
    echo '<div class="options_group">';

    $stock_mapping = ProductSync::get_stock_meta_mapping();
    foreach ( $stock_mapping as $location => $field ) :
      reset($field);
      $field_id = key($field);
      $label = current($field);
      woocommerce_wp_text_input( [
        'id'                => $field_id,
        'label'             => __( $label, 'df-fincon' ),
        'type'              => 'number',
        'desc_tip'          => true,
        'custom_attributes' => [ 'step' => '1', 'min' => '0' ],
      ] );
    endforeach;

    echo '</div>';

  }

  /**
   * Save the custom stock fields
   */
  public static function save_stock_fields( $product ) : void {
    $stock_mapping = ProductSync::get_stock_meta_mapping();
    foreach ( $stock_mapping as $location => $field ) :
      reset($field);
      $field_id = key($field);
      $label = current($field);
      if ( isset( $_POST[ $field_id ] ) ) :
        $value = wc_clean( wp_unslash( $_POST[ $field_id ] ) );
        $product->update_meta_data( $field_id, $value );
      endif;
    endforeach;
  }

/**
   * Register custom client type roles (Retail / Dealer)
   */
  public static function register_roles(): void {
    $customer_role = get_role( 'customer' );
    $capabilities = $customer_role ? $customer_role->capabilities : [
      'read' => true,
      'edit_posts' => false,
      'delete_posts' => false,
    ];

    if ( ! get_role( self::USER_ROLE_DEALER ) )
      add_role( self::USER_ROLE_DEALER, __( 'Client Type: Dealer (B2B)', 'df-fincon' ), $capabilities );
  }
  
  /**
   * Save custom FinCon fields from user profile.
   */
  public static function save_user_fields( int $user_id ): void {
    if ( ! current_user_can( 'edit_user', $user_id ) )
      return;

    if ( isset( $_POST[ self::USER_META_ACCNO ] ) ) {
      update_user_meta( $user_id, self::USER_META_ACCNO, sanitize_text_field( wp_unslash( $_POST[ self::USER_META_ACCNO ] ) ) );
    }

    if ( isset( $_POST[ self::USER_META_PRICE_STRUCTURE ] ) ) {
      update_user_meta( $user_id, self::USER_META_PRICE_STRUCTURE, (int) $_POST[ self::USER_META_PRICE_STRUCTURE ] );
    }

  }

  /**
   * Display FinCon customer synchronization data on user profile edit screen
   *
   * @param \WP_User $user User object
   * @return void
   * @since 1.0.0
   */
  public static function display_fincon_customer_data( \WP_User $user ): void {
    // Only show to users with manage_woocommerce capability
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
      return;
    }
    
    $user_id = $user->ID;
    
    // Get timestamps
    $changed_timestamp = (int) get_user_meta( $user_id, self::USER_META_CHANGED_TIMESTAMP, true );
    $last_sync_timestamp = (int) get_user_meta( $user_id, self::USER_META_LAST_SYNC_TIMESTAMP, true );
    
    // Get user's Fincon AccNo
    $accno = get_user_meta( $user_id, self::USER_META_ACCNO, true );
    $has_accno = ! empty( $accno );
    
    // Format timestamps
    $date_format = get_option( 'date_format' );
    $time_format = get_option( 'time_format' );
    $datetime_format = $date_format . ' ' . $time_format;
    
    $changed_formatted = $changed_timestamp ? date_i18n( $datetime_format, $changed_timestamp ) : __( 'Never', 'df-fincon' );
    $last_sync_formatted = $last_sync_timestamp ? date_i18n( $datetime_format, $last_sync_timestamp ) : __( 'Never', 'df-fincon' );
    ?>
    <h3><?php esc_html_e( 'FinCon Synchronization Data', 'df-fincon' ); ?></h3>
        <div class="df-fincon-user-sync-section" style="margin-top: 20px;">
      <h4><?php esc_html_e( 'Manual Synchronization', 'df-fincon' ); ?></h4>
      <p>
        <button
          id="df-fincon-sync-user-btn"
          class="button button-primary"
          type="button"
          data-user-id="<?php echo esc_attr( $user_id ); ?>"
          data-nonce="<?php echo esc_attr( wp_create_nonce( 'df_fincon_sync_user_nonce' ) ); ?>"
          <?php echo $has_accno ? '' : 'disabled'; ?>
        >
          <?php esc_html_e( 'Sync with Fincon', 'df-fincon' ); ?>
        </button>
        <?php if ( ! $has_accno ) : ?>
          <span class="description" style="margin-left: 10px;">
            <?php esc_html_e( 'User does not have a Fincon AccNo. Add an AccNo in the FinCon Customer Data section above to enable sync.', 'df-fincon' ); ?>
          </span>
        <?php endif; ?>
      </p>
      <div id="df-fincon-sync-user-feedback" style="margin-top: 10px; display: none;"></div>
    </div>
    <hr>
    <table class="form-table">
      <tr>
        <th><?php esc_html_e( 'FinCon Changed Timestamp', 'df-fincon' ); ?></th>
        <td>
          <p>
            <?php echo esc_html( $changed_formatted ); ?>
            <?php if ( $changed_timestamp ) : ?>
              <br/><small><?php printf( esc_html__( 'Unix timestamp: %d', 'df-fincon' ), esc_html( $changed_timestamp ) ); ?></small>
            <?php endif; ?>
          </p>
        </td>
      </tr>
      <tr>
        <th><?php esc_html_e( 'Last synced with FinCon', 'df-fincon' ); ?></th>
        <td>
          <p>
            <?php echo esc_html( $last_sync_formatted ); ?>
            <?php if ( $last_sync_timestamp ) : ?>
              <br/><small><?php printf( esc_html__( 'Unix timestamp: %d', 'df-fincon' ), esc_html( $last_sync_timestamp ) ); ?></small>
            <?php endif; ?>
          </p>
        </td>
      </tr>
    </table>
    

    <?php
  }

  /**
   * Display customer type (Retail/Dealer), price list, and location info in order edit screen
   *
   * @param \WC_Order $order WooCommerce order object
   * @return void
   * @since 1.0.0
   */
  public static function display_dealer_info_meta_box( \WC_Order $order ): void {
    echo '<div class="fincon-dealer-info" style="border: 2px solid #0073aa; background: #f0f8ff; padding: 10px;">';
    
    $customer_id = $order->get_customer_id();
    
    if ( ! $customer_id ) {
      echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Customer Type:', 'df-fincon' ) . '</strong> ' . esc_html__( 'Guest', 'df-fincon' ) . '</p>';
      return;
    }
    
    $user = get_user_by( 'id', $customer_id );
    if ( ! $user )
      return;
    
    
    // Also check if customer has Fincon account number
    $acc_no = get_user_meta( $customer_id, self::USER_META_ACCNO, true );
    $has_fincon_account = ! empty( $acc_no );
    
    // Get customer price list information
    $price_structure = (int) get_user_meta( $customer_id, self::USER_META_PRICE_STRUCTURE, true );
    $validated_price_list = CustomerSync::get_customer_price_list( $customer_id );
    $is_dealer = CustomerSync::is_customer_dealer( $customer_id );
    $is_retail = CustomerSync::is_customer_retail( $customer_id );
    
    // Get order price list (if stored separately)
    $order_price_list = $order->get_meta( Woo::ORDER_META_PRICE_LIST );
    if ( empty( $order_price_list ) ) {
      $order_price_list = $validated_price_list;
    }
    
    echo '<div class="order_data_column">';
    echo '<h3>' . esc_html__( 'Fincon Customer Info', 'df-fincon' ) . '</h3>';
    
    if ( $has_fincon_account )
      echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Fincon AccNo:', 'df-fincon' ) . '</strong> ' . esc_html( $acc_no ) . '</p>';
    
    // Display customer type and price list
    echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Customer Type:', 'df-fincon' ) . '</strong> ';
    if ( $is_dealer ) {
      echo '<span style="color: #2271b1; font-weight: bold;">' . esc_html__( 'Dealer (B2B)', 'df-fincon' ) . '</span>';
    } elseif ( $is_retail ) {
      echo '<span style="color: #646970;">' . esc_html__( 'Retail (B2C)', 'df-fincon' ) . '</span>';
    } else {
      echo '<span style="color: #d63638;">' . esc_html__( 'Unknown', 'df-fincon' ) . '</span>';
    }
    echo '</p>';
    
    // Display price list information
    echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Price List:', 'df-fincon' ) . '</strong> ';
    echo '<span style="font-weight: bold;">' . esc_html( $order_price_list ) . ' - ' . esc_html( Woo::get_price_list_label( $order_price_list ) ) . '</span>';
    
    echo '</p>';
    
    
    // Get selected location from order meta
    $selected_locno = $order->get_meta( OrderSync::META_LOCATION_CODE ) ?? '';
    $selected_locname = $order->get_meta( OrderSync::META_LOCATION_NAME ) ?? '';
    $selected_repcode = $order->get_meta( OrderSync::META_REP_CODE ) ?? '';
    
    // Get default location as fallback
    $default_location = LocationManager::create()->get_default_location();
    $default_locno = $default_location['code'] ?? '';
    $default_locname = $default_location['name'] ?? '';
    $default_repcode = $default_location['rep_code'] ?? '';
    
    if ( ! empty( $selected_locno ) ) {
      // Show selected location
      echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Location Name:', 'df-fincon' ) . '</strong> ' . esc_html( sprintf( '%s', $selected_locname ) ) . '</p>';
      echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Location No:', 'df-fincon' ) . '</strong> ' . esc_html( sprintf( '%s ',  $selected_locno ) ) . '</p>';
      echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Repcode:', 'df-fincon' ) . '</strong> ' . esc_html( sprintf( '%s', $selected_repcode ) ) . '</p>';
    } 
    
    echo '</div>';
    echo '</div>';

  }

  /**
   * Show FinCon fields on the WooCommerce customer edit screen.
   */
  public static function register_customer_meta_fields( array $fields ): array {
    // Get price list options with labels
    $price_list_options = [];
    for ( $i = 1; $i <= 6; $i++ ) {
      $price_list_options[$i] = sprintf( '%d - %s', $i, Woo::get_price_list_label( $i ) );
    }
    
    $fields['fincon'] = [
      'title'  => __( 'FinCon Customer Data', 'df-fincon' ),
      'fields' => [
        self::USER_META_ACCNO => [
          'label'       => __( 'FinCon AccNo', 'df-fincon' ),
          'description' => __( 'Debtor account number from FinCon.', 'df-fincon' ),
          'type'        => 'text',
        ],
        self::USER_META_PRICE_STRUCTURE => [
          'label'       => __( 'FinCon Price List', 'df-fincon' ),
          'description' => __( 'Determines which FinCon price tier to use. Price List 1 = Retail, 2-6 = Dealer.', 'df-fincon' ),
          'type'        => 'select',
          'options'     => $price_list_options,
        ],
      ],
    ];

    return $fields;
  }

  /**
   * Add price list column to orders list
   *
   * @param array $columns Existing columns
   * @return array Modified columns
   * @since 1.0.0
   */
  public static function add_order_price_list_column( array $columns ): array {
    $new_columns = [];
    
    foreach ( $columns as $key => $column ) {
      $new_columns[$key] = $column;
      
      // Insert after "order_total" column
      if ( $key === 'order_total' ) {
        $new_columns['price_list'] = __( 'Price List', 'df-fincon' );
      }
    }
    
    // If order_total not found, add at the end
    if ( ! isset( $new_columns['price_list'] ) ) {
      $new_columns['price_list'] = __( 'Price List', 'df-fincon' );
    }
    
    return $new_columns;
  }

  /**
   * Add Fincon columns to users list
   *
   * @param array $columns Existing columns
   * @return array Modified columns
   * @since 1.0.0
   */
  public static function add_user_columns( array $columns ): array {
    $columns[ self::USER_META_ACCNO ] = __( 'Fincon AccNo', 'df-fincon' );
    $columns[ self::USER_META_PRICE_STRUCTURE ] = __( 'Price List', 'df-fincon' );
    return $columns;
  }

  /**
   * Display Fincon column values in users list
   *
   * @param string $output Column output
   * @param string $column_name Column ID
   * @param int $user_id User ID
   * @return string Column content
   * @since 1.0.0
   */
  public static function display_user_column( string $output, string $column_name, int $user_id ): string {
    if ( $column_name === self::USER_META_ACCNO ) {
      $accno = get_user_meta( $user_id, self::USER_META_ACCNO, true );
      return $accno ? esc_html( $accno ) : '—';
    }
    
    if ( $column_name === self::USER_META_PRICE_STRUCTURE ) {
      $price_structure = (int) get_user_meta( $user_id, self::USER_META_PRICE_STRUCTURE, true );
      if ( $price_structure ) {
        $price_list_label = Woo::get_price_list_label( $price_structure );
        return sprintf( '%d - %s', $price_structure, esc_html( $price_list_label ) );
      }
      return '—';
    }
    
    return $output;
  }
  
  /**
   * Extend user search to include Fincon Account Number.
   *
   * Hooks into pre_user_query to OR the meta search into the existing
   * search clause rather than ANDing via meta_query (which breaks
   * standard name/email searches).
   *
   * @param \WP_User_Query $query User query object
   * @return void
   * @since 1.0.0
   */
  public static function extend_user_search( \WP_User_Query $query ): void {
    global $pagenow;
    
    if ( ! is_admin() || $pagenow !== 'users.php' || empty( $_GET['s'] ) )
      return;
    
    add_action( 'pre_user_query', [ __CLASS__, 'inject_fincon_meta_or' ] );
  }

  /**
   * Inject an OR sub-select into the user search WHERE clause so that
   * users matching the FinCon Account Number are included alongside
   * standard column matches.
   *
   * @param \WP_User_Query $query
   * @return void
   */
  public static function inject_fincon_meta_or( \WP_User_Query $query ): void {
    remove_action( 'pre_user_query', [ __CLASS__, 'inject_fincon_meta_or' ] );

    if ( empty( $_GET['s'] ) ) return;

    global $wpdb;

    $search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) );
    if ( empty( $search_term ) ) return;

    $like = '%' . $wpdb->esc_like( $search_term ) . '%';

    $or_clause = $wpdb->prepare(
      " OR {$wpdb->users}.ID IN (
          SELECT user_id FROM {$wpdb->usermeta}
          WHERE meta_key = %s AND meta_value LIKE %s
      )",
      self::USER_META_ACCNO,
      $like
    );

    // WordPress search clause in query_where looks like:
    //   AND (user_login LIKE '%x%' OR ... OR display_name LIKE '%x%')
    // Find the last LIKE '%term%' generated by WordPress and inject
    // our OR before the closing parenthesis of the search group.
    $like_sql = $wpdb->prepare( "%s", $like ); // e.g. '%chad%' (with quotes)
    $needle  = 'LIKE ' . $like_sql;

    $last_pos = strrpos( $query->query_where, $needle );
    if ( $last_pos !== false ) {
      $after = $last_pos + strlen( $needle );
      $close = strpos( $query->query_where, ')', $after );
      if ( $close !== false ) {
        $query->query_where = substr_replace( $query->query_where, $or_clause, $close, 0 );
      }
    }
  }

  /**
   * Add Fincon columns to orders list
   *
   * @param array $columns Existing columns
   * @return array Modified columns
   * @since 1.1.0
   */
  public static function add_order_fincon_columns( array $columns ): array {
    $new_columns = [];
    
    foreach ( $columns as $key => $column ) {
      $new_columns[$key] = $column;
      
      // Insert after "order_total" column (or after price_list if it exists)
      if ( $key === 'order_total' ) {
        // Check if price_list already exists
        if ( ! isset( $new_columns['price_list'] ) ) {
          $new_columns['price_list'] = __( 'Price List', 'df-fincon' );
        }
        $new_columns['fincon_sales_order'] = __( 'Fincon Order #', 'df-fincon' );
        $new_columns['fincon_invoice'] = __( 'Fincon Invoice', 'df-fincon' );
      }
    }
    
    // If order_total not found, add at the end
    if ( ! isset( $new_columns['fincon_sales_order'] ) ) {
      $new_columns['fincon_sales_order'] = __( 'Fincon Order #', 'df-fincon' );
      $new_columns['fincon_invoice'] = __( 'Fincon Invoice', 'df-fincon' );
    }
    
    return $new_columns;
  }

  /**
   * Display price list value in orders list column
   *
   * @param string $column Column ID
   * @param int $order_id Order ID
   * @return void
   * @since 1.0.0
   */
  public static function display_order_price_list_column( string $column, int $order_id ): void {
    if ( $column !== 'price_list' )
      return;
    
    $order = wc_get_order( $order_id );
    if ( ! $order )
      return;
    
    $price_list = $order->get_meta( Woo::ORDER_META_PRICE_LIST );
    
    if ( ! empty( $price_list ) ) {
      $price_list_label = Woo::get_price_list_label( $price_list );
      $customer_id = $order->get_customer_id();
      
      // Check if this matches customer's default price list
      $customer_price_list = $customer_id ? CustomerSync::get_customer_price_list( $customer_id ) : null;
      $is_default = $customer_price_list && $price_list == $customer_price_list;
      
      echo '<div class="price-list-display">';
      echo '<span class="price-list-value" style="font-weight: bold;">' . esc_html( $price_list ) . '</span>';
      echo '<br><span class="price-list-label" style="font-size: 11px; color: #666;">' . esc_html( $price_list_label ) . '</span>';
      
      if ( $is_default ) {
        echo '<br><span class="price-list-default" style="font-size: 10px; color: #2271b1;">' . esc_html__( 'Default', 'df-fincon' ) . '</span>';
      } elseif ( $customer_price_list && $price_list != $customer_price_list ) {
        echo '<br><span class="price-list-different" style="font-size: 10px; color: #d63638;">' .
             sprintf( esc_html__( 'Diff: %d', 'df-fincon' ), $customer_price_list ) . '</span>';
      }
      
      echo '</div>';
    } else {
      echo '<span class="na">—</span>';
    }
  }

  /**
   * Display Fincon columns in orders list
   *
   * @param string $column Column ID
   * @param int $order_id Order ID
   * @return void
   * @since 1.1.0
   */
  public static function display_order_fincon_columns( string $column, int $order_id ): void {
    if ( ! in_array( $column, [ 'fincon_sales_order', 'fincon_invoice' ], true ) )
      return;
    
    $order = wc_get_order( $order_id );
    if ( ! $order )
      return;
    
    if ( $column === 'fincon_sales_order' ) {
      // Display Fincon Order # number
      $fincon_order_no = $order->get_meta( OrderSync::META_ORDER_NO );
      $synced = $order->get_meta( OrderSync::META_SYNCED );
      
      if ( $synced && ! empty( $fincon_order_no ) ) {
        echo '<div class="fincon-sales-order-display">';
        echo '<span class="fincon-order-no" style="font-weight: bold; color: #2271b1;">' . esc_html( $fincon_order_no ) . '</span>';
        
        // Check if order has receipt number
        $receipt_no = $order->get_meta( OrderSync::META_RECEIPT_NO );
        if ( ! empty( $receipt_no ) ) {
          echo '<br><span class="fincon-receipt-no" style="font-size: 11px; color: #666;">' .
               sprintf( esc_html__( 'Receipt: %s', 'df-fincon' ), esc_html( $receipt_no ) ) . '</span>';
        }
        
        echo '</div>';
      } elseif ( $synced ) {
        echo '<span class="fincon-synced-no-number" style="color: #666; font-style: italic;">' .
             esc_html__( 'Synced (no number)', 'df-fincon' ) . '</span>';
      } else {
        $sync_error = $order->get_meta( OrderSync::META_SYNC_ERROR );
        if ( $sync_error ) {
          echo '<span class="fincon-sync-error" style="color: #d63638; font-style: italic;" title="' . esc_attr( $sync_error ) . '">' .
               esc_html__( 'Sync failed', 'df-fincon' ) . '</span>';
        } else {
          echo '<span class="na">—</span>';
        }
      }
    } elseif ( $column === 'fincon_invoice' ) {
      // Display Fincon Invoice status and numbers
      $invoice_status = $order->get_meta( OrderSync::META_INVOICE_STATUS );
      $invoice_numbers = $order->get_meta( OrderSync::META_INVOICE_NUMBERS );
      $pdf_available = $order->get_meta( OrderSync::META_PDF_AVAILABLE );
      
      if ( ! empty( $invoice_status ) ) {
        echo '<div class="fincon-invoice-display">';
        
        // Status badge with color coding
        $status_colors = [
          'pending' => '#f0ad4e', // Orange
          'available' => '#5cb85c', // Green
          'downloaded' => '#0275d8', // Blue
          'multiple' => '#5bc0de', // Light blue
          'error' => '#d9534f', // Red
        ];
        
        $status_color = $status_colors[$invoice_status] ?? '#777';
        $status_label = ucfirst( $invoice_status );
        
        echo '<span class="invoice-status-badge" style="display: inline-block; padding: 2px 6px; background: ' . esc_attr( $status_color ) . '; color: white; border-radius: 3px; font-size: 11px; font-weight: bold;">' .
             esc_html( $status_label ) . '</span>';
        
        // Invoice numbers
        if ( ! empty( $invoice_numbers ) ) {
          echo '<br><span class="invoice-numbers" style="font-size: 11px; color: #333; margin-top: 3px; display: block;">' .
               esc_html( $invoice_numbers ) . '</span>';
        }
        
        // PDF indicator
        if ( $pdf_available ) {
          $pdf_paths = $order->get_meta( OrderSync::META_PDF_PATHS );
          $has_pdfs = ! empty( $pdf_paths );
          
          if ( $has_pdfs ) {
            echo '<br><span class="pdf-available" style="font-size: 10px; color: #5cb85c;">' .
                 esc_html__( 'PDF available', 'df-fincon' ) . '</span>';
          }
        }
        
        // Check attempts for pending invoices
        if ( $invoice_status === 'pending' ) {
          $check_attempts = (int) $order->get_meta( OrderSync::META_INVOICE_CHECK_ATTEMPTS );
          if ( $check_attempts > 0 ) {
            echo '<br><span class="check-attempts" style="font-size: 10px; color: #f0ad4e;">' .
                 sprintf( esc_html__( 'Checked: %d', 'df-fincon' ), $check_attempts ) . '</span>';
          }
        }
        
        echo '</div>';
      } else {
        // Check if order is synced but no invoice status yet
        $synced = $order->get_meta( OrderSync::META_SYNCED );
        if ( $synced ) {
          echo '<span class="invoice-not-checked" style="color: #777; font-style: italic;">' .
               esc_html__( 'Not checked', 'df-fincon' ) . '</span>';
        } else {
          echo '<span class="na">—</span>';
        }
      }
    }
  }

  /**
   * Display price list value in orders list column (HPOS version)
   *
   * @param string $column Column ID
   * @param \WC_Order $order Order object
   * @return void
   * @since 1.1.0
   */
  public static function display_order_price_list_column_hpos( string $column, \WC_Order $order ): void {
    if ( $column !== 'price_list' )
      return;
    
    $price_list = $order->get_meta( Woo::ORDER_META_PRICE_LIST );
    
    if ( ! empty( $price_list ) ) {
      $price_list_label = Woo::get_price_list_label( $price_list );
      $customer_id = $order->get_customer_id();
      
      // Check if this matches customer's default price list
      $customer_price_list = $customer_id ? CustomerSync::get_customer_price_list( $customer_id ) : null;
      $is_default = $customer_price_list && $price_list == $customer_price_list;
      
      echo '<div class="price-list-display">';
      echo '<span class="price-list-value" style="font-weight: bold;">' . esc_html( $price_list ) . '</span>';
      echo '<br><span class="price-list-label" style="font-size: 11px; color: #666;">' . esc_html( $price_list_label ) . '</span>';
      
      if ( $is_default ) {
        echo '<br><span class="price-list-default" style="font-size: 10px; color: #2271b1;">' . esc_html__( 'Default', 'df-fincon' ) . '</span>';
      } elseif ( $customer_price_list && $price_list != $customer_price_list ) {
        echo '<br><span class="price-list-different" style="font-size: 10px; color: #d63638;">' .
             sprintf( esc_html__( 'Diff: %d', 'df-fincon' ), $customer_price_list ) . '</span>';
      }
      
      echo '</div>';
    } else {
      echo '<span class="na">—</span>';
    }
  }

  /**
   * Display Fincon columns in orders list (HPOS version)
   *
   * @param string $column Column ID
   * @param \WC_Order $order Order object
   * @return void
   * @since 1.1.0
   */
  public static function display_order_fincon_columns_hpos( string $column, \WC_Order $order ): void {
    if ( ! in_array( $column, [ 'fincon_sales_order', 'fincon_invoice' ], true ) )
      return;
    
    if ( $column === 'fincon_sales_order' ) {
      // Display Fincon Order # number
      $fincon_order_no = $order->get_meta( OrderSync::META_ORDER_NO );
      $synced = $order->get_meta( OrderSync::META_SYNCED );
      
      if ( $synced && ! empty( $fincon_order_no ) ) {
        echo '<div class="fincon-sales-order-display">';
        echo '<span class="fincon-order-no" style="font-weight: bold; color: #2271b1;">' . esc_html( $fincon_order_no ) . '</span>';
        
        // Check if order has receipt number
        $receipt_no = $order->get_meta( OrderSync::META_RECEIPT_NO );
        if ( ! empty( $receipt_no ) ) {
          echo '<br><span class="fincon-receipt-no" style="font-size: 11px; color: #666;">' .
               sprintf( esc_html__( 'Receipt: %s', 'df-fincon' ), esc_html( $receipt_no ) ) . '</span>';
        }
        
        echo '</div>';
      } elseif ( $synced ) {
        echo '<span class="fincon-synced-no-number" style="color: #666; font-style: italic;">' .
             esc_html__( 'Synced (no number)', 'df-fincon' ) . '</span>';
      } else {
        $sync_error = $order->get_meta( OrderSync::META_SYNC_ERROR );
        if ( $sync_error ) {
          echo '<span class="fincon-sync-error" style="color: #d63638; font-style: italic;" title="' . esc_attr( $sync_error ) . '">' .
               esc_html__( 'Sync failed', 'df-fincon' ) . '</span>';
        } else {
          echo '<span class="na">—</span>';
        }
      }
    } elseif ( $column === 'fincon_invoice' ) {
      // Display Fincon Invoice status and numbers
      $invoice_status = $order->get_meta( OrderSync::META_INVOICE_STATUS );
      $invoice_numbers = $order->get_meta( OrderSync::META_INVOICE_NUMBERS );
      $pdf_available = $order->get_meta( OrderSync::META_PDF_AVAILABLE );
      
      if ( ! empty( $invoice_status ) ) {
        echo '<div class="fincon-invoice-display">';
        
        // Status badge with color coding
        $status_colors = [
          'pending' => '#f0ad4e', // Orange
          'available' => '#5cb85c', // Green
          'downloaded' => '#0275d8', // Blue
          'multiple' => '#5bc0de', // Light blue
          'error' => '#d9534f', // Red
        ];
        
        $status_color = $status_colors[$invoice_status] ?? '#777';
        $status_label = ucfirst( $invoice_status );
        
        echo '<span class="invoice-status-badge" style="display: inline-block; padding: 2px 6px; background: ' . esc_attr( $status_color ) . '; color: white; border-radius: 3px; font-size: 11px; font-weight: bold;">' .
             esc_html( $status_label ) . '</span>';
        
        // Invoice numbers
        if ( ! empty( $invoice_numbers ) ) {
          echo '<br><span class="invoice-numbers" style="font-size: 11px; color: #333; margin-top: 3px; display: block;">' .
               esc_html( $invoice_numbers ) . '</span>';
        }
        
        // PDF indicator
        if ( $pdf_available ) {
          $pdf_paths = $order->get_meta( OrderSync::META_PDF_PATHS );
          $has_pdfs = ! empty( $pdf_paths );
          
          if ( $has_pdfs ) {
            echo '<br><span class="pdf-available" style="font-size: 10px; color: #5cb85c;">' .
                 esc_html__( 'PDF available', 'df-fincon' ) . '</span>';
          }
        }
        
        // Check attempts for pending invoices
        if ( $invoice_status === 'pending' ) {
          $check_attempts = (int) $order->get_meta( OrderSync::META_INVOICE_CHECK_ATTEMPTS );
          if ( $check_attempts > 0 ) {
            echo '<br><span class="check-attempts" style="font-size: 10px; color: #f0ad4e;">' .
                 sprintf( esc_html__( 'Checked: %d', 'df-fincon' ), $check_attempts ) . '</span>';
          }
        }
        
        echo '</div>';
      } else {
        // Check if order is synced but no invoice status yet
        $synced = $order->get_meta( OrderSync::META_SYNCED );
        if ( $synced ) {
          echo '<span class="invoice-not-checked" style="color: #777; font-style: italic;">' .
               esc_html__( 'Not checked', 'df-fincon' ) . '</span>';
        } else {
          echo '<span class="na">—</span>';
        }
      }
    }
  }

  /**
   * Make price list column sortable for HPOS
   *
   * @param array $columns Sortable columns
   * @return array Modified sortable columns
   * @since 1.1.0
   */
  public static function add_order_price_list_sortable_column_hpos( array $columns ): array {
    $columns['price_list'] = 'price_list';
    return $columns;
  }

  /**
   * Make price list column sortable
   *
   * @param array $columns Sortable columns
   * @return array Modified sortable columns
   * @since 1.0.0
   */
  public static function add_order_price_list_sortable_column( array $columns ): array {
    $columns['price_list'] = 'price_list';
    return $columns;
  }

  /**
   * Add price list filter dropdown to orders list
   *
   * @return void
   * @since 1.0.0
   */
  public static function add_price_list_filter_dropdown(): void {
    global $typenow, $pagenow;
    
    // Check if we're on an order screen (traditional or HPOS)
    $is_traditional_order_screen = 'shop_order' === $typenow && 'edit.php' === $pagenow;
    $is_hpos_order_screen = 'wc-orders' === ( $_GET['page'] ?? '' ) && 'admin.php' === $pagenow;
    
    if ( ! $is_traditional_order_screen && ! $is_hpos_order_screen )
      return;
    
    $current_price_list = isset( $_GET['price_list_filter'] ) ? (int) $_GET['price_list_filter'] : '';
    
    echo '<select name="price_list_filter" id="price_list_filter">';
    echo '<option value="">' . esc_html__( 'All Price Lists', 'df-fincon' ) . '</option>';
    
    for ( $i = 1; $i <= 6; $i++ ) {
      $selected = $current_price_list === $i ? ' selected="selected"' : '';
      $label = Woo::get_price_list_label( $i );
      echo '<option value="' . esc_attr( $i ) . '"' . $selected . '>' .
           esc_html( sprintf( '%d - %s', $i, $label ) ) . '</option>';
    }
    
    // Special option for orders without price list
    $selected_no_price = $current_price_list === 0 ? ' selected="selected"' : '';
    echo '<option value="0"' . $selected_no_price . '>' . esc_html__( 'No Price List', 'df-fincon' ) . '</option>';
    
    echo '</select>';
  }

  /**
   * Filter orders by price list
   *
   * @param array $query_vars Query variables
   * @return array Modified query variables
   * @since 1.0.0
   */
  public static function filter_orders_by_price_list( array $query_vars ): array {
    global $typenow;
    
    if ( 'shop_order' !== $typenow || ! isset( $_GET['price_list_filter'] ) || $_GET['price_list_filter'] === '' )
      return $query_vars;
    
    $price_list_filter = (int) $_GET['price_list_filter'];
    
    if ( $price_list_filter === 0 ) {
      // Filter for orders without price list
      $query_vars['meta_query'] = isset( $query_vars['meta_query'] ) ? $query_vars['meta_query'] : [];
      $query_vars['meta_query'][] = [
        'relation' => 'OR',
        [
          'key' => Woo::ORDER_META_PRICE_LIST,
          'compare' => 'NOT EXISTS',
        ],
        [
          'key' => Woo::ORDER_META_PRICE_LIST,
          'value' => '',
          'compare' => '=',
        ],
      ];
    } else {
      // Filter by specific price list
      $query_vars['meta_key'] = Woo::ORDER_META_PRICE_LIST;
      $query_vars['meta_value'] = $price_list_filter;
      $query_vars['meta_compare'] = '=';
    }
    
    return $query_vars;
  }

  /**
   * Join posts meta table for price list filtering
   *
   * @param string $join JOIN clause
   * @param \WP_Query $query WP_Query object
   * @return string Modified JOIN clause
   * @since 1.0.0
   */
  public static function filter_orders_by_price_list_join( string $join, \WP_Query $query ): string {
    global $wpdb;
    
    if ( ! $query->is_main_query() || ! isset( $_GET['orderby'] ) || $_GET['orderby'] !== 'price_list' )
      return $join;
    
    $join .= " LEFT JOIN {$wpdb->postmeta} AS price_list_meta ON {$wpdb->posts}.ID = price_list_meta.post_id AND price_list_meta.meta_key = '" . Woo::ORDER_META_PRICE_LIST . "'";
    
    return $join;
  }

  /**
   * Add WHERE clause for price list filtering
   *
   * @param string $where WHERE clause
   * @param \WP_Query $query WP_Query object
   * @return string Modified WHERE clause
   * @since 1.0.0
   */
  public static function filter_orders_by_price_list_where( string $where, \WP_Query $query ): string {
    global $wpdb;
    
    if ( ! $query->is_main_query() || ! isset( $_GET['orderby'] ) || $_GET['orderby'] !== 'price_list' )
      return $where;
    
    // Ensure we're only modifying shop_order queries
    if ( $query->get( 'post_type' ) !== 'shop_order' )
      return $where;
    
    // Add sorting by price list meta value
    $order = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'DESC' ? 'DESC' : 'ASC';
    
    // We'll handle sorting in the ORDER BY clause via WordPress
    // This WHERE clause ensures we have the meta table joined
    return $where;
  }

  /**
   * Add CSS styles for order list column
   *
   * @return void
   * @since 1.0.0
   */
  public static function add_order_list_styles(): void {
    global $pagenow, $typenow;
    
    if ( $pagenow !== 'edit.php' || $typenow !== 'shop_order' )
      return;
    
    echo '<style>
      .column-price_list {
        width: 100px;
      }
      .column-fincon_sales_order {
        width: 120px;
      }
      .column-fincon_invoice {
        width: 140px;
      }
      .price-list-display {
        line-height: 1.3;
      }
      .price-list-value {
        display: inline-block;
        padding: 2px 6px;
        background: #f0f0f0;
        border-radius: 3px;
        min-width: 20px;
        text-align: center;
      }
      .price-list-label {
        display: block;
        margin-top: 2px;
      }
      .price-list-default,
      .price-list-different {
        display: inline-block;
        padding: 1px 4px;
        border-radius: 2px;
        margin-top: 2px;
      }
      .price-list-default {
        background: #e8f4fd;
        color: #2271b1;
      }
      .price-list-different {
        background: #fde8e8;
        color: #d63638;
      }
      .fincon-sales-order-display,
      .fincon-invoice-display {
        line-height: 1.3;
      }
      .fincon-order-no {
        display: inline-block;
        padding: 2px 6px;
        background: #e8f4fd;
        border-radius: 3px;
        min-width: 20px;
        text-align: center;
      }
      .fincon-receipt-no {
        display: block;
        margin-top: 2px;
      }
      .fincon-synced-no-number {
        font-style: italic;
        color: #666;
      }
      .fincon-sync-error {
        font-style: italic;
        color: #d63638;
      }
      .invoice-status-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        color: white;
        text-align: center;
        min-width: 60px;
      }
      .invoice-numbers {
        display: block;
        margin-top: 3px;
        font-size: 11px;
        color: #333;
      }
      .pdf-available {
        display: block;
        margin-top: 2px;
        font-size: 10px;
        color: #5cb85c;
      }
      .check-attempts {
        display: block;
        margin-top: 2px;
        font-size: 10px;
        color: #f0ad4e;
      }
      .invoice-not-checked {
        font-style: italic;
        color: #777;
      }
      .na {
        color: #999;
        font-style: italic;
      }
    </style>';
  }

  /**
   * Add Fincon order number search field to orders list
   *
   * @return void
   * @since 1.1.0
   */
  public static function add_fincon_order_search_field(): void {
    global $typenow, $pagenow;
    
    // Check if we're on an order screen (traditional or HPOS)
    $is_traditional_order_screen = 'shop_order' === $typenow && 'edit.php' === $pagenow;
    $is_hpos_order_screen = 'wc-orders' === ( $_GET['page'] ?? '' ) && 'admin.php' === $pagenow;
    
    if ( ! $is_traditional_order_screen && ! $is_hpos_order_screen )
      return;
    
    $current_search = isset( $_GET['fincon_order_search'] ) ? sanitize_text_field( $_GET['fincon_order_search'] ) : '';
    
    echo '<input type="text" name="fincon_order_search" id="fincon_order_search" value="' . esc_attr( $current_search ) . '" placeholder="' . esc_attr__( 'Fincon Order No.', 'df-fincon' ) . '" style="margin-left: 10px; width: 180px;" />';
  }

  /**
   * Filter orders by Fincon order number
   *
   * @param array $query_vars Query variables
   * @return array Modified query variables
   * @since 1.1.0
   */
  public static function filter_orders_by_fincon_order( array $query_vars ): array {
    global $typenow;
    
    if ( 'shop_order' !== $typenow || ! isset( $_GET['fincon_order_search'] ) || $_GET['fincon_order_search'] === '' )
      return $query_vars;
    
    $fincon_order_search = sanitize_text_field( $_GET['fincon_order_search'] );
    
    if ( ! empty( $fincon_order_search ) ) {
      $query_vars['meta_query'] = isset( $query_vars['meta_query'] ) ? $query_vars['meta_query'] : [];
      $query_vars['meta_query'][] = [
        'key' => OrderSync::META_ORDER_NO,
        'value' => $fincon_order_search,
        'compare' => 'LIKE',
      ];
    }
    
    return $query_vars;
  }

  /**
   * Join posts meta table for Fincon order number filtering
   *
   * @param string $join JOIN clause
   * @param \WP_Query $query WP_Query object
   * @return string Modified JOIN clause
   * @since 1.1.0
   */
  public static function filter_orders_by_fincon_order_join( string $join, \WP_Query $query ): string {
    global $wpdb;
    
    if ( ! $query->is_main_query() || ! isset( $_GET['fincon_order_search'] ) || $_GET['fincon_order_search'] === '' )
      return $join;
    
    // Ensure we're only modifying shop_order queries
    if ( $query->get( 'post_type' ) !== 'shop_order' )
      return $join;
    
    $join .= " LEFT JOIN {$wpdb->postmeta} AS fincon_order_meta ON {$wpdb->posts}.ID = fincon_order_meta.post_id AND fincon_order_meta.meta_key = '" . OrderSync::META_ORDER_NO . "'";
    
    return $join;
  }

  /**
   * Add WHERE clause for Fincon order number filtering
   *
   * @param string $where WHERE clause
   * @param \WP_Query $query WP_Query object
   * @return string Modified WHERE clause
   * @since 1.1.0
   */
  public static function filter_orders_by_fincon_order_where( string $where, \WP_Query $query ): string {
    global $wpdb;
    
    if ( ! $query->is_main_query() || ! isset( $_GET['fincon_order_search'] ) || $_GET['fincon_order_search'] === '' )
      return $where;
    
    // Ensure we're only modifying shop_order queries
    if ( $query->get( 'post_type' ) !== 'shop_order' )
      return $where;
    
    $fincon_order_search = sanitize_text_field( $_GET['fincon_order_search'] );
    
    if ( ! empty( $fincon_order_search ) ) {
      $where .= $wpdb->prepare( " AND (fincon_order_meta.meta_value LIKE %s)", '%' . $wpdb->esc_like( $fincon_order_search ) . '%' );
    }
    
    return $where;
  }

  /**
   * Set default hidden columns for orders list
   *
   * @param array $hidden Array of hidden columns
   * @param \WP_Screen $screen Current screen object
   * @return array Modified hidden columns
   * @since 1.1.0
   */
  public static function default_hidden_order_columns( array $hidden, \WP_Screen $screen ): array {
    // Only apply to order screens (traditional and HPOS)
    $order_screens = [ 'edit-shop_order', 'woocommerce_page_wc-orders' ];
    if ( ! in_array( $screen->id, $order_screens, true ) )
      return $hidden;
    
    // Hide Fincon columns by default
    $fincon_columns = [ 'fincon_sales_order', 'fincon_invoice' ];
    
    // Add Fincon columns to hidden list if not already there
    foreach ( $fincon_columns as $column ) {
      if ( ! in_array( $column, $hidden, true ) ) {
        $hidden[] = $column;
      }
    }
    
    return $hidden;
  }

  /**
   * Add Fincon info meta box to order edit screen
   *
   * @return void
   * @since 1.0.0
   */
  public static function add_order_meta_boxes(): void {
    global $current_screen;
    
    // Determine the correct screen ID for WooCommerce orders
    // HPOS uses 'woocommerce_page_wc-orders', traditional uses 'shop_order'
    $screen_id = $current_screen->id ?? 'shop_order';
    
    // Only proceed if we're on a WooCommerce order screen
    $is_order_screen = in_array( $screen_id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true );

    
    // Only add meta box for WooCommerce order screens and if user has appropriate permissions
    if ( ! $current_screen || ! $is_order_screen )
      return;
    
    if ( ! current_user_can( 'manage_woocommerce' ) ) 
      return;
    

    add_meta_box(
      'fincon_dealer_info',
      __( 'Fincon Customer Info', 'df-fincon' ),
      [ self::class, 'display_dealer_info_meta_box' ],
      $screen_id,
      'normal',  // Changed from 'side' to 'normal' to appear below order details
      'default'  // Changed from 'high' to 'default' to appear after order details
    );

    add_meta_box(
      'fincon_order_info',
      __( 'Fincon Order Info', 'df-fincon' ),
      [ OrderSync::class, 'display_order_meta_box' ],
      $screen_id,
      'normal',  // Changed from 'side' to 'normal' to appear below order details
      'default'  // Changed from 'high' to 'default' to appear after order details
    );



  }

}