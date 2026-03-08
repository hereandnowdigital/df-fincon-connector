<?php
/**
 * Customer Synchronization helper
 *
 * @package df-fincon-connector
 * @author  Elizabeth Meyer
 * @since   0.0.1
 */

namespace DF_FINCON;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class CustomerSync {

  public const OPTIONS_NAME = Plugin::OPTIONS_NAME . '_customer';

  public const BATCH_STATE_OPTION_NAME = self::OPTIONS_NAME . '_batch';

  public const META_ACCNO = '_fincon_accno';

  public const META_ACCOUNT_NAME = 'account_name';

  public const META_DELIVERY_INSTRUCTIONS = 'delivery_instructions'; 

  public const META_PRICE_STRUCTURE = '_fincon_price_struc';

  public const META_ACTIVE = '_fincon_active';

  public const META_ON_HOLD = '_fincon_on_hold';

  public const META_CHANGED_TIMESTAMP = '_fincon_customer_changed_timestamp';

  public const META_LAST_SYNC_TIMESTAMP = '_fincon_last_sync_timestamp';

  const ROLE_RETAIL = 'df_customer_retail';

  const ROLE_DEALER = 'df_customer_dealer';

  const STATUS_CREATE = 'create';
  const STATUS_UPDATE = 'update';
  const STATUS_SKIP   = 'skip';

  /**
   * Price list constants
   *
   * @since 1.0.0
   */
  const PRICE_LIST_MIN = 1;
  const PRICE_LIST_MAX = 6;
  const PRICE_LIST_DEFAULT = 1;
  const PRICE_LIST_RETAIL = 1;
  const PRICE_LIST_DEALER_MIN = 2;
  const PRICE_LIST_DEALER_MAX = 6;

  /**
   * Price list meta field names
   *
   * @since 1.0.0
   */
    const META_PRICE_LIST_VALIDATED = '_fincon_price_list_validated';

  /**
   * Get customer import options
   */
  public static function get_options(): array {
    $defaults = [
      'customer_batch_size' => 100,
      'customer_import_only_changed' => 0,
      'customer_weblist_only' => 1,
      // New cron settings
      'customer_sync_schedule_enabled' => 0,
      'customer_sync_schedule_frequency' => 'daily',
      'customer_sync_schedule_time' => '23:00',
      'customer_sync_schedule_day' => 1,
    ];
    return wp_parse_args( get_option( self::OPTIONS_NAME, [] ), $defaults );
  }

  /**
   * Batch state helpers (mirrors ProductSync implementation)
   */
  public static function get_batch_state(): array {
    $default = [
      'in_progress'     => false,
      'last_rec_no'     => 0,
      'total_processed' => 0,
      'batch_size'      => 100,
      'started_at'      => null,
      'completed_at'    => null,
    ];
    return wp_parse_args( get_option( self::BATCH_STATE_OPTION_NAME, [] ), $default );
  }

  public static function start_batch_import( int $batch_size ): void {
    update_option(
      self::BATCH_STATE_OPTION_NAME,
      [
        'in_progress'     => true,
        'last_rec_no'     => 0,
        'total_processed' => 0,
        'batch_size'      => $batch_size,
        'started_at'      => current_time( 'mysql' ),
        'completed_at'    => null,
      ],
      false
    );
  }
  public static function update_batch_progress( int $last_rec_no, int $count ): void {
    $state = self::get_batch_state();
    $state['last_rec_no'] = $last_rec_no;
    $state['total_processed'] += $count;
    update_option( self::BATCH_STATE_OPTION_NAME, $state, false );
  }

  public static function complete_batch_import(): void {
    $state = self::get_batch_state();
    $state['in_progress']  = false;
    $state['completed_at'] = current_time( 'mysql' );
    update_option( self::BATCH_STATE_OPTION_NAME, $state, false );
  }

  public static function reset_batch_state(): void {
    delete_option( self::BATCH_STATE_OPTION_NAME );
  }

  /**
   * Locate WordPress user IDs linked to a FinCon AccNo
   *
   * @param string $accno
   * @return array<int>
   */
  public static function find_customers_by_accno( string $accno ): array {
    if ( empty( $accno ) )
      return [];

    $users = get_users( [
      'meta_key'   => self::META_ACCNO,
      'meta_value' => $accno,
      'fields'     => 'ID',
      'number'     => -1,
    ] );

    return array_map( 'intval', $users );
  }

  /**
   * Create or update WooCommerce customers based on FinCon data.
   *
   * @param array $debtor
   * @param bool $update_only_changed Should we skip if not changed since last sync?
   * @param bool $create_if_missing Whether to create a new customer if none found.
   * @return array{status:string,user_ids:int[]}
   */
  public static function create_or_update_customer( array $debtor, bool $update_only_changed = false, bool $create_if_missing = true ): array {
    $acc_no = trim( $debtor['AccNo'] ?? '' );
    if ( empty( $acc_no ) )
      return [ 'status' => self::STATUS_SKIP, 'user_ids' => [] ];

    $change_timestamp = self::build_timestamp_from_fincon_values(
      $debtor['ChangeDate'] ?? '',
      $debtor['ChangeTime'] ?? ''
    );

    $user_ids = self::find_customers_by_accno( $acc_no );

    // Fallback to matching by email if available
    if ( empty( $user_ids ) && ! empty( $debtor['Email'] ) ) {
      $user = get_user_by( 'email', $debtor['Email'] );
      if ( $user )
        $user_ids = [ $user->ID ];
    }

    $created_user_id = null;
    if ( empty( $user_ids ) ) {
      if ( $create_if_missing ) {
        $created_user_id = self::create_customer_from_fincon( $debtor );
        if ( is_wp_error( $created_user_id ) )
          return [ 'status' => self::STATUS_SKIP, 'user_ids' => [] ];
        $user_ids = [ $created_user_id ];
      } else {
        // No matching customer and creation disabled → skip
        Logger::debug( sprintf( 'Customer with AccNo %s not found, creation disabled.', $acc_no ) );
        return [ 'status' => self::STATUS_SKIP, 'user_ids' => [] ];
      }
    }

    $status = $created_user_id ? self::STATUS_CREATE : self::STATUS_UPDATE;
    $updated_ids = [];

    foreach ( $user_ids as $user_id ) {
      $last_sync = (int) get_user_meta( $user_id, self::META_CHANGED_TIMESTAMP, true );

      if ( $update_only_changed && $last_sync && $last_sync >= $change_timestamp ) {
        $status = self::STATUS_SKIP;
        continue;
      }

      self::update_customer_meta( $user_id, $debtor );
      self::apply_client_type_role( $user_id, $debtor );

      $updated_ids[] = $user_id;
    }

    $result = [ 'status' => $status, 'user_ids' => $updated_ids ?: $user_ids ];
    
    // Log summary of customer sync with price list info
    $price_structure = (int) ( $debtor['PriceStruc'] ?? 1 );
    $validated_price_list = self::validate_price_structure( $price_structure );
    $role = self::map_price_structure_to_role( $validated_price_list );
    
    Logger::info( sprintf(
      'Customer sync completed: AccNo %s, Status: %s, Users: %d, PriceStruc: %d → PriceList: %d → Role: %s',
      $acc_no,
      $status,
      count( $result['user_ids'] ),
      $price_structure,
      $validated_price_list,
      $role
    ) );
    
    return $result;
  }

  /**
   * Create a new WooCommerce customer from FinCon data.
   */
  private static function create_customer_from_fincon( array $debtor ) {
    
    $email = self::extract_email_address ( $debtor );
        
    if ( empty( $email ) ) {
        $acc_no = $debtor['AccNo'] ?? 'Unknown';
        $deb_name = $debtor['DebName'] ?? 'Unknown';
        
        Logger::error( "$acc_no - $deb_name not created as user because no email." );

        return new \WP_Error( 'fincon_no_email', 'User skipped due to missing email' );
    }

    $username = sanitize_user( strtolower( $debtor['Contact'] ?? $debtor['DebName']) );
    if ( empty( $username ) )
      $username = 'fincon_' . wp_generate_password( 6, false );

    $user_id = wp_insert_user( [
      'user_login' => $username,
      'user_email' => $email,
      'user_pass'  => wp_generate_password( 16 ),
      'display_name' => $debtor['Contact'] ?? $debtor['DebName'] ?? $username, 
      'first_name'   => $debtor['Contact'] ?? '',
      'role'       => 'customer',
    ] );

    if ( is_wp_error( $user_id ) )
      return $user_id;

    return $user_id;
  }



  /**
   * Update customer meta fields with enhanced price structure validation
   *
   * @param int $user_id WordPress user ID
   * @param array $debtor Fincon debtor data
   * @since 1.0.0
   */
  private static function update_customer_meta( int $user_id, $debtor): void {
    // Extract and validate price structure
    $raw_price_structure = (int) ( $debtor['PriceStruc'] ?? 1 );
    $validated_price_list = self::validate_price_structure( $raw_price_structure );
    
    // Store values
    update_user_meta( $user_id, self::META_PRICE_STRUCTURE, $raw_price_structure );
    update_user_meta( $user_id, self::META_PRICE_LIST_VALIDATED, 'yes' );
    
    // Log price list assignment
    self::log_price_list_assignment( $user_id, $raw_price_structure, $validated_price_list, 'sync_update' );
    
    // Store active status
    $is_active = strtoupper( (string) ( $debtor['Active'] ?? 'Y' ) ) === 'Y' ? 'Y' : 'N';
    update_user_meta( $user_id, self::META_ACTIVE, $is_active );

    // Store on-hold status
    $is_on_hold = strtoupper( (string) ( $debtor['OnHold'] ?? 'Y' ) ) === 'Y' ? 'Y' : 'N';
    update_user_meta( $user_id, self::META_ON_HOLD, $is_on_hold );
    
    // Store account number
    $account_no = $debtor['AccNo'] ?? '';
    update_user_meta( $user_id, self::META_ACCNO, $account_no );

    // Store company name
    $company_name = trim( $debtor['DebName'] ?? '' );
    update_user_meta( $user_id, self::META_ACCOUNT_NAME, $company_name );
    update_user_meta( $user_id, 'billing_company', $company_name );

    // Billing address
    update_user_meta( $user_id, 'billing_address_1', trim( $debtor['Addr1'] ?? '' ) );
    update_user_meta( $user_id, 'billing_city', trim( $debtor['Addr2'] ?? '' ) );
    update_user_meta( $user_id, 'billing_postcode', trim( $debtor['PCode'] ?? '' ) );
    update_user_meta( $user_id, 'billing_country', 'ZA' );

    // Shipping address
    $shipping_name = trim( $debtor['DelName'] ?? '' );
    if ( empty( $shipping_name ) )
      $shipping_name = $company_name;

    update_user_meta( $user_id, 'shipping_company', $shipping_name );
    update_user_meta( $user_id, 'shipping_address_1', trim( $debtor['DelAddr1'] ?? '' ) );
    update_user_meta( $user_id, 'shipping_city', trim( $debtor['DelAddr2'] ?? '' ) );
    update_user_meta( $user_id, 'shipping_postcode', trim( $debtor['DelPCode'] ?? '' ) );
    update_user_meta( $user_id, 'shipping_country', 'ZA' );

    // Phone number
    $phone = trim( $debtor['TelNo'] ) ?? trim( $debtor['TelNo1'] ) ?? '';
    update_user_meta( $user_id, 'billing_phone', $phone );

    // Delivery instructions
    $instructions_lines = [];
    for ( $i = 1; $i <= 6; $i++ ) {
      $line = trim( $debtor[ "DelInstruc$i" ] ?? '' );
      if ( ! empty( $line ) )
        $instructions_lines[] = $line;
    }
    
    $final_instructions = implode( "\n", $instructions_lines );
    update_user_meta( $user_id, self::META_DELIVERY_INSTRUCTIONS, $final_instructions );

    // Update changed timestamp
    $change_timestamp = self::build_timestamp_from_fincon_values(
      $debtor['ChangeDate'] ?? '',
      $debtor['ChangeTime'] ?? ''
    );
    update_user_meta( $user_id, self::META_CHANGED_TIMESTAMP, $change_timestamp );

    // Update last sync timestamp (when plugin last synced this customer)
    update_user_meta( $user_id, self::META_LAST_SYNC_TIMESTAMP, time() );

    Logger::debug( sprintf( 'Updated customer meta for user %d with price list %d', $user_id, $validated_price_list ) );
  }

  /**
   * Apply the correct client type role (Retail / Dealer) with enhanced validation.
   *
   * @param int $user_id WordPress user ID
   * @param array $debtor Fincon debtor data
   * @since 1.0.0
   */
  private static function apply_client_type_role( int $user_id, array $debtor ): void {
    $raw_price_structure = (int) ( $debtor['PriceStruc'] ?? 1 );
    $validated_price_list = self::validate_price_structure( $raw_price_structure );
    $role = self::map_price_structure_to_role( $validated_price_list );
    
    $user = new \WP_User( $user_id );

    // Remove both custom roles before assigning
    $user->remove_role( self::ROLE_RETAIL );
    $user->remove_role( self::ROLE_DEALER );

    // Always ensure base customer role
    $user->add_role( 'customer' );
    $user->add_role( $role );
    
    // Log role assignment
    $user_info = $user->user_login . ' (' . $user->user_email . ')';
    Logger::info( sprintf(
      'Assigned role %s to user %d (%s) based on price list %d (raw: %d)',
      $role,
      $user_id,
      $user_info,
      $validated_price_list,
      $raw_price_structure
    ) );
  }

  /**
   * Build unix timestamp from FinCon ChangeDate + ChangeTime values.
   */
  private static function build_timestamp_from_fincon_values( string $date, string $time ): int {
    if ( empty( $date ) )
      return time();

    $year  = substr( $date, 0, 4 );
    $month = substr( $date, 4, 2 );
    $day   = substr( $date, 6, 2 );
    $time  = substr( $time, 0, 8 );

    return strtotime( sprintf( '%s-%s-%s %s', $year, $month, $day, $time ?: '00:00:00' ) );
  }

  /**
   * Determine client type role based on validated price structure.
   *
   * @param int $price_structure Validated price structure (1-6)
   * @return string Role constant (ROLE_RETAIL or ROLE_DEALER)
   * @since 1.0.0
   */
  public static function map_price_structure_to_role( int $price_structure ): string {
    // Price structure 1 = Retail, 2-6 = Dealer
    return $price_structure > self::PRICE_LIST_RETAIL ? self::ROLE_DEALER : self::ROLE_RETAIL;
  }

  /**
  * Helper to extract email based on priority (Email > Email1) and separator logic
  *
  * @param array $debtor
  * @return string
  */
 private static function extract_email_address( array $debtor ): string {
    $raw_email = trim( $debtor['Email'] ?? '' );
    
    if ( empty( $raw_email ) )
        $raw_email = trim( $debtor['Email1'] ?? '' );

    if ( empty( $raw_email ) )
        return '';
    

    $emails = explode( ';', $raw_email );
    return sanitize_email( trim( $emails[0] ) );
 }

 /**
  * Get customer's price list number with validation
  *
  * @param int $user_id WordPress user ID
  * @return int Valid price list number (1-6)
  * @since 1.0.0
  */
 public static function get_customer_price_list( int $user_id ): int {
   $price_structure = (int) get_user_meta( $user_id, self::META_PRICE_STRUCTURE, true );
   
   // Otherwise validate and store the price structure
   $price_list = self::validate_price_structure( $price_structure );
   update_user_meta( $user_id, self::META_PRICE_LIST_VALIDATED, 'yes' );
   
   Logger::debug( sprintf( 'Validated price list for user %d: %d (from PriceStruc: %d)', $user_id, $price_list, $price_structure ) );
   
   return $price_list;
 }

 /**
  * Check if customer is a dealer (PriceStruc > 1)
  *
  * @param int $user_id WordPress user ID
  * @return bool True if customer is a dealer
  * @since 1.0.0
  */
 public static function is_customer_dealer( int $user_id ): bool {
   $price_structure = (int) get_user_meta( $user_id, self::META_PRICE_STRUCTURE, true );
   return $price_structure > self::PRICE_LIST_RETAIL;
 }

 /**
  * Check if customer is retail (PriceStruc = 1)
  *
  * @param int $user_id WordPress user ID
  * @return bool True if customer is retail
  * @since 1.0.0
  */
 public static function is_customer_retail( int $user_id ): bool {
   $price_structure = (int) get_user_meta( $user_id, self::META_PRICE_STRUCTURE, true );
   return $price_structure === self::PRICE_LIST_RETAIL;
 }

 /**
  * Validate price structure value and return appropriate price list number
  *
  * @param int $price_structure Raw PriceStruc value from Fincon
  * @return int Valid price list number (1-6)
  * @since 1.0.0
  */
 public static function validate_price_structure( int $price_structure ): int {
   // Ensure price structure is within valid range
   if ( $price_structure < self::PRICE_LIST_MIN || $price_structure > self::PRICE_LIST_MAX ) {
     Logger::warning( sprintf( 'Invalid PriceStruc value %d, defaulting to retail price list', $price_structure ) );
     return self::PRICE_LIST_DEFAULT;
   }
   
   return $price_structure;
 }

 /**
  * Get appropriate price list for display/cart based on customer type
  *
  * @param int $user_id WordPress user ID
  * @return int Price list number to use (1-6)
  * @since 1.0.0
  */
 public static function get_display_price_list( int $user_id ): int {
   $price_list = self::get_customer_price_list( $user_id );
   
   // Retail customers always use price list 1
   if ( self::is_customer_retail( $user_id ) ) {
     return self::PRICE_LIST_RETAIL;
   }
   
   // Dealer customers use their specific price list (2-6)
   return $price_list;
 }

 /**
  * Get price list meta key for a specific price list number
  *
  * @param int $price_list_number Price list number (1-6)
  * @return string Meta key for the price list
  * @since 1.0.0
  */
 public static function get_price_list_meta_key( int $price_list_number ): string {
   if ( $price_list_number === 1 ) {
     return '_regular_price'; // WooCommerce regular price
   }
   
   return "_selling_price_{$price_list_number}";
 }

 /**
  * Get promotional price meta key for a specific price list number
  *
  * @param int $price_list_number Price list number (1-6)
  * @return string Promotional price meta key
  * @since 1.0.0
  */
 public static function get_promo_price_meta_key( int $price_list_number ): string {
   if ( $price_list_number === 1 ) {
     return '_sale_price'; // WooCommerce sale price
   }
   
   return "_promo_price_{$price_list_number}";
 }

 /**
  * Get promotional flag meta key for a specific price list number
  *
  * @param int $price_list_number Price list number (1-6)
  * @return string Promotional flag meta key
  * @since 1.0.0
  */
 public static function get_promo_flag_meta_key( int $price_list_number ): string {
   if ( $price_list_number === 1 ) {
     return '_fincon_promo_active'; // Custom flag for price list 1
   }
   
   return "_promo_{$price_list_number}";
 }

 /**
  * Check if price list number is valid (1-6)
  *
  * @param int $price_list_number Price list number to validate
  * @return bool True if valid
  * @since 1.0.0
  */
 public static function is_valid_price_list( int $price_list_number ): bool {
   return $price_list_number >= self::PRICE_LIST_MIN && $price_list_number <= self::PRICE_LIST_MAX;
 }

 /**
  * Get all valid price list numbers
  *
  * @return array Array of valid price list numbers (1-6)
  * @since 1.0.0
  */
 public static function get_valid_price_lists(): array {
   return range( self::PRICE_LIST_MIN, self::PRICE_LIST_MAX );
 }

 /**
  * Get dealer price list numbers (2-6)
  *
  * @return array Array of dealer price list numbers
  * @since 1.0.0
  */
 public static function get_dealer_price_lists(): array {
   return range( self::PRICE_LIST_DEALER_MIN, self::PRICE_LIST_DEALER_MAX );
 }

 /**
  * Log price list assignment for debugging
  *
  * @param int $user_id WordPress user ID
  * @param int $price_structure Raw PriceStruc value
  * @param int $price_list Validated price list
  * @param string $context Additional context for logging
  * @since 1.0.0
  */
 private static function log_price_list_assignment( int $user_id, int $price_structure, int $price_list, string $context = '' ): void {
   $user = get_user_by( 'id', $user_id );
   $username = $user ? $user->user_login : 'Unknown';
   
   $message = sprintf(
     'Price list assignment: User %d (%s) - PriceStruc: %d → Price List: %d',
     $user_id,
     $username,
     $price_structure,
     $price_list
   );
   
   if ( ! empty( $context ) ) {
     $message .= " - Context: {$context}";
   }
   
   Logger::info( $message );
 }


}

