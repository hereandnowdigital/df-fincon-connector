<?php
/**
 * Dynamic Stock Locations Management Class
 *
 * Manages admin-configurable stock locations for the DF Fincon Connector plugin.
 * Replaces hardcoded location system with dynamic, database-driven locations.
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

class LocationManager {

  /**
   * Singleton instance
   * @var self|null
   */
  private static ?self $instance = null;

  /**
   * Option name for storing locations data
   * @const string
   */
  public const OPTION_NAME = Plugin::SLUG . '_stock_locations';

  /**
   * Data structure version for migration tracking
   * @const string
   */
  public const DATA_VERSION = '1.0.0';

  /**
   * Default locations for initial setup
   * @const array
   */
  private const DEFAULT_LOCATIONS = [
    '00' => [
      'code' => '00',
      'name' => 'Johannesburg',
      'short_name' => 'JHB',
      'rep_code' => '079',
      'active' => true,
      'is_default' => true,
      'sort_order' => 1,
    ],
    '01' => [
      'code' => '01',
      'name' => 'Cape Town',
      'short_name' => 'CPT',
      'rep_code' => '',
      'active' => true,
      'is_default' => false,
      'sort_order' => 2,
    ],
    '03' => [
      'code' => '03',
      'name' => 'Durban',
      'short_name' => 'DBN',
      'rep_code' => '',
      'active' => true,
      'is_default' => false,
      'sort_order' => 3,
    ],
  ];

  /**
   * Constructor - private for singleton pattern
   */
  private function __construct() {
    // Ensure default locations exist on first run
    add_action( 'admin_init', [ $this, 'maybe_initialize_defaults' ] );
  }

  /**
   * Factory method for singleton pattern
   *
   * @return self
   * @since 1.0.0
   */
  public static function create(): self {
    if ( self::$instance === null ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Get all locations from database
   *
   * @return array Array of locations indexed by location code
   * @since 1.0.0
   */
  public function get_all_locations(): array {
    $data = get_option( self::OPTION_NAME, [] );
    
    // Ensure we have the expected structure
    if ( empty( $data ) || ! isset( $data['locations'] ) ) {
      return [];
    }
    
    return $data['locations'];
  }

  /**
   * Get only active locations
   *
   * @return array Array of active locations indexed by location code
   * @since 1.0.0
   */
  public function get_active_locations(): array {
    $all_locations = $this->get_all_locations();
    $active_locations = [];
    
    foreach ( $all_locations as $code => $location ) {
      if ( ! empty( $location['active'] ) ) {
        $active_locations[$code] = $location;
      }
    }
    
    return $active_locations;
  }

  /**
   * Get a specific location by code
   *
   * @param string $code Location code (e.g., '00', '01', '03')
   * @return array|null Location data or null if not found
   * @since 1.0.0
   */
  public function get_location( string $code ): ?array {
    $locations = $this->get_all_locations();
    return $locations[$code] ?? null;
  }

  /**
   * Get the default location (marked as is_default)
   *
   * @return array|null Default location data or null if none found
   * @since 1.0.0
   */
  public function get_default_location(): ?array {
    $locations = $this->get_all_locations();
    
    foreach ( $locations as $location ) 
      if ( ! empty( $location['is_default'] ) ) 
        return $location;
      
    
    // Fallback to first active location
    $active_locations = $this->get_active_locations();
    if ( ! empty( $active_locations ) ) 
      return reset( $active_locations );
    
    return null;
  }

  /**
   * Add a new location
   *
   * @param array $location_data Location data to add
   * @return bool|\WP_Error True on success, WP_Error on failure
   * @since 1.0.0
   */
  public function add_location( array $location_data ): bool|\WP_Error {
    // Validate location data
    $validation = $this->validate_location_data( $location_data );
    if ( is_wp_error( $validation ) ) {
      return $validation;
    }
    
    // Sanitize location code
    $code = $this->sanitize_location_code( $location_data['code'] );
    
    // Check for duplicate code
    if ( $this->location_exists( $code ) ) {
      return new \WP_Error(
        'duplicate_location_code',
        sprintf( __( 'Location code "%s" already exists.', 'df-fincon' ), $code )
      );
    }
    
    // Prepare location data
    $location = [
      'code' => $code,
      'name' => sanitize_text_field( $location_data['name'] ),
      'short_name' => sanitize_text_field( $location_data['short_name'] ),
      'rep_code' => $this->sanitize_rep_code( $location_data['rep_code'] ?? '' ),
      'active' => ! empty( $location_data['active'] ),
      'is_default' => ! empty( $location_data['is_default'] ),
      'sort_order' => absint( $location_data['sort_order'] ?? 99 ),
    ];
    
    // If this is being set as default, unset default flag from other locations
    if ( $location['is_default'] ) {
      $this->unset_default_flag_from_other_locations( $code );
    }
    
    // Get current data
    $data = $this->get_option_data();
    $data['locations'][$code] = $location;
    
    // Save to database
    return update_option( self::OPTION_NAME, $data );
  }

  /**
   * Update an existing location
   *
   * @param string $code Location code to update
   * @param array $location_data New location data
   * @return bool|\WP_Error True on success, WP_Error on failure
   * @since 1.0.0
   */
  public function update_location( string $code, array $location_data ): bool|\WP_Error {
    // Check if location exists
    if ( ! $this->location_exists( $code ) ) {
      return new \WP_Error(
        'location_not_found',
        sprintf( __( 'Location code "%s" not found.', 'df-fincon' ), $code )
      );
    }
    
    // Validate location data
    $validation = $this->validate_location_data( $location_data, $code );
    if ( is_wp_error( $validation ) ) {
      return $validation;
    }
    
    // Get current location
    $current_location = $this->get_location( $code );
    
    // Determine new code
    $new_code = $this->sanitize_location_code( $location_data['code'] );
    $code_changed = $new_code !== $code;
    
    // Prepare updated location data
    $updated_location = [
      'code' => $new_code, // Use new code
      'name' => sanitize_text_field( $location_data['name'] ),
      'short_name' => sanitize_text_field( $location_data['short_name'] ),
      'rep_code' => $this->sanitize_rep_code( $location_data['rep_code'] ?? $current_location['rep_code'] ),
      'active' => isset( $location_data['active'] ) ? (bool) $location_data['active'] : $current_location['active'],
      'is_default' => isset( $location_data['is_default'] ) ? (bool) $location_data['is_default'] : $current_location['is_default'],
      'sort_order' => isset( $location_data['sort_order'] ) ? absint( $location_data['sort_order'] ) : $current_location['sort_order'],
    ];
    
    // If this is being set as default, unset default flag from other locations
    if ( $updated_location['is_default'] && ! $current_location['is_default'] ) {
      $this->unset_default_flag_from_other_locations( $code_changed ? $new_code : $code );
    }
    
    // Get current data
    $data = $this->get_option_data();
    
    // If code changed, remove old entry and add new
    if ( $code_changed ) {
      unset( $data['locations'][$code] );
      $data['locations'][$new_code] = $updated_location;
      // Migrate product meta fields for this location
      $this->migrate_product_meta_for_location_code_change( $code, $new_code );
    } else {
      $data['locations'][$code] = $updated_location;
    }
    
    // Save to database
    return update_option( self::OPTION_NAME, $data );
  }

  /**
   * Delete a location
   *
   * @param string $code Location code to delete
   * @return bool|\WP_Error True on success, WP_Error on failure
   * @since 1.0.0
   */
  public function delete_location( string $code ): bool|\WP_Error {
    // Check if location exists
    if ( ! $this->location_exists( $code ) ) {
      return new \WP_Error(
        'location_not_found',
        sprintf( __( 'Location code "%s" not found.', 'df-fincon' ), $code )
      );
    }
    
    // Prevent deletion of default location
    $location = $this->get_location( $code );
    if ( ! empty( $location['is_default'] ) ) {
      return new \WP_Error(
        'cannot_delete_default',
        __( 'Cannot delete the default location. Set another location as default first.', 'df-fincon' )
      );
    }
    
    // Get current data
    $data = $this->get_option_data();
    unset( $data['locations'][$code] );
    
    // Save to database
    return update_option( self::OPTION_NAME, $data );
  }

  /**
   * Validate location data
   *
   * @param array $data Location data to validate
   * @param string|null $existing_code Existing location code (for updates)
   * @return \WP_Error|true WP_Error on validation failure, true on success
   * @since 1.0.0
   */
  public function validate_location_data( array $data, ?string $existing_code = null ): \WP_Error|bool {
    $errors = new \WP_Error();
    
    // Validate location code
    if ( empty( $data['code'] ) ) {
      $errors->add( 'missing_code', __( 'Location code is required.', 'df-fincon' ) );
    } else {
      $code = $this->sanitize_location_code( $data['code'] );
      
      // Check code format (numeric only, 2-6 characters)
      if ( ! preg_match( '/^[0-9]{2,6}$/', $code ) ) {
        $errors->add( 'invalid_code_format', __( 'Location code must be 2-6 numeric characters only.', 'df-fincon' ) );
      }
      
      // Check for duplicate (only if new location or code changed)
      if ( $existing_code !== $code && $this->location_exists( $code ) ) {
        $errors->add( 'duplicate_code', sprintf( __( 'Location code "%s" already exists.', 'df-fincon' ), $code ) );
      }
    }
    
    // Validate location name
    if ( empty( $data['name'] ) ) {
      $errors->add( 'missing_name', __( 'Location name is required.', 'df-fincon' ) );
    } else {
      $name = trim( $data['name'] );
      if ( strlen( $name ) > 100 ) {
        $errors->add( 'name_too_long', __( 'Location name must be 100 characters or less.', 'df-fincon' ) );
      }
    }
    
    // Validate short name
    if ( empty( $data['short_name'] ) ) {
      $errors->add( 'missing_short_name', __( 'Short name is required.', 'df-fincon' ) );
    } else {
      $short_name = trim( $data['short_name'] );
      if ( strlen( $short_name ) > 10 ) {
        $errors->add( 'short_name_too_long', __( 'Short name must be 10 characters or less.', 'df-fincon' ) );
      }
    }
    
    // Validate rep code (optional)
    if ( ! empty( $data['rep_code'] ) ) {
      $rep_code = $this->sanitize_rep_code( $data['rep_code'] );
      if ( ! preg_match( '/^[0-9]{1,10}$/', $rep_code ) ) {
        $errors->add( 'invalid_rep_code', __( 'Rep code must be 1-10 numeric characters only.', 'df-fincon' ) );
      }
    }
    
    // Return validation result
    if ( $errors->has_errors() ) {
      return $errors;
    }
    
    return true;
  }

  /**
   * Get comma-separated string of location codes for API calls
   *
   * @return string Comma-separated location codes (e.g., "00,01,03")
   * @since 1.0.0
   */
  public function get_location_codes_string(): string {
    $active_locations = $this->get_active_locations();
    $codes = array_keys( $active_locations );
    return implode( ',', $codes );
  }

  /**
   * Get stock meta mapping compatible with PRODUCT_META_STOCK_LOCATIONS
   *
   * @return array Array in format ['code' => ['_stock_code' => 'Short Name']]
   * @since 1.0.0
   */
  public function get_stock_meta_mapping(): array {
    $active_locations = $this->get_active_locations();
    $mapping = [];
    
    foreach ( $active_locations as $code => $location ) {
      $mapping[$code] = [
        '_stock_' . $code => $location['short_name'],
      ];
    }
    
    return $mapping;
  }

  /**
   * Get location meta keys for all locations
   *
   * @return array Array of meta keys for stock and location number fields
   * @since 1.0.0
   */
  public function get_location_meta_keys(): array {
    $all_locations = $this->get_all_locations();
    $meta_keys = [];
    
    foreach ( $all_locations as $code => $location ) {
      $meta_keys[] = '_stock_' . $code;
      $meta_keys[] = 'LocNo_' . $code;
    }
    
    return $meta_keys;
  }

  /**
   * Get location by rep code
   *
   * @param string $rep_code Rep code to search for
   * @return array|null Location data or null if not found
   * @since 1.0.0
   */
  public function get_location_by_rep_code( string $rep_code ): ?array {
    $locations = $this->get_all_locations();
    
    foreach ( $locations as $location ) {
      if ( $location['rep_code'] === $rep_code ) {
        return $location;
      }
    }
    
    return null;
  }

  /**
   * Migrate from hardcoded locations to dynamic system
   *
   * @return bool True on success, false on failure
   * @since 1.0.0
   */
  public function migrate_from_hardcoded(): bool {
    // Check if migration already completed
    if ( $this->is_migration_complete() ) {
      return true;
    }
    
    // Get current data
    $data = $this->get_option_data();
    
    // Add default locations if none exist
    if ( empty( $data['locations'] ) ) {
      $data['locations'] = self::DEFAULT_LOCATIONS;
      $data['migrated'] = true;
      $data['version'] = self::DATA_VERSION;
      
      $result = update_option( self::OPTION_NAME, $data );
      
      if ( $result ) {
        Logger::info( 'Stock locations migrated from hardcoded to dynamic system.' );
      } else {
        Logger::error( 'Stock locations migration failed.' );
      }
      
      return $result;
    }
    
    // Migration already has data
    $data['migrated'] = true;
    $data['version'] = self::DATA_VERSION;
    return update_option( self::OPTION_NAME, $data );
  }

  /**
   * Check if migration from hardcoded locations is complete
   *
   * @return bool True if migration is complete
   * @since 1.0.0
   */
  public function is_migration_complete(): bool {
    $data = get_option( self::OPTION_NAME, [] );
    return ! empty( $data['migrated'] );
  }

  /**
   * Check if a location exists
   *
   * @param string $code Location code to check
   * @return bool True if location exists
   * @since 1.0.0
   */
  public function location_exists( string $code ): bool {
    $locations = $this->get_all_locations();
    return isset( $locations[$code] );
  }

  /**
   * Get total number of locations
   *
   * @return int Number of locations
   * @since 1.0.0
   */
  public function get_location_count(): int {
    $locations = $this->get_all_locations();
    return count( $locations );
  }

  /**
   * Get number of active locations
   *
   * @return int Number of active locations
   * @since 1.0.0
   */
  public function get_active_location_count(): int {
    $active_locations = $this->get_active_locations();
    return count( $active_locations );
  }

  /**
   * Sanitize location code
   *
   * @param string $code Raw location code
   * @return string Sanitized location code
   * @since 1.0.0
   */
  public function sanitize_location_code( string $code ): string {
    return preg_replace( '/[^0-9]/', '', $code );
  }

  /**
   * Sanitize rep code
   *
   * @param string $rep_code Raw rep code
   * @return string Sanitized rep code
   * @since 1.0.0
   */
  public function sanitize_rep_code( string $rep_code ): string {
    return preg_replace( '/[^0-9]/', '', $rep_code );
  }

  /**
   * Unset default flag from all locations except the specified one
   *
   * @param string $exclude_code Location code to keep as default
   * @return void
   * @since 1.0.0
   */
  private function unset_default_flag_from_other_locations( string $exclude_code ): void {
    $data = $this->get_option_data();
    
    foreach ( $data['locations'] as $code => &$location ) {
      if ( $code !== $exclude_code ) {
        $location['is_default'] = false;
      }
    }
    
    update_option( self::OPTION_NAME, $data );
  }

  /**
   * Get option data with proper structure
   *
   * @return array Complete option data
   * @since 1.0.0
   */
  private function get_option_data(): array {
    $data = get_option( self::OPTION_NAME, [] );
    
    // Ensure proper structure
    if ( ! isset( $data['locations'] ) ) {
      $data['locations'] = [];
    }
    
    if ( ! isset( $data['version'] ) ) {
      $data['version'] = self::DATA_VERSION;
    }
    
    if ( ! isset( $data['migrated'] ) ) {
      $data['migrated'] = false;
    }
    
    return $data;
  }

  /**
   * Initialize default locations if none exist
   *
   * @return void
   * @since 1.0.0
   */
  public function maybe_initialize_defaults(): void {
    $locations = $this->get_all_locations();
    
    if ( empty( $locations ) ) {
      $this->migrate_from_hardcoded();
    }
  }

  /**
   * Toggle location active status
   *
   * @param string $code Location code
   * @param bool $active New active status
   * @return bool|\WP_Error True on success, WP_Error on failure
   * @since 1.0.0
   */
  public function toggle_location_active( string $code, bool $active ): bool|\WP_Error {
    if ( ! $this->location_exists( $code ) ) {
      return new \WP_Error(
        'location_not_found',
        sprintf( __( 'Location code "%s" not found.', 'df-fincon' ), $code )
      );
    }
    
    $location = $this->get_location( $code );
    $location['active'] = $active;
    
    return $this->update_location( $code, $location );
  }

  /**
   * Set default location
   *
   * @param string $code Location code to set as default
   * @return bool|\WP_Error True on success, WP_Error on failure
   * @since 1.0.0
   */
  public function set_default_location( string $code ): bool|\WP_Error {
    if ( ! $this->location_exists( $code ) ) {
      return new \WP_Error(
        'location_not_found',
        sprintf( __( 'Location code "%s" not found.', 'df-fincon' ), $code )
      );
    }
    
    $location = $this->get_location( $code );
    $location['is_default'] = true;
    
    return $this->update_location( $code, $location );
  }

  /**
   * Get locations sorted by sort_order
   *
   * @return array Locations sorted by sort_order
   * @since 1.0.0
   */
  public function get_sorted_locations(): array {
    $locations = $this->get_all_locations();
    
    uasort( $locations, function( $a, $b ) {
      return ( $a['sort_order'] ?? 99 ) <=> ( $b['sort_order'] ?? 99 );
    } );
    
    return $locations;
  }

  /**
   * Get active locations sorted by sort_order
   *
   * @return array Active locations sorted by sort_order
   * @since 1.0.0
   */
  public function get_sorted_active_locations(): array {
    $active_locations = $this->get_active_locations();
    
    uasort( $active_locations, function( $a, $b ) {
      return ( $a['sort_order'] ?? 99 ) <=> ( $b['sort_order'] ?? 99 );
    } );
    
    return $active_locations;
  }

  /**
   * Migrate product meta fields when a location code changes
   *
   * Updates all product meta fields that use the old location code as suffix
   * to use the new location code instead. This includes:
   * - _stock_{old_code} → _stock_{new_code}
   * - LocNo_{old_code} → LocNo_{new_code}
   *
   * @param string $old_code Original location code
   * @param string $new_code New location code
   * @return void
   * @since 1.0.0
   */
  private function migrate_product_meta_for_location_code_change( string $old_code, string $new_code ): void {
    global $wpdb;
    
    if ( $old_code === $new_code ) {
      return;
    }
    
    // Define meta keys to migrate
    $old_meta_keys = [
      '_stock_' . $old_code,
      'LocNo_' . $old_code,
    ];
    
    $new_meta_keys = [
      '_stock_' . $new_code,
      'LocNo_' . $new_code,
    ];
    
    // Get all product IDs that have the old meta keys
    $product_ids = $wpdb->get_col( $wpdb->prepare(
      "SELECT DISTINCT post_id
       FROM {$wpdb->postmeta}
       WHERE meta_key IN (%s, %s)
       AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product')",
      $old_meta_keys[0],
      $old_meta_keys[1]
    ) );
    
    if ( empty( $product_ids ) ) {
      return;
    }
    
    // Process each product
    foreach ( $product_ids as $product_id ) {
      $product = wc_get_product( $product_id );
      if ( ! $product ) {
        continue;
      }
      
      // Migrate each meta key
      foreach ( $old_meta_keys as $index => $old_key ) {
        $new_key = $new_meta_keys[$index];
        $value = $product->get_meta( $old_key, true );
        
        if ( $value !== '' ) {
          // Update to new key
          $product->update_meta_data( $new_key, $value );
          // Delete old key
          $product->delete_meta_data( $old_key );
        }
      }
      
      // Save changes
      $product->save();
    }
    
    Logger::info( sprintf(
      'Migrated product meta for location code change: %s → %s (%d products affected)',
      $old_code,
      $new_code,
      count( $product_ids )
    ) );
  }

}