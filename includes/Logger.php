<?php
  /**
   * WooCommerce logger
   *
   * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
   * @package df-fincon-connector
   * Text Domain: df-fincon
   * 
   */

namespace DF_FINCON;

if ( ! defined( constant_name: 'ABSPATH' ) ) 
	exit;

class Logger {

	/**
   * Singleton instance
   * @var self|null
   */
  private static ?self $instance = null;

	/**
	 * WooCommerce logger instance.
	 *
	 * @var \WC_Logger
	 */
	protected \WC_Logger $logger;


	const SOURCE = Plugin::SLUG;


	/**
	 * Constructor.
	 *
	 * @param string $source Optional log source name.
	 */
	public function __construct( protected bool $enabled = true ) {
		if ( $this->enabled ) 
			$this->logger = wc_get_logger();
	}

   public static function create( $enabled = true ): self {
    if ( self::$instance === null ) {
      self::$instance = new self( $enabled );
    }
    return self::$instance;
  }


    /**
     * Get instance
     */
    private static function get_instance(): self {
      if ( self::$instance === null ) 
        wp_die('Logger not initialized. Call woo_logger::create($source) first.');
      return self::$instance;
    }

	/**
	 * Write a log entry.
	 *
	 * @param string $level Log level: emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message The message to log.
	 * @param array  $context Optional additional context.
	 */
  private static function log(string $level, string $message, mixed $context = []): void {
      $instance = self::get_instance();
      if ( !$instance->enabled ) return;

      $instance->logger->log($level, $message, array_merge([ 'source' => $instance::SOURCE ], self::normalize_context_data($context) ) );
  }

  /**
   * Safely converts mixed context data into a usable array for WC_Logger.
   *
   * @param mixed $data The variable to convert.
   * @return array The array representation of the context data.
   */
  private static function normalize_context_data( mixed $data ): array {
    if ( is_array($data) ) 
      return $data;    
    
    if ( is_object($data) ) 
      return (array) $data;

    if ( is_string($data) || is_numeric($data) || is_bool($data) || is_null($data) ) 
      return [ $data ];
    
    return [ 'unsupported_context_type' => gettype($data) ];
  }

	public static function emergency ( $message, mixed $context = [] ) {
		self::log( 'emergency', $message, $context );
	}

	public static function alert ( $message, mixed $context = [] ) {
		self::log( 'alert', $message, $context );
	}

	public static function critical ( $message, mixed $context = [] ) {
		self::log( 'critical', $message, $context );
	}

	public static function error ( $message, mixed $context = [] ) {
		self::log( 'error', $message, $context );
	}

	public static function warning ( $message, mixed $context = [] ) {
		self::log( 'warning', $message, $context );
	}

	public static function notice ( $message, mixed $context = [] ) {
		self::log( 'notice', $message, $context );
	}

	public static function info ( $message, mixed $context = [] ) {
		self::log( 'info', $message, $context );
	}

	public static function debug ( $message, mixed $context = [], $backtrace = false ) {
		if ( $backtrace ) { 
			$backtrace = debug_backtrace();
			$message .= $backtrace[0] ? ' (LINE ' . $backtrace[0]['line'] . ', FUNCTION: ' . $backtrace[0]['function'] . ', FILE:' . $backtrace[0]['file'] . ')' : '';
		}
		self::log( 'debug', $message, $context );
	}

}
