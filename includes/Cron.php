<?php
/**
 * Cron Functionality
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

class Cron {

  const HOOK = 'df_fincon_product_sync';
  const CUSTOMER_HOOK = 'df_fincon_customer_sync';
  const INVOICE_CHECK_HOOK = 'df_fincon_check_invoices';
  const LOG_OPTION_NAME = Plugin::OPTIONS_NAME . '_cron_log';
  const MAX_LOG_ENTRIES = 50;

  private static ?self $instance = null;
  private function __construct( ) {
    self::register_cron_hook(); 
    self::init();

  }  
 public static function create(  ): self {
    if ( self::$instance === null ) 
      self::$instance = new self( );
    return self::$instance;
  }

  private static function init() {
    self::register_actions();
    self::register_filters();
  }

  private static function register_actions() {
    add_action('init', [__CLASS__, 'init_cron_schedule']);
  }



  /**
   * Initialize cron schedules on plugin load
   * This ensures cron jobs are scheduled even if settings were never saved after enabling
   */
  public static function init_cron_schedule(): void {
    
    // Product sync - only schedule if not already scheduled
    if ( ! wp_next_scheduled( self::HOOK ) ) {
      $options = ProductSync::get_options();
      if ( ! empty( $options['sync_schedule_enabled'] ) ) {
        self::add_product_cron_schedule();
      }
    }

    // Customer sync - only schedule if not already scheduled
    if ( ! wp_next_scheduled( self::CUSTOMER_HOOK ) ) {
      $options = CustomerSync::get_options();
      if ( ! empty( $options['customer_sync_schedule_enabled'] ) ) {
        self::add_customer_cron_schedule();
      }
    }

    // Invoice check - only schedule if not already scheduled
    if ( ! wp_next_scheduled( self::INVOICE_CHECK_HOOK ) ) {
      self::schedule_invoice_check();
    }
  }

  private static function register_filters() {
    add_filter( 'cron_schedules', [ __CLASS__, 'add_custom_cron_schedules' ] );
  }


  public static function register_cron_hook() {
    add_action( self::HOOK, [ __CLASS__, 'product_scheduled_sync' ] );
    add_action( self::CUSTOMER_HOOK, [ __CLASS__, 'customer_scheduled_sync' ] );
    add_action( self::INVOICE_CHECK_HOOK, [ __CLASS__, 'invoice_check_scheduled' ] );
  }


  /**
   * Add custom cron schedules
   * 
   * @param array $schedules Existing cron schedules
   * @return array Modified schedules
   */
  public static function add_custom_cron_schedules( array $schedules ): array {
    $schedules['every_5_minutes'] = [
      'interval' => 5 * MINUTE_IN_SECONDS,
      'display'  => __( 'Every 5 Minutes', 'df-fincon' ),
    ];
    
    // Add invoice check schedule
    $invoice_checker_options = InvoiceChecker::get_options();
    $check_interval = (int) ( $invoice_checker_options['check_interval'] ?? 900 ); // Default 15 minutes
    
    $schedules['df_fincon_invoice_check_interval'] = [
      'interval' => $check_interval,
      'display' => sprintf( __( 'Every %d seconds (Fincon Invoice Check)', 'df-fincon' ), $check_interval ),
    ];
    
    return $schedules;
  }

    /**
     * Initialize cron scheduling based on product settings
     */
    public static function add_product_cron_schedule(): void {
      // Clear existing schedule before creating new one
      self::clear_product_cron_schedule();
      $options = ProductSync::get_options();
      
      if ( empty( $options['sync_schedule_enabled'] ) ) {
        Logger::debug( 'Product cron schedule disabled, skipping', [
          'options' => $options,
        ] );
        return;
      }

      $frequency = $options['sync_schedule_frequency'] ?? 'daily';
      $time = $options['sync_schedule_time'] ?? '23:00';
      $day = isset( $options['sync_schedule_day'] ) ? (int) $options['sync_schedule_day'] : 1;

      $first_run = self::calculate_first_run( $frequency, $time, $day );

      $schedule = self::get_wp_schedule( $frequency );

      if ( $first_run && $schedule ) {
        wp_schedule_event( $first_run, $schedule, self::HOOK );
        Logger::info( 'Product cron schedule added', [
          'frequency' => $frequency,
          'time' => $time,
          'day' => $day,
          'first_run' => date( 'Y-m-d H:i:s', $first_run ),
          'schedule' => $schedule,
          'hook' => self::HOOK,
        ] );
      } else {
        Logger::error( 'Failed to calculate product cron schedule', [
          'frequency' => $frequency,
          'time' => $time,
          'day' => $day,
          'first_run' => $first_run,
          'schedule' => $schedule,
        ] );
      }
    }

    /**
     * Initialize cron scheduling based on customer settings
     */
    public static function add_customer_cron_schedule(): void {
      Logger::debug( 'add_customer_cron_schedule() called', [
        'timestamp' => time(),
        'caller' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? 'unknown',
      ] );
      
      // Clear existing schedule before creating new one
      self::clear_customer_cron_schedule();
      $options = CustomerSync::get_options();
      
      Logger::debug( 'Customer cron settings check', [
        'customer_sync_schedule_enabled' => ! empty( $options['customer_sync_schedule_enabled'] ),
        'options' => $options,
      ] );
      
      if ( empty( $options['customer_sync_schedule_enabled'] ) ) {
        Logger::debug( 'Customer cron schedule disabled, skipping', [
          'options' => $options,
        ] );
        return;
      }

      $frequency = $options['customer_sync_schedule_frequency'] ?? 'daily';
      $time = $options['customer_sync_schedule_time'] ?? '23:00';
      $day = isset( $options['customer_sync_schedule_day'] ) ? (int) $options['customer_sync_schedule_day'] : 1;

      Logger::debug( 'Calculating customer cron schedule', [
        'frequency' => $frequency,
        'time' => $time,
        'day' => $day,
      ] );

      $first_run = self::calculate_first_run( $frequency, $time, $day );

      $schedule = self::get_wp_schedule( $frequency );

      if ( $first_run && $schedule ) {
        wp_schedule_event( $first_run, $schedule, self::CUSTOMER_HOOK );
        Logger::info( 'Customer cron schedule added', [
          'frequency' => $frequency,
          'time' => $time,
          'day' => $day,
          'first_run' => date( 'Y-m-d H:i:s', $first_run ),
          'schedule' => $schedule,
          'hook' => self::CUSTOMER_HOOK,
          'next_scheduled' => wp_next_scheduled( self::CUSTOMER_HOOK ),
        ] );
      } else {
        Logger::error( 'Failed to calculate customer cron schedule', [
          'frequency' => $frequency,
          'time' => $time,
          'day' => $day,
          'first_run' => $first_run,
          'schedule' => $schedule,
        ] );
      }
    }

    /**
     * Initialize all cron schedules (product, customer, invoice check)
     */
    public static function add_cron_schedule(): void {
      Logger::debug( 'add_cron_schedule() called - scheduling all cron jobs', [
        'timestamp' => time(),
        'caller' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? 'unknown',
      ] );
      
      self::add_product_cron_schedule();
      self::add_customer_cron_schedule();
      self::schedule_invoice_check();
      
      // Log final state
      Logger::debug( 'add_cron_schedule() completed - cron state', [
        'product_cron_scheduled' => wp_next_scheduled( self::HOOK ),
        'customer_cron_scheduled' => wp_next_scheduled( self::CUSTOMER_HOOK ),
        'invoice_cron_scheduled' => wp_next_scheduled( self::INVOICE_CHECK_HOOK ),
      ] );
    }
  
  /**
   * Removes scheduled cron events for product sync
   *
   * @return void
   */
  public static function clear_product_cron_schedule(): void {
    wp_clear_scheduled_hook( self::HOOK );
  }

  /**
   * Removes scheduled cron events for customer sync
   *
   * @return void
   */
  public static function clear_customer_cron_schedule(): void {
    wp_clear_scheduled_hook( self::CUSTOMER_HOOK );
  }

  /**
   * Removes all scheduled cron events (product, customer, invoice check)
   *
   * @return void
   */
  public static function clear_cron_schedule(): void {
    self::clear_product_cron_schedule();
    self::clear_customer_cron_schedule();
    wp_clear_scheduled_hook( self::INVOICE_CHECK_HOOK );
  }

  /**
   * Schedule invoice check cron job
   *
   * @return void
   * @since 1.1.0
   */
  public static function schedule_invoice_check(): void {
    $invoice_checker_options = InvoiceChecker::get_options();
    
    if ( empty( $invoice_checker_options['enabled'] ) ) {
      // Unschedule if disabled
      $timestamp = wp_next_scheduled( self::INVOICE_CHECK_HOOK );
      if ( $timestamp ) {
        wp_unschedule_event( $timestamp, self::INVOICE_CHECK_HOOK );
      }
      return;
    }
    
    $check_interval = (int) ( $invoice_checker_options['check_interval'] ?? 900 ); // Default 15 minutes
    
    if ( ! wp_next_scheduled( self::INVOICE_CHECK_HOOK ) ) {
      wp_schedule_event( time(), 'df_fincon_invoice_check_interval', self::INVOICE_CHECK_HOOK );
      
      Logger::info( 'Invoice check cron job scheduled', [
        'interval' => $check_interval,
        'hook' => self::INVOICE_CHECK_HOOK,
      ] );
    }
  }
  

  /**
   * Calculate the first run timestamp based on frequency, time, and day
   * 
   * @param string $frequency hourly, daily, or weekly
   * @param string $time Time in HH:MM format
   * @param int $day Day of week (0=Sunday, 6=Saturday) for weekly
   * @return int|false Timestamp or false on error
   */
  private static function calculate_first_run( string $frequency, string $time, int $day ): int|false {
    $wp_timezone = wp_timezone();
    $now = new \DateTime( 'now', $wp_timezone );
    
    list( $hour, $minute ) = explode( ':', $time );
    $hour = (int) $hour;
    $minute = (int) $minute;

    if ( $frequency === 'every_5_minutes' ) :
      // For 5 minutes, run at the next 5-minute mark (0, 5, 10, 15, etc.)
      $current_minute = (int) $now->format( 'i' );
      $next_minute_mark = (int) ( ceil( ( $current_minute + 1 ) / 5 ) * 5 );
      
      if ( $next_minute_mark >= 60 ) :
        // Roll over to next hour, minute 0
        $next_run = clone $now;
        $next_run->modify( '+1 hour' );
        $next_run->setTime( (int) $next_run->format( 'H' ), 0, 0 );
      else :
        $next_run = clone $now;
        $next_run->setTime( (int) $now->format( 'H' ), $next_minute_mark, 0 );
      endif;
      
      // If the calculated time is in the past (edge case), add 5 minutes
      if ( $next_run <= $now ) :
        $next_run->modify( '+5 minutes' );
      endif;
      
      return $next_run->getTimestamp();
    elseif ( $frequency === 'hourly' ) :
      // For hourly, ignore time picker - run at next hour
      $next_run = clone $now;
      $next_run->modify( '+1 hour' );
      $next_run->setTime( (int) $next_run->format( 'H' ), 0, 0 );
      return $next_run->getTimestamp();
    elseif ( $frequency === 'daily' ) :
      // For daily, use specified time
      $target = clone $now;
      $target->setTime( $hour, $minute, 0 );
      if ( $target <= $now ) :
        $target->modify( '+1 day' );
      endif;
      return $target->getTimestamp();
    elseif ( $frequency === 'weekly' ) :
      // For weekly, find next occurrence of day at specified time
      $current_day = (int) $now->format( 'w' );
      $days_until = ( $day - $current_day + 7 ) % 7;
      
      $target = clone $now;
      $target->setTime( $hour, $minute, 0 );
      
      if ( $days_until === 0 && $target > $now ) :
        // Today, and time hasn't passed
        return $target->getTimestamp();
      else :
        // Add days (or 7 if today but time passed)
        $days_to_add = $days_until === 0 ? 7 : $days_until;
        $target->modify( "+{$days_to_add} days" );
        return $target->getTimestamp();
      endif;
    endif;

    return false;
  }
    /**
     * Get WordPress cron schedule name
     * 
     * @param string $frequency hourly, daily, or weekly
     * @return string|false Schedule name or false
     */
    private static function get_wp_schedule( string $frequency ): string|false {
      return match( $frequency ) {
        'every_5_minutes' => 'every_5_minutes',
        'hourly' => 'hourly',
        'daily' => 'daily',
        'weekly' => 'weekly',
        default => false,
      };
    }

    /**
     * Run the scheduled product sync
     *
     */
    public static function product_scheduled_sync(): void {
      
      $start_time = current_time( 'mysql' );
      $start_timestamp = current_time( 'timestamp' );
      self::log_cron_run( 'started', $start_time, 0, '', 'product' );
      
      // Start product cron log if enabled
      $cron_logger = ProductCronLogger::create();
      $cron_log_started = $cron_logger->start_log();
      
      $is_complete = false;
      $first_batch = true;
      $total_created = 0;
      $total_updated = 0;
      $total_skipped = 0;
      $total_imported = 0;
      $total_processed = 0;
      $has_error = false;
      $error_message = '';
      
      while ( ! $is_complete && ! $has_error ) {
        // For first batch, don't resume. For subsequent batches, resume from progress
        $result = FinconService::import_products( 0, ! $first_batch );
        $first_batch = false;
        
        if ( is_wp_error( $result ) ) :
          $error_message = $result->get_error_message();
          $has_error = true;
          break;
        endif;
        
        // Accumulate totals
        $total_created += $result['created_count'] ?? 0;
        $total_updated += $result['updated_count'] ?? 0;
        $total_skipped += $result['skipped_count'] ?? 0;
        $total_imported += $result['imported_count'] ?? 0;
        
        $batch_state = $result['batch_state'] ?? [];
        $total_processed = $batch_state['total_processed'] ?? 0;
        $is_complete = $result['batch_complete'] ?? false;
      }
      
      $end_time = current_time( 'mysql' );
      $end_timestamp = current_time( 'timestamp' );
      $duration = $end_timestamp - $start_timestamp;

      if ( $has_error ) :
        self::log_cron_run( 'failed', $end_time, $duration, $error_message, 'product' );
        Logger::error( 'Scheduled product sync failed: ' . $error_message, $result );
        
        // Complete product cron log with error
        if ( $cron_log_started ) :
          $cron_logger->complete_log( 0, 0, 0, 0, $duration );
        endif;
      else :
        if ( $is_complete ) :
          $summary = sprintf(
            'Batch import COMPLETED. Total: %d (Imported: %d, Created: %d, Updated: %d, Skipped: %d)',
            $total_processed,
            $total_imported,
            $total_created,
            $total_updated,
            $total_skipped
          );
        else :
          // This shouldn't happen with our loop, but keep as fallback
          $summary = sprintf(
            'Batch: %d (Imported: %d, Created: %d, Updated: %d, Skipped: %d). Total processed so far: %d. More batches remaining.',
            $result['api_count'] ?? 0,
            $total_imported,
            $total_created,
            $total_updated,
            $total_skipped,
            $total_processed
          );
        endif;

        self::log_cron_run( 'completed', $end_time, $duration, $summary, 'product' );
        
        // Complete product cron log with summary
        if ( $cron_log_started ) :
          $cron_logger->complete_log(
            $total_processed,
            $total_created,
            $total_updated,
            $total_skipped,
            $duration
          );
        endif;
      endif;
    }

    /**
     * Run the scheduled customer sync
     *
     * @return void
     */
    public static function customer_scheduled_sync(): void {
      Logger::debug( 'Customer scheduled sync STARTING', [
        'hook' => self::CUSTOMER_HOOK,
        'timestamp' => time(),
        'next_scheduled' => wp_next_scheduled( self::CUSTOMER_HOOK ),
        'caller' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? 'unknown',
      ] );
      
      $start_time = current_time( 'mysql' );
      $start_timestamp = current_time( 'timestamp' );
      self::log_cron_run( 'started', $start_time, 0, '', 'customer' );
      
      $customer_options = CustomerSync::get_options();
      $result = CustomerService::import_batch(
        ! empty( $customer_options['customer_import_only_changed'] ),
        ! empty( $customer_options['customer_weblist_only'] )
      );
      
      $end_time = current_time( 'mysql' );
      $end_timestamp = current_time( 'timestamp' );
      $duration = $end_timestamp - $start_timestamp;
      
      if ( is_wp_error( $result ) ) :
        $error_message = $result->get_error_message();
        self::log_cron_run( 'failed', $end_time, $duration, $error_message, 'customer' );
        Logger::error( 'Scheduled customer sync failed: ' . $error_message, $result );
      else :
        $batch_state = $result['batch_state'] ?? [];
        $is_complete = $result['batch_complete'] ?? false;
        $total_processed = $batch_state['total_processed'] ?? 0;
        
        if ( $is_complete ) :
          $summary = sprintf(
            'Customer batch import COMPLETED. Total: %d (Created: %d, Updated: %d, Skipped: %d)',
            $total_processed,
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['skipped_count'] ?? 0
          );
        else :
          $summary = sprintf(
            'Customer batch: %d (Created: %d, Updated: %d, Skipped: %d). Total processed so far: %d. More batches remaining.',
            $result['api_count'] ?? 0,
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['skipped_count'] ?? 0,
            $total_processed
          );
        endif;
        
        self::log_cron_run( 'completed', $end_time, $duration, $summary, 'customer' );
        
        Logger::debug( 'Customer scheduled sync COMPLETED', [
          'duration' => $duration,
          'summary' => $summary,
          'next_scheduled' => wp_next_scheduled( self::CUSTOMER_HOOK ),
          'batch_complete' => $is_complete ?? false,
          'batch_state' => $batch_state,
        ] );
      endif;
      
      // Enhanced safety check: Always ensure cron is scheduled for next run if enabled
      $next_scheduled = wp_next_scheduled( self::CUSTOMER_HOOK );
      $options = CustomerSync::get_options();
      $is_enabled = ! empty( $options['customer_sync_schedule_enabled'] );
      
      Logger::debug( 'Post-execution cron check', [
        'next_scheduled' => $next_scheduled ? date( 'Y-m-d H:i:s', $next_scheduled ) : false,
        'is_enabled' => $is_enabled,
        'frequency' => $options['customer_sync_schedule_frequency'] ?? 'N/A',
      ] );
      
      if ( ! $next_scheduled && $is_enabled ) {
        Logger::warning( 'Customer cron not scheduled after execution but enabled in settings. Forcing reschedule.' );
        self::add_customer_cron_schedule();
        Logger::info( 'Customer cron rescheduled after execution.' );
      } elseif ( $next_scheduled && ! $is_enabled ) {
        Logger::warning( 'Customer cron scheduled but disabled in settings. Clearing schedule.' );
        self::clear_customer_cron_schedule();
      } elseif ( $next_scheduled && $is_enabled ) {
        // Cron is scheduled and enabled - log next run time for debugging
        $now = time();
        $time_until_next = $next_scheduled - $now;
        Logger::debug( 'Customer cron next run', [
          'next_run' => date( 'Y-m-d H:i:s', $next_scheduled ),
          'in_seconds' => $time_until_next,
          'in_minutes' => round( $time_until_next / 60, 1 ),
        ] );
      }
    }

    /**
     * Run scheduled invoice check
     *
     * @return void
     * @since 1.1.0
     */
    public static function invoice_check_scheduled(): void {
      $start_time = current_time( 'mysql' );
      $start_timestamp = current_time( 'timestamp' );
      
      // Log start
      Logger::info( 'Scheduled invoice check started', [
        'start_time' => $start_time,
      ] );
      
      $invoice_checker = InvoiceChecker::create();
      $result = $invoice_checker->check_pending_invoices();
      
      $end_time = current_time( 'mysql' );
      $end_timestamp = current_time( 'timestamp' );
      $duration = $end_timestamp - $start_timestamp;
      
      // Log completion
      Logger::info( 'Scheduled invoice check completed', [
        'end_time' => $end_time,
        'duration' => $duration,
        'result' => $result,
      ] );
      
      // Add to cron log
      if ( isset( $result['skipped'] ) && $result['skipped'] ) {
        $summary = 'Invoice check skipped: ' . ( $result['reason'] ?? 'unknown' );
      } else {
        $total_checked = count( $result['checked'] ?? [] );
        $total_fetched = count( $result['fetched'] ?? [] );
        $total_errors = count( $result['errors'] ?? [] );
        
        $summary = sprintf(
          'Invoice check completed: %d checked, %d fetched, %d errors',
          $total_checked,
          $total_fetched,
          $total_errors
        );
      }
      
      self::log_cron_run( 'completed', $end_time, $duration, $summary, 'invoice' );
    }

    /**
     * Log a cron run entry
     *
     * @param string $status started, completed, or failed
     * @param string $time MySQL datetime string
     * @param int $duration Duration in seconds (optional)
     * @param string $message Optional message/error/details
     * @param string $type Type of cron job: product, customer, invoice (default: product)
     */
    private static function log_cron_run( string $status, string $time, int $duration = 0, string $message = '', string $type = 'product' ): void {
      $logs = self::get_logs();
      
      // If this is a 'started' entry, create a new log entry
      if ( $status === 'started' ) :
        array_unshift( $logs, [
          'started_at' => $time,
          'completed_at' => null,
          'status' => 'running',
          'duration' => 0,
          'message' => '',
          'type' => $type,
        ] );
      else :
        // Update the most recent entry of the same type
        $found = false;
        foreach ( $logs as &$log ) :
          if ( $log['status'] === 'running' && $log['type'] === $type ) :
            $log['completed_at'] = $time;
            $log['status'] = $status === 'completed' ? 'success' : 'failed';
            $log['duration'] = $duration;
            $log['message'] = $message;
            $found = true;
            break;
          endif;
        endforeach;
        
        if ( ! $found ) :
          // No running entry found, create new one
          array_unshift( $logs, [
            'started_at' => $time,
            'completed_at' => $time,
            'status' => $status === 'completed' ? 'success' : 'failed',
            'duration' => $duration,
            'message' => $message,
            'type' => $type,
          ] );
        endif;
      endif;
      
      $logs = array_slice( $logs, 0, self::MAX_LOG_ENTRIES );
      
      update_option( self::LOG_OPTION_NAME, $logs, false );
    }

    /**
     * Get cron log entries
     * 
     * @return array Array of log entries
     */
    public static function get_logs(): array {
      $logs = get_option( self::LOG_OPTION_NAME, [] );
      return is_array( $logs ) ? $logs : [];
    }

    /**
     * Clear cron log entries
     */
    public static function clear_logs(): void {
      delete_option( self::LOG_OPTION_NAME );
    }
}

