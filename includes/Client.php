<?php
  /**
   * HTTP API Request Client
   *
   * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
   * @package df-fincon-connector
   * Text Domain: df-fincon
   * */

namespace DF_FINCON;

use \WP_Error;

if ( ! defined( 'ABSPATH' ) ) 
    exit;

class Client {

  /**
   * Enable / disable API request log.
   * @var bool
   */
  public bool $log_enabled;

  /**
   * HTTP request content type
   * @var string
   */
  public string $contenttype = 'application/json';

  /**
   * HTTP request accept type
   * @var string
   */
  public string $accept = 'application/json';

  /**
   * HTTP request timeout
   * @var int
   */
  public int $timeout = 20;

  /**
   * HTTP request method
   * @var string
   */
  public string $method = 'GET';

  /**
   * Auth Header
   * E.g. $auth_header = 'Basic ' . base64_encode( "{$username}:{$password}" );
   * @var string
   */
  public string $auth_header = '';

  /**
   * HTTP request path
   * @var string
   */
  public string $path = '';

  /**
   * HTTP request body
   * @var array
   */
  public array $request_body = [];

  /**
   * HTTP request body JSON
   * @var string
   */
  public string $request_body_json = '';

 /**
   * HTTP response
   * @var array
   */
  public mixed $response = null;

  /**
   * HTTP response code
   * @var int
   */
  public int $response_code = 0;

  /**
   * HTTP response body JSON
   * @var string
   */
  public string $response_body = '';

  /**
   * Constructor
   */
  public function __construct( $log_enabled = false ) {
    $this->log_enabled = $log_enabled;
  }

  public function request( ): array|\WP_Error {
    $this->request_body_json = '';
    if ( $this->request_body )
        $this->request_body_json = wp_json_encode( $this->request_body );

    $args = [
      'url'        => $this->path,
      'method'      => $this->method,
      'headers'     => [
          'Content-Type' => $this->contenttype,
          'Accept'       => $this->accept,
      ],
      'body'        => $this->request_body_json,
      'timeout'     => $this->timeout,
    ];
    
    if ( !empty( $this->auth_header ) )
      $args['headers']['Authorization'] = $this->auth_header;

    $this->log( 'API Request: ', $args );

    $this->response = wp_remote_request( $this->path, $args );
    $this->log( 'API Response: ', $this->response );

    if ( is_wp_error( $this->response ) )
      return $this->response;

    $this->response_code = wp_remote_retrieve_response_code( $this->response );

    if ( $this->response_code < 200 || $this->response_code >= 300 )
      return new \WP_Error( 'http_error', 'Unexpected HTTP status: ' . $this->response_code, $this->response );

    $this->response_body = wp_remote_retrieve_body( $this->response );
    $decoded = json_decode( $this->response_body, true );
    
    if ( $decoded === null && $this->response_body !== '' && $this->response_body !== 'null' )
      return new \WP_Error( 'json_decode_error', 'Failed to decode JSON response: ' . $this->response_body );
    
    return $decoded ?? [];
  }

  private function log( $message = '', $context = [] ) {
    if ( $this->log_enabled )
      Logger::debug( $message, $context );
  }

}

