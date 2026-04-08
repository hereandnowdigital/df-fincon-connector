<?php
  /**
   * WooCommerce Functionality
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

class Woo {

  /**
   * Instance
   * @var self
   */
  private static ?self $instance = null;
  

  /**
   * USER meta field keys
   */
  private const USER_ROLE_RETAIL = CustomerSync::ROLE_RETAIL;
  private const USER_ROLE_DEALER = CustomerSync::ROLE_DEALER;

  private const USER_META_DBG_DEALER_SUB_ACCOUNT = CustomerSync::META_DBG_DEALER_SUB_ACCOUNT;

  private const FIELD_DELIVERY_INSTRUCTIONS = CustomerSync::META_DELIVERY_INSTRUCTIONS;
  
  /**
   * Location selector field key
   */
  private const FIELD_LOCATION_SELECTOR = 'df_fincon_location';

  /**
   * Price list session key
   */
  private const SESSION_PRICE_LIST_KEY = 'df_fincon_price_list';

  /**
   * Order meta key for price list
   */
  public const ORDER_META_PRICE_LIST = '_fincon_price_list';

  /**
   * Price display labels
   */
  private const LABEL_RETAIL_PRICE = 'Retail Price: ';
  private const LABEL_DEALER_PRICE = 'Your Price: ';

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
    self::register_actions_users();
    self::register_actions_checkout();
    self::register_actions_pricing();
  }

  private static function register_actions_users(): void {
    add_action( 'init', [ __CLASS__, 'register_roles' ] );
    add_action( 'woocommerce_edit_account_form', [ __CLASS__, 'display_frontend_session_pricing_button' ] );
    add_filter( 'woocommerce_address_to_edit', [ __CLASS__, 'disable_myaccount_address_fields_for_dbg_dealer' ], 10, 2 );
    
    // My Account addresses page modifications for DBG Dealer sub accounts
    add_action( 'woocommerce_account_content', [ __CLASS__, 'maybe_display_dbg_dealer_address_message' ] );
    add_action( 'woocommerce_after_edit_address_form_(load_address)', [ __CLASS__, 'maybe_disable_save_address_button' ] );
    add_filter( 'woocommerce_my_account_my_address_description', [ __CLASS__, 'modify_my_address_description' ], 10, 1 );
    
    // Remove addresses link from My Account navigation for DBG Dealer sub accounts
    add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'maybe_remove_addresses_menu_item' ] );
  }

  /**
   * Register Checkout related hooks
   */
  private static function register_actions_checkout(): void {
    add_action( 'woocommerce_payment_complete', [ OrderSync::class, 'sync_order_on_payment_complete' ] );
    add_action( 'woocommerce_order_status_completed', [ OrderSync::class, 'sync_order_on_payment_complete' ] );
    add_action( 'woocommerce_order_status_processing', [ OrderSync::class, 'sync_order_on_status_processing' ] );
    add_action( 'woocommerce_before_order_notes', [ __CLASS__, 'add_delivery_instructions_field' ] );
    add_action( 'woocommerce_before_order_notes', [ __CLASS__, 'add_location_selector_field' ] );
    add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'save_delivery_instructions_to_order' ], 10, 2 );
    add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'save_location_to_order' ], 10, 2 );
    add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'save_price_list_to_order' ], 10, 2 );
    add_action( 'woocommerce_after_order_details', [ __CLASS__, 'add_pdf_download_to_order_view' ] );
    add_action( 'woocommerce_after_thankyou_title', [ __CLASS__, 'display_location_in_order_view' ] );
add_action( 'woocommerce_after_order_details', [ __CLASS__, 'display_location_in_order_view' ] );
    
    // Disable address fields for DBG Dealer sub accounts
    add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'disable_address_fields_for_dbg_dealer' ] );
    
    // Display notice for DBG Dealer sub accounts on checkout
    add_action( 'woocommerce_before_checkout_billing_form', [ __CLASS__, 'maybe_display_checkout_notice_for_dbg_dealer' ] );
    
    // Cart page integration
    add_action( 'wp_ajax_df_fincon_update_cart_location', [ __CLASS__, 'update_cart_location_ajax' ] );
    add_action( 'wp_ajax_nopriv_df_fincon_update_cart_location', [ __CLASS__, 'update_cart_location_ajax' ] );
    
    // Price list AJAX endpoints
    add_action( 'wp_ajax_df_fincon_update_price_list', [ __CLASS__, 'update_price_list_ajax' ] );
    add_action( 'wp_ajax_nopriv_df_fincon_update_price_list', [ __CLASS__, 'update_price_list_ajax' ] );
    
    // Clear user session pricing AJAX endpoint
    add_action( 'wp_ajax_df_fincon_clear_user_session_pricing', [ __CLASS__, 'clear_user_session_pricing_ajax' ] );
  }

  /**
   * Register pricing related hooks
   */
  private static function register_actions_pricing(): void {
    
    // Price display hooks
    add_filter( 'woocommerce_get_price_html', [ __CLASS__, 'filter_single_product_display_price'], 10, 2 );

    // Cart item data hooks
    add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_price_list_to_cart_item_data' ], 10, 3 );
    add_filter( 'woocommerce_get_cart_item_from_session', [ __CLASS__, 'get_cart_item_from_session' ], 10, 3 );
    add_filter( 'woocommerce_cart_item_price', [ __CLASS__, 'filter_cart_item_price_display' ], 10, 3 );
    add_filter( 'woocommerce_cart_item_subtotal', [ __CLASS__, 'filter_cart_item_subtotal_display' ], 10, 3 );
    
    // Cart validation hooks
    add_filter( 'woocommerce_cart_loaded_from_session', [ __CLASS__, 'sync_cart_prices_with_price_list' ] );
    
    // Session management
    add_action( 'wp_login', [ __CLASS__, 'set_customer_price_list_on_login' ], 10, 2 );
    add_action( 'wp_logout', [ __CLASS__, 'clear_price_list_on_logout' ] );
    add_action( 'woocommerce_cart_emptied', [ __CLASS__, 'clear_price_list_on_cart_empty' ] );
    add_action( 'profile_update', [ __CLASS__, 'clear_price_list_on_profile_update' ], 10, 2 );
    add_action( 'woocommerce_save_account_details', [ __CLASS__, 'clear_price_list_on_profile_update' ] );
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
   * Output the custom Delivery Instructions field on checkout
   * * @param \WC_Checkout $checkout
   */
  public static function add_delivery_instructions_field( $checkout ): void {
    
    $default_value = '';

    // Check if user is logged in to pre-fill from User Meta
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        // Pull the data we saved in CustomerSync
        $default_value = get_user_meta( $user_id, CustomerSync::META_DELIVERY_INSTRUCTIONS, true );
    }

    echo '<div id="fincon_delivery_instructions_field_wrapper">';
    
    woocommerce_form_field( self::FIELD_DELIVERY_INSTRUCTIONS, [
        'type'        => 'textarea',
        'class'       => ['form-row-wide'],
        'label'       => __( 'Delivery Instructions', 'df-fincon' ),
        'placeholder' => __( 'Delivery Instructions', 'df-fincon' ),
        'required'    => false, 
        'default'     => $default_value,
    ], $checkout->get_value( self::FIELD_DELIVERY_INSTRUCTIONS ) ?: $default_value );

    echo '</div>';
  }

  /**
   * Save the custom field value to the Order Meta
   * * @param \WC_Order $order
   * @param array $data
   */
  public static function save_delivery_instructions_to_order( $order, $data ): void {
    if ( isset( $_POST[ self::FIELD_DELIVERY_INSTRUCTIONS ] ) && ! empty( $_POST[ self::FIELD_DELIVERY_INSTRUCTIONS ] ) ) 
      $order->update_meta_data( 'delivery_instructions', sanitize_textarea_field( $_POST[ self::FIELD_DELIVERY_INSTRUCTIONS ] ) );
  }

  /**
   * Add PDF download button to customer My Account order view
   *
   * @param \WC_Order $order WooCommerce order object
   * @return void
   * @since 1.0.0
   */
  public static function add_pdf_download_to_order_view( \WC_Order $order ): void {
    if ( is_order_received_page() || is_wc_endpoint_url( 'order-received' ) || ! is_account_page()) 
        return;

    // Check if order has Fincon invoice document number
    $invoice_docno = $order->get_meta( OrderSync::META_INVOICE_DOCNO ) ?? '';
    
    // Check if PDF exists
    $pdf_storage = PdfStorage::create();
    $pdf_info = $pdf_storage->get_pdf_info( $order->get_id() );
    $has_pdf = $pdf_info && $pdf_info['exists'];
    
    // Generate download URL
    $download_url = add_query_arg( [
      'action' => 'df_fincon_download_pdf',
      'order_id' => $order->get_id(),
      'nonce' => wp_create_nonce( 'df_fincon_download_pdf_' . $order->get_id() ),
    ], admin_url( 'admin-ajax.php' ) );
    
    ?>
    <div class="fincon-pdf-download" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
      <h3><?php esc_html_e( 'Invoice PDF Download', 'df-fincon' ); ?></h3>
      
      <?php if ( $has_pdf ) : ?>
      <p>
        <a href="<?php echo esc_url( $download_url ); ?>"
           class="button"
           target="_blank"
           style="text-decoration: none;">
          <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
          <?php esc_html_e( 'Download Invoice PDF', 'df-fincon' ); ?>
        </a>
        <span class="description" style="margin-left: 10px;">
          <?php esc_html_e( 'Download your Fincon invoice as PDF', 'df-fincon' ); ?>
        </span>
      </p>
      <?php else : ?>
      <p>
        <span class="description">
          <?php esc_html_e( 'Invoice PDF is being processed. Please check back later or contact support if you need immediate access.', 'df-fincon' ); ?>
        </span>
      </p>
      <?php endif; ?>
    </div>
    <?php
  }

  /**
   * Output the location selector field on checkout
   *
   * @param \WC_Checkout $checkout
   * @return void
   * @since 1.0.0
   */
  public static function add_location_selector_field( $checkout ): void {
    $active_locations = LocationManager::create()->get_sorted_active_locations();
    if ( empty( $active_locations ) ) {
      return;
    }
    
    $default_location = LocationManager::create()->get_default_location();
    $default_value = $default_location['code'] ?? '';
    
    // Get saved value from session or default
    $saved_value = WC()->session->get( 'df_fincon_selected_location', $default_value );
    
    $options = [];
    foreach ( $active_locations as $location ) {
      $options[$location['code']] = sprintf( '%s (%s)', $location['name'], $location['short_name'] );
    }
    
    echo '<div id="fincon_location_selector_field_wrapper">';
    
    woocommerce_form_field( self::FIELD_LOCATION_SELECTOR, [
      'type'        => 'select',
      'class'       => ['form-row-wide'],
      'label'       => __( 'Order Location', 'df-fincon' ),
      'required'    => true,
      'options'     => $options,
      'default'     => $saved_value,
    ], $saved_value );
    
    echo '</div>';
  }

  /**
   * Save the location selection to the Order Meta
   *
   * @param \WC_Order $order
   * @param array $data
   * @return void
   * @since 1.0.0
   */
  public static function save_location_to_order( $order, $data ): void {
    $location_code = null;
    
    // First check POST data (traditional checkout form field)
    if ( isset( $_POST[ self::FIELD_LOCATION_SELECTOR ] ) ) {
      $location_code = sanitize_text_field( $_POST[ self::FIELD_LOCATION_SELECTOR ] );
    }
    // If not in POST, check session (for shortcode-based checkout)
    elseif ( WC()->session ) {
      $location_code = WC()->session->get( 'df_fincon_selected_location' );
    }
    
    // If still no location code, get default location
    if ( empty( $location_code ) ) {
      $default_location = LocationManager::create()->get_default_location();
      $location_code = $default_location['code'] ?? '';
    }
    
    // Validate and save location
    if ( ! empty( $location_code ) ) {
      $location = LocationManager::create()->get_location( $location_code );
      
      if ( $location ) {
        $order->update_meta_data( OrderSync::META_LOCATION_CODE, $location_code );
        $order->update_meta_data( OrderSync::META_LOCATION_NAME, $location['name'] );
        $order->update_meta_data( OrderSync::META_REP_CODE, $location['rep_code'] ?? '' );
        
        // Also save to session for cart consistency
        if ( WC()->session ) {
          WC()->session->set( 'df_fincon_selected_location', $location_code );
        }
        
        Logger::debug( sprintf( 'Saved location "%s" to order %d', $location_code, $order->get_id() ) );
      }
    }
  }

  /**
   * Display selected location in customer My Account order view
   *
   * @param \WC_Order $order WooCommerce order object
   * @return void
   * @since 1.0.0
   */
  public static function display_location_in_order_view( \WC_Order $order ): void {
    $location_code = $order->get_meta( OrderSync::META_LOCATION_CODE );
    $location_name = $order->get_meta( OrderSync::META_LOCATION_NAME );
    
    if ( ! empty( $location_code ) ) {
      ?>
      <div class="fincon-order-location" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
        <h3><?php esc_html_e( 'Order Location', 'df-fincon' ); ?></h3>
        <p>
          <strong><?php esc_html_e( 'Location:', 'df-fincon' ); ?></strong>
          <?php echo esc_html( sprintf( '%s (%s)', $location_name, $location_code ) ); ?>
        </p>
      </div>
      <?php
    }
  }

  /**
   * Get human-readable label for price list
   *
   * @param int $price_list Price list number (1-6)
   * @return string Price list label
   * @since 1.0.0
   */
  public static function get_price_list_label( int $price_list ): string {
    $labels = [
      1 => __( 'Retail', 'df-fincon' ),
      2 => __( 'Dealer Pricelist 2', 'df-fincon' ),
      3 => __( 'Dealer Pricelist 3', 'df-fincon' ),
      4 => __( 'Dealer Pricelist 4', 'df-fincon' ),
      5 => __( 'Dealer Pricelist 5', 'df-fincon' ),
      6 => __( 'Dealer Pricelist 6', 'df-fincon' ),
    ];
    
    return $labels[$price_list] ?? sprintf( __( 'Price List %d', 'df-fincon' ), $price_list );
  }

  /**
   * Add location selector to cart page
   *
   * @return void
   * @since 1.0.0
   */
  public static function add_location_selector_to_cart(): void {
    $active_locations = LocationManager::create()->get_sorted_active_locations();
    if ( empty( $active_locations ) ) {
      return;
    }
    
    $default_location = LocationManager::create()->get_default_location();
    $default_value = $default_location['code'] ?? '';
    $saved_value = WC()->session->get( 'df_fincon_selected_location', $default_value );
    
    ?>
    <div class="fincon-cart-location-selector" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
      <h3><?php esc_html_e( 'Order Location', 'df-fincon' ); ?></h3>
      <p><?php esc_html_e( 'Select your preferred location for this order:', 'df-fincon' ); ?></p>
      
      <select id="df-fincon-cart-location" class="select" name="df_fincon_cart_location">
        <?php foreach ( $active_locations as $location ) : ?>
          <option value="<?php echo esc_attr( $location['code'] ); ?>"
            <?php selected( $saved_value, $location['code'] ); ?>>
            <?php echo esc_html( sprintf( '%s (%s)', $location['name'], $location['short_name'] ) ); ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <button type="button" id="df-fincon-update-location" class="button" style="margin-left: 10px;">
        <?php esc_html_e( 'Update Location', 'df-fincon' ); ?>
      </button>
      
      <span class="spinner" style="float: none; margin-left: 5px; display: none;"></span>
      <div id="df-fincon-location-message" style="margin-top: 10px; display: none;"></div>
      
      <?php wp_nonce_field( 'df_fincon_update_cart_location', 'df_fincon_cart_location_nonce' ); ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
      var $select = $('#df-fincon-cart-location');
      var $button = $('#df-fincon-update-location');
      var $spinner = $button.next('.spinner');
      var $message = $('#df-fincon-location-message');
      
      // Function to update location via AJAX
      function updateLocation() {
        var locationCode = $select.val();
        var nonce = $('#df_fincon_cart_location_nonce').val();
        
        $button.prop('disabled', true);
        $spinner.show();
        $message.hide().empty();
        
        $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
          action: 'df_fincon_update_cart_location',
          location_code: locationCode,
          nonce: nonce
        }, function(response) {
          if (response.success) {
            $message.html('<span style="color: green;">' + response.data.message + '</span>').show();
            // Refresh cart to update totals
            $(document.body).trigger('wc_fragment_refresh');
          } else {
            $message.html('<span style="color: red;">' + response.data.message + '</span>').show();
          }
          $button.prop('disabled', false);
          $spinner.hide();
        }).fail(function() {
          $message.html('<span style="color: red;"><?php esc_html_e( 'Server error. Please try again.', 'df-fincon' ); ?></span>').show();
          $button.prop('disabled', false);
          $spinner.hide();
        });
      }
      
      // Trigger on select change
      $select.on('change', updateLocation);
      
      // Keep button click functionality as fallback
      $button.on('click', updateLocation);
    });
    </script>
    <?php
  }

  /**
   * Add location selector to checkout page (for block/Elementor-based checkout)
   *
   * @return void
   * @since 1.0.0
   */
  public static function add_location_selector_to_checkout(): void {
    $active_locations = LocationManager::create()->get_sorted_active_locations();
    if ( empty( $active_locations ) ) {
      return;
    }
    
    $default_location = LocationManager::create()->get_default_location();
    $default_value = $default_location['code'] ?? '';
    $saved_value = $default_value;

    if ( isset( WC()->session ) && ! is_null( WC()->session ) ) 
      $saved_value = WC()->session->get( 'df_fincon_selected_location', $default_value );
    
    ?>
    <div class="fincon-checkout-location-selector" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
      <h2><?php _e( 'Order Location', 'df-fincon' ); ?><span class="required" aria-hidden="true">*</span></h2>
      <p><?php _e( 'Choose where you’d like this order to be fulfilled from. This helps us process your order correctly.', 'df-fincon' ); ?></p>
      
      <select id="df-fincon-checkout-location" class="select" name="df_fincon_checkout_location">
        <?php foreach ( $active_locations as $location ) : ?>
          <option value="<?php echo esc_attr( $location['code'] ); ?>"
            <?php selected( $saved_value, $location['code'] ); ?>>
            <?php echo esc_html( sprintf( '%s (%s)', $location['name'], $location['short_name'] ) ); ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <button type="button" id="df-fincon-update-checkout-location" class="button" style="margin-left: 10px;">
        <?php esc_html_e( 'Update Location', 'df-fincon' ); ?>
      </button>
      
      <span class="spinner" style="float: none; margin-left: 5px; display: none;"></span>
      <div id="df-fincon-checkout-location-message" style="margin-top: 10px; display: none;"></div>
      
      <?php wp_nonce_field( 'df_fincon_update_cart_location', 'df_fincon_checkout_location_nonce' ); ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
      var $select = $('#df-fincon-checkout-location');
      var $button = $('#df-fincon-update-checkout-location');
      var $spinner = $button.next('.spinner');
      var $message = $('#df-fincon-checkout-location-message');
      
      // Function to update location via AJAX
      function updateLocation() {
        var locationCode = $select.val();
        var nonce = $('#df_fincon_checkout_location_nonce').val();
        
        $button.prop('disabled', true);
        $spinner.show();
        $message.hide().empty();
        
        $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
          action: 'df_fincon_update_cart_location',
          location_code: locationCode,
          nonce: nonce
        }, function(response) {
          if (response.success) {
            $message.html('<span style="color: green;">' + response.data.message + '</span>').show();
            // Trigger checkout update to refresh any location-dependent data
            $(document.body).trigger('update_checkout');
          } else {
            $message.html('<span style="color: red;">' + response.data.message + '</span>').show();
          }
          $button.prop('disabled', false);
          $spinner.hide();
        }).fail(function() {
          $message.html('<span style="color: red;"><?php esc_html_e( 'Server error. Please try again.', 'df-fincon' ); ?></span>').show();
          $button.prop('disabled', false);
          $spinner.hide();
        });
      }
      
      // Trigger on select change
      $select.on('change', updateLocation);
      
      // Keep button click functionality as fallback
      $button.on('click', updateLocation);
    });
    </script>
    <?php
  }

  /**
   * AJAX handler for updating cart location
   *
   * @return void
   * @since 1.0.0
   */
  public static function update_cart_location_ajax(): void {
    check_ajax_referer( 'df_fincon_update_cart_location', 'nonce' );
    
    if ( ! isset( $_POST['location_code'] ) ) {
      wp_send_json_error( [ 'message' => __( 'Missing location code.', 'df-fincon' ) ] );
      return;
    }
    
    $location_code = sanitize_text_field( $_POST['location_code'] );
    $location = LocationManager::create()->get_location( $location_code );
    
    if ( ! $location ) {
      wp_send_json_error( [ 'message' => __( 'Invalid location code.', 'df-fincon' ) ] );
      return;
    }
    
    // Save to session
    WC()->session->set( 'df_fincon_selected_location', $location_code );
    
    wp_send_json_success( [
      'message' => __( 'Location Updated', 'df-fincon' ),
      'location_name' => $location['name']
    ] );
  }

  /**
   * AJAX handler for updating price list
   *
   * @return void
   * @since 1.0.0
   */
  public static function update_price_list_ajax(): void {
    check_ajax_referer( 'df_fincon_update_price_list', 'nonce' );
    
    if ( ! isset( $_POST['price_list'] ) ) {
      wp_send_json_error( [ 'message' => __( 'Missing price list.', 'df-fincon' ) ] );
      return;
    }
    
    $price_list = (int) sanitize_text_field( $_POST['price_list'] );
    
    // Validate price list
    if ( ! CustomerSync::is_valid_price_list( $price_list ) ) {
      wp_send_json_error( [ 'message' => __( 'Invalid price list.', 'df-fincon' ) ] );
      return;
    }
    
    // Check if user is allowed to use this price list
    if ( is_user_logged_in() ) {
      $user_id = get_current_user_id();
      $user_price_list = CustomerSync::get_display_price_list( $user_id );
      
      // Users can only switch between price lists they're authorized for
      if ( $price_list !== $user_price_list ) {
        // For now, only allow switching to user's assigned price list
        // In future, could allow switching between dealer price lists if authorized
        wp_send_json_error( [
          'message' => __( 'You are not authorized to use this price list.', 'df-fincon' )
        ] );
        return;
      }
    } else {
      // Guest users can only use retail price list
      if ( $price_list !== CustomerSync::PRICE_LIST_RETAIL ) {
        wp_send_json_error( [
          'message' => __( 'Please log in to use dealer pricing.', 'df-fincon' )
        ] );
        return;
      }
    }
    
    // Save to session
    $success = self::set_current_price_list( $price_list );
    
    if ( ! $success ) {
      wp_send_json_error( [ 'message' => __( 'Failed to update price list.', 'df-fincon' ) ] );
      return;
    }
    
    // Update cart item prices if needed
    self::update_cart_prices_for_price_list( $price_list );
    
    $price_list_label = self::get_price_list_label( $price_list );
    
    wp_send_json_success( [
      'message' => sprintf( __( 'Price list updated to %s.', 'df-fincon' ), $price_list_label ),
      'price_list' => $price_list,
      'price_list_label' => $price_list_label,
    ] );
  }

  /**
   * AJAX handler for clearing user session pricing
   *
   * @return void
   * @since 1.0.0
   */
  public static function clear_user_session_pricing_ajax(): void {
    // Verify nonce
    check_ajax_referer( 'df_fincon_clear_user_session_pricing', 'nonce' );
    
    // Check capability - allow users to clear their own session, admins to clear any
    if ( ! is_user_logged_in() ) {
      wp_send_json_error( [ 'message' => __( 'You must be logged in to clear session pricing.', 'df-fincon' ) ] );
      return;
    }
    
    $current_user_id = get_current_user_id();
    $target_user_id = isset( $_POST['user_id'] ) ? (int) sanitize_text_field( $_POST['user_id'] ) : $current_user_id;
    
    // Check if current user can clear session for target user
    if ( $target_user_id !== $current_user_id && ! current_user_can( 'manage_woocommerce' ) ) {
      wp_send_json_error( [ 'message' => __( 'You do not have permission to clear session pricing for this user.', 'df-fincon' ) ] );
      return;
    }
    
    // Clear the price list session
    if ( WC()->session ) {
      WC()->session->set( self::SESSION_PRICE_LIST_KEY, CustomerSync::PRICE_LIST_RETAIL );
      Logger::debug( sprintf( 'Cleared price list session for user %d via AJAX', $target_user_id ) );
    }
    
    wp_send_json_success( [
      'message' => __( 'User session pricing cleared successfully.', 'df-fincon' ),
      'user_id' => $target_user_id,
    ] );
  }

  /**
   * Update cart item prices when price list changes
   *
   * @param int $price_list New price list
   * @return void
   * @since 1.0.0
   */
  private static function update_cart_prices_for_price_list( int $price_list ): void {
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
      return;
    }
    
    $cart = WC()->cart->get_cart();
    
    foreach ( $cart as $cart_item_key => $cart_item ) {
      WC()->cart->cart_contents[$cart_item_key]['df_fincon_price_list'] = $price_list;
      
      $product = $cart_item['data'];
      $dealer_price = self::calculate_dealer_price( $product );
      
      if ( $dealer_price !== null ) {
        self::get_dealer_price( $product );
        WC()->cart->cart_contents[$cart_item_key]['df_fincon_price_list_price'] = $dealer_price;
        
        $product->set_price( $dealer_price );
      }
    }
    
    WC()->cart->set_session();
    
  }

  /**
   * Save price list to order meta
   *
   * @param \WC_Order $order WooCommerce order object
   * @param array $data Checkout data
   * @return void
   * @since 1.0.0
   */
  public static function save_price_list_to_order( $order, $data ): void {
    $price_list = self::get_current_price_list();
    if ( $price_list ) 
      $order->update_meta_data( self::ORDER_META_PRICE_LIST, $price_list );    
  }

  /**
   * Filter single product price html
   *
   * @param string $price_html Original price HTML
   * @param array $product Product object
   * @return string Filtered price HTML
   * @since 1.0.0
   */
  public static function filter_single_product_display_price( $price_html, $product ) {
    
    if ( ! self::is_dealer_customer() ) 
      return $price_html;

    return self::filter_dealer_price_html( $price_html, $product );
    
  }


  /**
   * Add price list data to cart item when added to cart
   *
   * @param array $cart_item_data Existing cart item data
   * @param int $product_id Product ID
   * @param int $variation_id Variation ID
   * @return array Modified cart item data
   * @since 1.0.0
   */
  public static function add_price_list_to_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
    $price_list = self::get_current_price_list();
    
    // Only store price list if it's a dealer price list (2-6)
    if ( $price_list > CustomerSync::PRICE_LIST_RETAIL ) {
      $cart_item_data['df_fincon_price_list'] = $price_list;
      
      // Also store the calculated price at time of adding to cart
      $product = wc_get_product( $variation_id ?: $product_id );
      if ( $product ) {
        $dealer_price = self::calculate_dealer_price( $product );
        if ( $dealer_price !== null ) {
          $cart_item_data['df_fincon_price_list_price'] = $dealer_price;
        }
      }
      
    }
    
    return $cart_item_data;
  }

  /**
   * Restore cart item data from session
   *
   * @param array $cart_item Cart item data
   * @param array $values Session values
   * @param string $key Cart item key
   * @return array Modified cart item data
   * @since 1.0.0
   */
  public static function get_cart_item_from_session( array $cart_item, array $values, string $key ): array {
    // Restore price list data from session
    if ( isset( $values['df_fincon_price_list'] ) ) {
      $cart_item['df_fincon_price_list'] = $values['df_fincon_price_list'];
    }
    
    if ( isset( $values['df_fincon_price_list_price'] ) ) {
      $cart_item['df_fincon_price_list_price'] = $values['df_fincon_price_list_price'];
    }
    
    return $cart_item;
  }

  /**
   * Get current price list from session or user meta
   *
   * @return int|null Price list number (1-6) or null if not set
   * @since 1.0.0
   */
  public static function get_current_price_list(): ?int {
    // Check session first
    // if ( WC()->session ) {
    //   $session_price_list = WC()->session->get( self::SESSION_PRICE_LIST_KEY );
    //   if ( $session_price_list && CustomerSync::is_valid_price_list( $session_price_list ) ) {
    //     return (int) $session_price_list;
    //   }
    // }
    
    // Check logged in user
    if ( is_user_logged_in() ) {
      $user_id = get_current_user_id();
      $price_list = CustomerSync::get_display_price_list( $user_id );
      if ( $price_list ) {
        // Cache in session
        if ( WC()->session ) {
          WC()->session->set( self::SESSION_PRICE_LIST_KEY, $price_list );
        }
        return $price_list;
      }
    }
    
    // Default to retail price list
    return CustomerSync::PRICE_LIST_RETAIL;
  }

  /**
   * Set current price list in session
   *
   * @param int $price_list Price list number (1-6)
   * @return bool True if successful
   * @since 1.0.0
   */
  public static function set_current_price_list( int $price_list ): bool {
    if ( ! CustomerSync::is_valid_price_list( $price_list ) ) {
      return false;
    }
    
    if ( WC()->session ) {
      WC()->session->set( self::SESSION_PRICE_LIST_KEY, $price_list );
      return true;
    }
    
    return false;
  }

  /**
   * Get price for a specific price list
   *
   * @param \WC_Product $product WooCommerce product
   * @param int $price_list Price list number (1-6)
   * @return float|null Price or null if not available
   * @since 1.0.0
   */
  public static function get_price_for_price_list( $product, int $price_list ): ?float {
    if ( ! CustomerSync::is_valid_price_list( $price_list ) ) {
      return null;
    }
    
    if ( $price_list === 1 ) {
      // Price list 1 is the regular price
      return (float) $product->get_regular_price();
    }
    
    // Price lists 2-6 are stored in meta
    $meta_key = CustomerSync::get_price_list_meta_key( $price_list );
    $price = $product->get_meta( $meta_key );
    
    return $price ? (float) $price : null;
  }

  /**
   * Get promotional price for a specific price list
   *
   * @param \WC_Product $product WooCommerce product
   * @param int $price_list Price list number (1-6)
   * @return float | string Promotional price or empty string if not available
   * @since 1.0.0
   */
  public static function get_promotional_price_for_price_list( $product, int $price_list ): float | string {
    $product_sales_price = $product->get_sale_price();

    if ( CustomerSync::is_valid_price_list( $price_list ) && self::is_promotional_price_active( $product, $price_list ) ) {
      $meta_key = CustomerSync::get_promo_price_meta_key( $price_list );
      $price = $product->get_meta( $meta_key );
      if ( $price && (float) $price > (float) $product_sales_price )
        return (float) $price;
    }
    
    return $product_sales_price;
  }

  /**
   * Check if promotional price is active for a price list
   *
   * @param \WC_Product $product WooCommerce product
   * @param int $price_list Price list number (1-6)
   * @return bool True if promotional price is active
   * @since 1.0.0
   */
  public static function is_promotional_price_active( $product, int $price_list ): bool {
    if ( $price_list === 1 ) {
      // For price list 1, check WooCommerce sale dates
      $sale_price = $product->get_sale_price();
      if ( empty( $sale_price ) ) 
        return false;
      
      
      $date_from = $product->get_date_on_sale_from();
      $date_to = $product->get_date_on_sale_to();
      
      return $product->is_on_sale();
    }
    
    // For price lists 2-6, check promo flag
    $flag_key = CustomerSync::get_promo_flag_meta_key( $price_list );
    $flag = $product->get_meta( $flag_key );
    
    return $flag === 'yes';
  }

  /**
   * Get dealer price for current customer
   *
   * @param \WC_Product $product WooCommerce product
   * @return float|null Dealer price or null if not available
   * @since 1.0.0
   */
  public static function get_dealer_price( $product ): ?float {
    $price_list = self::get_current_price_list();
    
    // Check promotional price first
    $promo_price = self::get_promotional_price_for_price_list( $product, $price_list );
    if ( $promo_price !== null ) 
      return (float) $promo_price;
        
    // Fall back to regular price for price list
    return self::get_price_for_price_list( $product, $price_list );
  }

  /**
   * Calculate display price with promotions
   *
   * @param \WC_Product $product WooCommerce product
   * @return float
   * @since 1.0.0
   */
  public static function calculate_dealer_price( $product ): float {
    $price_list = self::get_current_price_list();
    $regular_price = $product->get_regular_price();
    $dealer_price = self::get_price_for_price_list( $product, $price_list );
    $dealer_promo_price = self::get_promotional_price_for_price_list( $product, $price_list );
    $sell_prices = array_filter([$regular_price, $dealer_price, $dealer_promo_price], 'is_numeric');
    if ( empty( $sell_prices ) ) 
      return 0.0;

    return min($sell_prices);
  }

  /**
   * Filter cart item price display
   *
   * @param string $price_html Original price HTML
   * @param array $cart_item Cart item data
   * @param string $cart_item_key Cart item key
   * @return string Filtered price HTML
   * @since 1.0.0
   */
  public static function filter_cart_item_price_display( $price_html, $cart_item, $cart_item_key ): string {
    if (! self::is_dealer_customer())
      return $price_html;
    $product = $cart_item['data'];
    return self::filter_dealer_price_html( $price_html, $product );
  }

  /**
   * Filter item price html
   *
   * @param string $price_html Original price HTML
   * @param object $product Product object
   * @return string Filtered price HTML
   * @since 1.0.0
   */

  private static function filter_dealer_price_html ( $price_html, $product ):string  {
    if ( is_admin( ) )
      return $price_html;
    if ( ! self::is_dealer_customer() ) 
      return $price_html;
  
    $regular_price = $product->get_price();
    $dealer_price = self::calculate_dealer_price( $product );
    
    if ( $dealer_price < $regular_price ) 
      return '<del>' . self::LABEL_RETAIL_PRICE . wc_price( $regular_price ) . '</del> ' . '<ins>' . self::LABEL_DEALER_PRICE  . wc_price( $dealer_price ) . '</ins>';
    
    return wc_price( $regular_price );
  }

  /**
   * Filter cart item subtotal display
   *
   * @param string $subtotal_html Original subtotal HTML
   * @param array $cart_item Cart item data
   * @param string $cart_item_key Cart item key
   * @return string Filtered subtotal HTML
   * @since 1.0.0
   */
  public static function filter_cart_item_subtotal_display( $subtotal_html, $cart_item, $cart_item_key ): string {
    // Only apply for dealer customers
    if ( ! self::is_dealer_customer() ) 
      return $subtotal_html;
    
    $product = $cart_item['data'];
    $quantity = $cart_item['quantity'];
    $dealer_price = self::calculate_dealer_price( $product );
    $subtotal = $dealer_price * $quantity;
    
    return wc_price( $subtotal );
  }

  /**
   * Format price display with retail/dealer labels
   *
   * @param float $regular_price Regular price
   * @param float|null $sale_price Sale price (optional)
   * @param int $price_list Price list number
   * @return string Formatted price HTML
   * @since 1.0.0
   */
  public static function format_cart_price_display( float $regular_price, ?float $sell_price ): string {        
    if ( $sell_price ) 
      return '<del>' . wc_price( $regular_price ) . '</del> <ins>' . wc_price( $sell_price ) . '</ins>';
    
    return wc_price( $regular_price );

  }

  /**
   * Check if current customer is a dealer
   *
   * @return bool True if dealer customer
   * @since 1.0.0
   */
  public static function is_dealer_customer(): bool {
    if ( ! is_user_logged_in() ) {
      return false;
    }
    
    $user_id = get_current_user_id();
    return CustomerSync::is_customer_dealer( $user_id );
  }

  /**
   * Set customer price list on login
   *
   * @param string $user_login User login
   * @param \WP_User $user User object
   * @return void
   * @since 1.0.0
   */
  public static function set_customer_price_list_on_login( $user_login, $user ): void {
    $price_list = CustomerSync::get_display_price_list( $user->ID );
    self::set_current_price_list( $price_list );
    
    Logger::debug( sprintf( 'Set price list %d for user %d on login', $price_list, $user->ID ) );
  }

  /**
   * Clear price list on logout
   *
   * @return void
   * @since 1.0.0
   */
  public static function clear_price_list_on_logout(): void {
    self::set_current_price_list( CustomerSync::PRICE_LIST_RETAIL );
    
    Logger::debug( 'Cleared price list on logout' );
  }

  /**
   * Clear price list when cart is emptied
   *
   * @return void
   * @since 1.0.0
   */
  public static function clear_price_list_on_cart_empty(): void {
    // Only clear price list for guest users
    if ( ! is_user_logged_in() ) {
      self::set_current_price_list( CustomerSync::PRICE_LIST_RETAIL );
      Logger::debug( 'Cleared price list on cart empty for guest user' );
    }
  }

  /**
   * Clear price list when user profile is updated
   *
   * @param int $user_id User ID
   * @param \WP_User $old_user_data Old user data (optional)
   * @return void
   * @since 1.0.0
   */
  public static function clear_price_list_on_profile_update( $user_id, $old_user_data = null ): void {
    // Clear session price list for the updated user
    if ( WC()->session ) {
      WC()->session->set( self::SESSION_PRICE_LIST_KEY, CustomerSync::PRICE_LIST_RETAIL );
      Logger::debug( sprintf( 'Cleared price list on profile update for user %d', $user_id ) );
    }
  }


  /**
   * Sync cart prices with current price list when cart loads from session
   *
   * @param \WC_Cart $cart WooCommerce cart object
   * @return void
   * @since 1.0.0
   */
  public static function sync_cart_prices_with_price_list( $cart ): void {
    if ( ! $cart || $cart->is_empty() ) {
      return;
    }
    
    $price_list = self::get_current_price_list();
    $is_dealer = $price_list > CustomerSync::PRICE_LIST_RETAIL;
    
    // Only sync for dealer price lists
    if ( ! $is_dealer ) {
      return;
    }
    
    $cart_contents = $cart->get_cart();
    $updated = false;
    
    foreach ( $cart_contents as $cart_item_key => $cart_item ) {
      $product = $cart_item['data'];
      
      // Get price for current price list
      $price = self::get_price_for_price_list( $product, $price_list );
      
      if ( $price !== null ) {
        // Check for promotional price
        $promo_price = self::get_promotional_price_for_price_list( $product, $price_list );
        $final_price = $promo_price ?: $price;
        
        // Update product price in cart
        $product->set_price( $final_price );
        
        // Update cart item data
        $cart->cart_contents[$cart_item_key]['df_fincon_price_list'] = $price_list;
        $cart->cart_contents[$cart_item_key]['df_fincon_price_list_price'] = $final_price;
        
        $updated = true;
      }
    }
    
    if ( $updated ) {
      $cart->set_session();
      Logger::debug( sprintf( 'Synced cart prices with price list %d', $price_list ) );
    }
  }

  /**
   * Get cart total savings for dealer customers
   *
   * @return array{retail_total: float, dealer_total: float, savings: float, savings_percentage: float}|null
   * @since 1.0.0
   */
  public static function get_cart_savings(): ?array {
    if ( ! WC()->cart || WC()->cart->is_empty() || ! self::is_dealer_customer() ) {
      return null;
    }
    
    $price_list = self::get_current_price_list();
    if ( $price_list <= CustomerSync::PRICE_LIST_RETAIL ) {
      return null;
    }
    
    $retail_total = 0;
    $dealer_total = 0;
    $cart = WC()->cart->get_cart();
    
    foreach ( $cart as $cart_item ) {
      $product = $cart_item['data'];
      $quantity = $cart_item['quantity'];
      
      // Retail price (price list 1)
      $retail_price = self::get_price_for_price_list( $product, CustomerSync::PRICE_LIST_RETAIL );
      if ( $retail_price !== null ) {
        $retail_total += $retail_price * $quantity;
      }
      
      // Dealer price (current price list)
      $dealer_price = self::get_dealer_price( $product );
      if ( $dealer_price !== null ) {
        $dealer_total += $dealer_price * $quantity;
      }
    }
    
    if ( $retail_total <= 0 || $dealer_total <= 0 ) {
      return null;
    }
    
    $savings = $retail_total - $dealer_total;
    $savings_percentage = $retail_total > 0 ? ( $savings / $retail_total ) * 100 : 0;
    
    return [
      'retail_total' => $retail_total,
      'dealer_total' => $dealer_total,
      'savings' => $savings,
      'savings_percentage' => $savings_percentage,
    ];
  }

  /**
   * Check if current user has DBG Dealer sub account enabled
   *
   * @return bool True if enabled, false otherwise
   * @since 1.0.0
   */
  public static function is_dbg_dealer_sub_account_enabled(): bool {
    if ( ! is_user_logged_in() ) {
      return false;
    }
    
    $user_id = get_current_user_id();
    return (bool) get_user_meta( $user_id, self::USER_META_DBG_DEALER_SUB_ACCOUNT, true );
  }
  
  /**
   * Disable address fields on checkout for DBG Dealer sub accounts
   *
   * @param array $fields Checkout fields
   * @return array Modified checkout fields
   * @since 1.0.0
   */
  public static function disable_address_fields_for_dbg_dealer( array $fields ): array {
    // Check if user is logged in
    if ( ! is_user_logged_in() ) {
      return $fields;
    }
    
    $user_id = get_current_user_id();
    $is_dbg_dealer_sub_account = (bool) get_user_meta( $user_id, self::USER_META_DBG_DEALER_SUB_ACCOUNT, true );
    
    if ( ! $is_dbg_dealer_sub_account ) {
      return $fields;
    }
    
    // Define address fields to disable
    $address_fields_to_disable = [
      'billing_first_name',
      'billing_last_name',
      'billing_company',
      'billing_address_1',
      'billing_address_2',
      'billing_city',
      'billing_state',
      'billing_postcode',
      'billing_country',
      'billing_phone',
      'billing_email',
      'shipping_first_name',
      'shipping_last_name',
      'shipping_company',
      'shipping_address_1',
      'shipping_address_2',
      'shipping_city',
      'shipping_state',
      'shipping_postcode',
      'shipping_country',
    ];
    
    // Make fields readonly and disabled
    foreach ( $address_fields_to_disable as $field_key ) {
      if ( isset( $fields['billing'][$field_key] ) ) {
        $fields['billing'][$field_key]['custom_attributes']['readonly'] = 'readonly';
        $fields['billing'][$field_key]['custom_attributes']['disabled'] = 'disabled';
        $fields['billing'][$field_key]['class'][] = 'df-fincon-field-disabled';
      }
      if ( isset( $fields['shipping'][$field_key] ) ) {
        $fields['shipping'][$field_key]['custom_attributes']['readonly'] = 'readonly';
        $fields['shipping'][$field_key]['custom_attributes']['disabled'] = 'disabled';
        $fields['shipping'][$field_key]['class'][] = 'df-fincon-field-disabled';
      }
    }
    
    return $fields;
  }
  
  /**
   * Disable address fields on My Account edit address page for DBG Dealer sub accounts
   *
   * @param array $fields Address fields
   * @param string $load_address Type of address (billing or shipping)
   * @return array Modified address fields
   * @since 1.0.0
   */
  public static function disable_myaccount_address_fields_for_dbg_dealer( array $fields, string $load_address ): array {
    if ( ! self::is_dbg_dealer_sub_account_enabled() ) {
      return $fields;
    }
    
    // Define address fields to disable (both billing and shipping)
    $address_fields_to_disable = [
      'first_name',
      'last_name',
      'company',
      'address_1',
      'address_2',
      'city',
      'state',
      'postcode',
      'country',
      'phone',
      'email',
    ];
    
    // Make fields readonly and disabled
    foreach ( $address_fields_to_disable as $field_key ) {
      $full_field_key = $load_address . '_' . $field_key;
      if ( isset( $fields[$full_field_key] ) ) {
        $fields[$full_field_key]['custom_attributes']['readonly'] = 'readonly';
        $fields[$full_field_key]['custom_attributes']['disabled'] = 'disabled';
        $fields[$full_field_key]['class'][] = 'df-fincon-field-disabled';
      }
    }
    
    return $fields;
  }
  
  /**
   * Display message on My Account addresses page for DBG Dealer sub accounts
   *
   * @return void
   * @since 1.0.0
   */
  public static function maybe_display_dbg_dealer_address_message(): void {
    if ( ! self::is_dbg_dealer_sub_account_enabled() ) {
      return;
    }
    
    // This hook runs on the addresses page (woocommerce_before_account_addresses)
    // No need for additional checks
    echo '<div class="woocommerce-message woocommerce-info df-fincon-dbg-dealer-message" style="margin-bottom: 20px;">';
    echo '<p><strong>' . esc_html__( 'Addresses managed by your main DBG Dealer account', 'df-fincon' ) . '</strong></p>';
    echo '<p>' . esc_html__( 'Please contact your main DBG Dealer account administrator for address changes.', 'df-fincon' ) . '</p>';
    echo '</div>';
  }
  
  /**
   * Disable save address button on edit address page for DBG Dealer sub accounts
   *
   * @return void
   * @since 1.0.0
   */
  public static function maybe_disable_save_address_button(): void {
    if ( ! self::is_dbg_dealer_sub_account_enabled() ) {
      return;
    }
    
    // Output JavaScript to disable the save button
    ?>
    <script>
    jQuery(document).ready(function($) {
      // Disable the save address button
      $('button[name="save_address"]').prop('disabled', true).addClass('disabled');
      
      // Also hide the button if preferred
      // $('button[name="save_address"]').hide();
    });
    </script>
    <?php
  }
  
  /**
   * Modify My Address description to remove edit links for DBG Dealer sub accounts
   *
   * @param string $description Current description
   * @return string Modified description
   * @since 1.0.0
   */
  public static function modify_my_address_description( string $description ): string {
    if ( ! self::is_dbg_dealer_sub_account_enabled() ) {
      return $description;
    }
    
    // Remove edit links from the description - matches "Edit", "Edit Billing address", "Edit Shipping address", etc.
    $description = preg_replace( '/<a[^>]*>Edit[^<]*<\/a>/i', '', $description );
    
    return $description;
  }
  
  /**
   * Remove addresses menu item from My Account navigation for DBG Dealer sub accounts
   *
   * @param array $menu_items My Account menu items
   * @return array Filtered menu items
   * @since 1.0.0
   */
  public static function maybe_remove_addresses_menu_item( array $menu_items ): array {
    if ( ! self::is_dbg_dealer_sub_account_enabled() ) {
      return $menu_items;
    }
    
    // Remove the addresses menu item
    unset( $menu_items['edit-address'] );
    
    return $menu_items;
  }
  
  /**
   * Display checkout notice for DBG Dealer sub accounts
   *
   * @return void
   * @since 1.0.0
   */
  public static function maybe_display_checkout_notice_for_dbg_dealer(): void {
    if ( ! self::is_dbg_dealer_sub_account_enabled() ) {
      return;
    }
    
    echo '<div class="woocommerce-message woocommerce-info df-fincon-dbg-dealer-checkout-notice" style="margin-bottom: 20px;">';
    echo '<p><strong>' . esc_html__( 'Address Management Notice', 'df-fincon' ) . '</strong></p>';
    echo '<p>' . esc_html__( 'Your billing and shipping addresses are managed by your main DBG Dealer account and cannot be edited here.', 'df-fincon' ) . '</p>';
    echo '<p>' . esc_html__( 'Please contact your main DBG Dealer account administrator for any address changes.', 'df-fincon' ) . '</p>';
    echo '</div>';
  }
  
  /**
   * Display "Clear session pricing" button on frontend account edit page
   *
   * @return void
   * @since 1.0.0
   */
  public static function display_frontend_session_pricing_button(): void {
    // Only show to logged-in users
    if ( ! is_user_logged_in() ) {
      return;
    }
    
    $user_id = get_current_user_id();
    $nonce = wp_create_nonce( 'df_fincon_clear_user_session_pricing' );
    ?>
    <fieldset>
      <legend><?php esc_html_e( 'Fincon Session Pricing', 'df-fincon' ); ?></legend>
      <p class="form-row">
        <?php esc_html_e( 'Clear your cached price list session to force a fresh lookup from Fincon.', 'df-fincon' ); ?>
      </p>
      <p class="form-row">
        <button type="button"
                id="df-fincon-clear-session-pricing-frontend"
                class="button"
                data-user-id="<?php echo esc_attr( $user_id ); ?>"
                data-nonce="<?php echo esc_attr( $nonce ); ?>">
          <?php esc_html_e( 'Clear session pricing', 'df-fincon' ); ?>
        </button>
        <span class="spinner" style="float: none; margin-left: 5px; display: none;"></span>
        <div id="df-fincon-clear-session-message-frontend" style="margin-top: 10px; display: none;"></div>
      </p>
    </fieldset>
    <script>
    jQuery(document).ready(function($) {
      $('#df-fincon-clear-session-pricing-frontend').on('click', function() {
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $message = $('#df-fincon-clear-session-message-frontend');
        var userId = $button.data('user-id');
        var nonce = $button.data('nonce');
        
        $button.prop('disabled', true);
        $spinner.show();
        $message.hide().empty();
        
        $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
          action: 'df_fincon_clear_user_session_pricing',
          user_id: userId,
          nonce: nonce
        }, function(response) {
          $spinner.hide();
          $button.prop('disabled', false);
          
          if (response.success) {
            $message.html('<div class="woocommerce-message">' + response.data.message + '</div>').show();
          } else {
            $message.html('<div class="woocommerce-error">' + response.data.message + '</div>').show();
          }
        }).fail(function() {
          $spinner.hide();
          $button.prop('disabled', false);
          $message.html('<div class="woocommerce-error"><?php esc_html_e( 'AJAX request failed. Please try again.', 'df-fincon' ); ?></div>').show();
        });
      });
    });
    </script>
    <?php
  }

}
