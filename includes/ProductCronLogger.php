<?php
/**
 * Product Cron Logger
 * 
 * Handles file-based logging for product cron sync operations.
 * Logs per-product details including SKU, WooCommerce ID, and status.
 *
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * @subpackage Includes
 * Text Domain: df-fincon
 */

namespace DF_FINCON;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class ProductCronLogger {

  /**
   * Singleton instance
   * 
   * @var self|null
   */
  private static ?self $instance = null;

  /**
   * Log directory path
   * 
   * @var string
   */
  private const LOG_DIR = DF_FINCON_PLUGIN_DIR . 'logs/products/';

  /**
   * Log file prefix
   * 
   * @var string
   */
  private const LOG_FILE_PREFIX = 'cron_product_';

  /**
   * Log file extension
   * 
   * @var string
   */
  private const LOG_FILE_EXT = '.log';

  /**
   * Current log file handle
   * 
   * @var resource|null
   */
  private $log_file_handle = null;

  /**
   * Current log file path
   * 
   * @var string|null
   */
  private $current_log_file = null;

  /**
   * Whether logging is enabled
   * 
   * @var bool
   */
  private $enabled = false;

  /**
   * Constructor
   */
  private function __construct() {
    // Check if logging is enabled
    $this->enabled = $this->is_logging_enabled();
  }

  /**
   * Create an instance.
   *
   * @return self
   */
  public static function create(): self {
    if ( self::$instance === null ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Check if product cron logging is enabled
   * 
   * @return bool True if enabled, false otherwise
   */
  private function is_logging_enabled(): bool {
    $options = ProductSync::get_options();
    return ! empty( $options['product_cron_log_enabled'] );
  }

  /**
   * Start a new cron log session
   * 
   * @return bool True on success, false on failure
   */
  public function start_log(): bool {
    if ( ! $this->enabled ) {
      return false;
    }

    // Ensure log directory exists
    if ( ! $this->ensure_log_directory() ) {
      Logger::error( 'Failed to create product cron log directory', [
        'log_dir' => self::LOG_DIR,
      ] );
      return false;
    }

    // Generate log file name based on current date
    $date_string = current_time( 'Y-m-d' );
    $this->current_log_file = self::LOG_DIR . self::LOG_FILE_PREFIX . $date_string . self::LOG_FILE_EXT;

    // Open log file for appending
    $this->log_file_handle = fopen( $this->current_log_file, 'a' );
    if ( ! $this->log_file_handle ) {
      Logger::error( 'Failed to open product cron log file for writing', [
        'log_file' => $this->current_log_file,
      ] );
      return false;
    }

    // Write start header
    $timezone_string = wp_timezone_string();
    $start_time = current_time( 'mysql' );
    $header = sprintf(
      "=== Product Cron Sync Started: %s (%s) ===\n\n",
      $start_time,
      $timezone_string
    );

    fwrite( $this->log_file_handle, $header );
    return true;
  }

  /**
   * Log a product import result
   * 
   * @param string $sku Product SKU
   * @param int $wc_id WooCommerce product ID (0 if not created/updated)
   * @param string $status Product status (created, updated, skipped)
   * @param string $message Optional additional message
   * @return bool True on success, false on failure
   */
  public function log_product( string $sku, int $wc_id, string $status, string $message = '' ): bool {
    if ( ! $this->enabled || ! $this->log_file_handle ) {
      return false;
    }

    $timestamp = current_time( 'mysql' );
    $log_line = sprintf(
      "[%s] SKU: %s, WC ID: %d, Status: %s",
      $timestamp,
      $sku,
      $wc_id,
      $status
    );

    if ( ! empty( $message ) ) {
      $log_line .= sprintf( ' (%s)', $message );
    }

    $log_line .= "\n";

    $result = fwrite( $this->log_file_handle, $log_line );
    return $result !== false;
  }

  /**
   * Complete the cron log session
   * 
   * @param int $total_processed Total products processed
   * @param int $created_count Number of products created
   * @param int $updated_count Number of products updated
   * @param int $skipped_count Number of products skipped
   * @param int $duration Duration in seconds
   * @return bool True on success, false on failure
   */
  public function complete_log( int $total_processed, int $created_count, int $updated_count, int $skipped_count, int $duration ): bool {
    if ( ! $this->enabled || ! $this->log_file_handle ) {
      return false;
    }

    // Write summary
    $end_time = current_time( 'mysql' );
    $summary = sprintf(
      "\n=== Product Cron Sync Completed: %s ===\n",
      $end_time
    );

    $summary .= sprintf(
      "Summary: %d products processed (%d created, %d updated, %d skipped)\n",
      $total_processed,
      $created_count,
      $updated_count,
      $skipped_count
    );

    $summary .= sprintf(
      "Duration: %d seconds\n\n",
      $duration
    );

    $summary .= str_repeat( '=', 60 ) . "\n\n";

    fwrite( $this->log_file_handle, $summary );
    
    // Close file handle
    fclose( $this->log_file_handle );
    $this->log_file_handle = null;
    $this->current_log_file = null;

    return true;
  }

  /**
   * Get list of all log files
   * 
   * @return array Array of log file information: name, path, size, modified_time
   */
  public function get_log_files(): array {
    $log_files = [];

    if ( ! is_dir( self::LOG_DIR ) ) {
      return $log_files;
    }

    $files = scandir( self::LOG_DIR );
    if ( ! $files ) {
      return $log_files;
    }

    foreach ( $files as $file ) {
      if ( $file === '.' || $file === '..' ) {
        continue;
      }

      // Check if file matches our pattern
      if ( strpos( $file, self::LOG_FILE_PREFIX ) === 0 && strpos( $file, self::LOG_FILE_EXT ) !== false ) {
        $file_path = self::LOG_DIR . $file;
        $file_info = [
          'name' => $file,
          'path' => $file_path,
          'size' => filesize( $file_path ),
          'modified_time' => filemtime( $file_path ),
          'modified_date' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $file_path ) ),
        ];
        $log_files[] = $file_info;
      }
    }

    // Sort by modified time (newest first)
    usort( $log_files, function( $a, $b ) {
      return $b['modified_time'] <=> $a['modified_time'];
    } );

    return $log_files;
  }

  /**
   * Read a log file content
   * 
   * @param string $file_name Log file name (without path)
   * @return string|false File content on success, false on failure
   */
  public function read_log_file( string $file_name ): string|false {
    // Security: ensure file is within log directory
    $file_path = self::LOG_DIR . basename( $file_name );
    $real_log_dir = realpath( self::LOG_DIR );
    $real_file_path = realpath( $file_path );

    if ( ! $real_file_path || strpos( $real_file_path, $real_log_dir ) !== 0 ) {
      return false;
    }

    if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
      return false;
    }

    return file_get_contents( $file_path );
  }

  /**
   * Delete a log file
   * 
   * @param string $file_name Log file name (without path)
   * @return bool True on success, false on failure
   */
  public function delete_log_file( string $file_name ): bool {
    // Security: ensure file is within log directory
    $file_path = self::LOG_DIR . basename( $file_name );
    $real_log_dir = realpath( self::LOG_DIR );
    $real_file_path = realpath( $file_path );

    if ( ! $real_file_path || strpos( $real_file_path, $real_log_dir ) !== 0 ) {
      return false;
    }

    if ( ! file_exists( $file_path ) ) {
      return false;
    }

    return unlink( $file_path );
  }

  /**
   * Delete all log files
   * 
   * @return array Array of deleted files and errors
   */
  public function delete_all_log_files(): array {
    $log_files = $this->get_log_files();
    $results = [
      'deleted' => [],
      'errors' => [],
    ];

    foreach ( $log_files as $file_info ) {
      if ( $this->delete_log_file( $file_info['name'] ) ) {
        $results['deleted'][] = $file_info['name'];
      } else {
        $results['errors'][] = $file_info['name'];
      }
    }

    return $results;
  }

  /**
   * Ensure log directory exists and is writable
   * 
   * @return bool True on success, false on failure
   */
  private function ensure_log_directory(): bool {
    if ( ! is_dir( self::LOG_DIR ) ) {
      if ( ! wp_mkdir_p( self::LOG_DIR ) ) {
        return false;
      }
    }

    // Create .htaccess if it doesn't exist
    $htaccess_file = DF_FINCON_PLUGIN_DIR . 'logs/.htaccess';
    if ( ! file_exists( $htaccess_file ) ) {
      $htaccess_content = "# Deny access to all files in the logs directory\nOrder Deny,Allow\nDeny from all";
      file_put_contents( $htaccess_file, $htaccess_content );
    }

    return is_writable( self::LOG_DIR );
  }

  /**
   * Check if logging is currently enabled
   * 
   * @return bool True if enabled, false otherwise
   */
  public function is_enabled(): bool {
    return $this->enabled;
  }

  /**
   * Get the current log file path
   *
   * @return string|null Current log file path or null if not logging
   */
  public function get_current_log_file(): ?string {
    return $this->current_log_file;
  }

  /**
   * Get the log directory path
   *
   * @return string Log directory path
   */
  public static function get_log_dir(): string {
    return self::LOG_DIR;
  }
}