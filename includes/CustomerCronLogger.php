<?php
/**
 * Customer Sync Logger
 * 
 * Handles file-based logging for customer cron sync operations.
 * Logs per-customer details including AccNo, WooCommerce user ID, and status.
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

class CustomerCronLogger {

  private static ?self $instance = null;

  private const LOG_DIR = DF_FINCON_PLUGIN_DIR . 'logs/customers/';

  private const LOG_FILE_PREFIX = 'cron_customer_';

  private const LOG_FILE_EXT = '.log';

  private $log_file_handle = null;

  private $current_log_file = null;

  private $enabled = false;

  private function __construct() {
    $this->enabled = $this->is_logging_enabled();
  }

  public static function create(): self {
    if ( self::$instance === null ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function is_logging_enabled(): bool {
    $options = CustomerSync::get_options();
    return ! empty( $options['customer_cron_log_enabled'] );
  }

  public function start_log(): bool {
    if ( ! $this->enabled ) {
      return false;
    }

    if ( ! $this->ensure_log_directory() ) {
      Logger::error( 'Failed to create customer cron log directory', [
        'log_dir' => self::LOG_DIR,
      ] );
      return false;
    }

    $date_string = current_time( 'Y-m-d' );
    $this->current_log_file = self::LOG_DIR . self::LOG_FILE_PREFIX . $date_string . self::LOG_FILE_EXT;

    $this->log_file_handle = fopen( $this->current_log_file, 'a' );
    if ( ! $this->log_file_handle ) {
      Logger::error( 'Failed to open customer cron log file for writing', [
        'log_file' => $this->current_log_file,
      ] );
      return false;
    }

    $timezone_string = wp_timezone_string();
    $start_time = current_time( 'mysql' );
    $header = sprintf(
      "=== Customer Cron Sync Started: %s (%s) ===\n\n",
      $start_time,
      $timezone_string
    );

    fwrite( $this->log_file_handle, $header );
    return true;
  }

  /**
   * Log a single customer sync result
   * 
   * @param string $acc_no  Customer AccNo
   * @param int    $user_id WooCommerce user ID (0 if not found/created)
   * @param string $status  Status: created, updated, skipped
   * @param string $message Optional additional message
   */
  public function log_customer( string $acc_no, int $user_id, string $status, string $message = '' ): bool {
    if ( ! $this->enabled || ! $this->log_file_handle ) {
      return false;
    }

    $timestamp = current_time( 'mysql' );
    $log_line = sprintf(
      "[%s] AccNo: %s, WC User ID: %d, Status: %s",
      $timestamp,
      $acc_no,
      $user_id,
      $status
    );

    if ( ! empty( $message ) ) {
      $log_line .= sprintf( ' (%s)', $message );
    }

    $log_line .= "\n";

    $result = fwrite( $this->log_file_handle, $log_line );
    return $result !== false;
  }

  public function complete_log( int $total_processed, int $created_count, int $updated_count, int $skipped_count, int $duration ): bool {
    if ( ! $this->enabled || ! $this->log_file_handle ) {
      return false;
    }

    $end_time = current_time( 'mysql' );
    $summary = sprintf(
      "\n=== Customer Cron Sync Completed: %s ===\n",
      $end_time
    );

    $summary .= sprintf(
      "Summary: %d customers processed (%d created, %d updated, %d skipped)\n",
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

    fclose( $this->log_file_handle );
    $this->log_file_handle = null;
    $this->current_log_file = null;

    return true;
  }

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

      if ( strpos( $file, self::LOG_FILE_PREFIX ) === 0 && strpos( $file, self::LOG_FILE_EXT ) !== false ) {
        $file_path = self::LOG_DIR . $file;
        $log_files[] = [
          'name'          => $file,
          'path'          => $file_path,
          'size'          => filesize( $file_path ),
          'modified_time' => filemtime( $file_path ),
          'modified_date' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $file_path ) ),
        ];
      }
    }

    usort( $log_files, function( $a, $b ) {
      return $b['modified_time'] <=> $a['modified_time'];
    } );

    return $log_files;
  }

  public function read_log_file( string $file_name ): string|false {
    $file_path    = self::LOG_DIR . basename( $file_name );
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

  public function delete_log_file( string $file_name ): bool {
    $file_path    = self::LOG_DIR . basename( $file_name );
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

  public function delete_all_log_files(): array {
    $log_files = $this->get_log_files();
    $results = [ 'deleted' => [], 'errors' => [] ];

    foreach ( $log_files as $file_info ) {
      if ( $this->delete_log_file( $file_info['name'] ) ) {
        $results['deleted'][] = $file_info['name'];
      } else {
        $results['errors'][] = $file_info['name'];
      }
    }

    return $results;
  }

  private function ensure_log_directory(): bool {
    if ( ! is_dir( self::LOG_DIR ) ) {
      if ( ! wp_mkdir_p( self::LOG_DIR ) ) {
        return false;
      }
    }

    $htaccess_file = DF_FINCON_PLUGIN_DIR . 'logs/.htaccess';
    if ( ! file_exists( $htaccess_file ) ) {
      file_put_contents( $htaccess_file, "# Deny access to all files in the logs directory\nOrder Deny,Allow\nDeny from all" );
    }

    return is_writable( self::LOG_DIR );
  }

  public function is_enabled(): bool {
    return $this->enabled;
  }

  public function get_current_log_file(): ?string {
    return $this->current_log_file;
  }

  public static function get_log_dir(): string {
    return self::LOG_DIR;
  }
}