<?php
/**
 * PDF Storage and Management Class
 *
 * Handles storage, retrieval, and download of Fincon PDF documents.
 * Saves base64 encoded PDFs to WordPress uploads directory with secure access.
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

class PdfStorage {

  /**
   * Singleton instance
   * 
   * @var self|null
   */
  private static ?self $instance = null;

  /**
   * PDF storage directory name (within uploads)
   * 
   * @const string
   */
  private const STORAGE_DIR = 'df-fincon-pdfs';

  /**
   * PDF file extension
   * 
   * @const string
   */
  private const FILE_EXTENSION = '.pdf';

  /**
   * Constructor (private for singleton)
   */
  private function __construct() {
    // Private constructor to enforce singleton pattern
  }

  /**
   * Create an instance
   *
   * @return self
   * @since 1.0.0
   */
  public static function create(): self {
    if ( self::$instance === null )
      self::$instance = new self();

    return self::$instance;
  }

  /**
   * Get the base storage directory path
   *
   * @return string Full path to PDF storage directory
   * @since 1.0.0
   */
  private function get_storage_dir(): string {
    $upload_dir = wp_upload_dir();
    $storage_path = trailingslashit( $upload_dir['basedir'] ) . self::STORAGE_DIR . '/';
    
    // Create directory if it doesn't exist
    if ( ! file_exists( $storage_path ) )
      wp_mkdir_p( $storage_path );
    
    // Add .htaccess for security
    $this->protect_directory( $storage_path );
    
    return $storage_path;
  }

  /**
   * Get the base storage URL
   *
   * @return string URL to PDF storage directory
   * @since 1.0.0
   */
  private function get_storage_url(): string {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['baseurl'] ) . self::STORAGE_DIR . '/';
  }

  /**
   * Protect storage directory with .htaccess
   *
   * @param string $directory_path Path to directory
   * @return void
   * @since 1.0.0
   */
  private function protect_directory( string $directory_path ): void {
    $htaccess_file = $directory_path . '.htaccess';
    
    if ( ! file_exists( $htaccess_file ) ) {
      $htaccess_content = "Order Deny,Allow\nDeny from all\n";
      file_put_contents( $htaccess_file, $htaccess_content );
    }
  }

  /**
   * Generate unique filename for PDF
   *
   * @param string $doc_type Document type (e.g., 'I' for Invoice)
   * @param string $doc_no Document number
   * @param int $order_id WooCommerce order ID
   * @return string Unique filename
   * @since 1.0.0
   */
  private function generate_filename( string $doc_type, string $doc_no, int $order_id ): string {
    $timestamp = current_time( 'Ymd_His' );
    $hash = substr( md5( $doc_type . $doc_no . $order_id . $timestamp ), 0, 8 );
    
    return sprintf(
      'fincon_%s_%s_%d_%s%s',
      strtolower( $doc_type ),
      $doc_no,
      $order_id,
      $hash,
      self::FILE_EXTENSION
    );
  }

  /**
   * Save base64 PDF to file
   *
   * @param string $base64_pdf Base64 encoded PDF content
   * @param string $doc_type Document type (e.g., 'I' for Invoice)
   * @param string $doc_no Document number
   * @param int $order_id WooCommerce order ID
   * @return array|\WP_Error Array with file info or WP_Error on failure
   * @since 1.0.0
   */
  public function save_pdf( string $base64_pdf, string $doc_type, string $doc_no, int $order_id ): array|\WP_Error {
    // Validate base64 string
    if ( empty( $base64_pdf ) )
      return new \WP_Error( 'empty_pdf', __( 'Empty PDF content provided.', 'df-fincon' ) );
    
    // Decode base64
    $pdf_content = base64_decode( $base64_pdf );
    
    if ( $pdf_content === false )
      return new \WP_Error( 'invalid_base64', __( 'Invalid base64 PDF content.', 'df-fincon' ) );
    
    // Verify it's a PDF (check for PDF header)
    if ( substr( $pdf_content, 0, 4 ) !== '%PDF' )
      return new \WP_Error( 'invalid_pdf_format', __( 'Content is not a valid PDF file.', 'df-fincon' ) );
    
    // Generate filename and path
    $filename = $this->generate_filename( $doc_type, $doc_no, $order_id );
    $storage_dir = $this->get_storage_dir();
    $file_path = $storage_dir . $filename;
    
    // Save file
    $bytes_written = file_put_contents( $file_path, $pdf_content );
    
    if ( $bytes_written === false )
      return new \WP_Error( 'file_write_error', sprintf( __( 'Failed to write PDF file to %s', 'df-fincon' ), $file_path ) );
    
    // Get file info
    $file_info = [
      'filename' => $filename,
      'path' => $file_path,
      'url' => $this->get_storage_url() . $filename,
      'size' => $bytes_written,
      'doc_type' => $doc_type,
      'doc_no' => $doc_no,
      'order_id' => $order_id,
      'saved_at' => current_time( 'mysql' ),
    ];
    
    Logger::info( 'PDF saved successfully', [
      'order_id' => $order_id,
      'doc_type' => $doc_type,
      'doc_no' => $doc_no,
      'filename' => $filename,
      'size' => $bytes_written,
      'file_path' => $file_path,
    ] );
    
    return $file_info;
  }

  /**
   * Get PDF file info for an order
   *
   * @param int $order_id WooCommerce order ID
   * @return array|null File info array or null if not found
   * @since 1.0.0
   */
  public function get_pdf_info( int $order_id ): ?array {
    $order = wc_get_order( $order_id );
    
    if ( ! $order )
      return null;
    
    $pdf_path = $order->get_meta( OrderSync::META_PDF_PATH );
    
    if ( empty( $pdf_path ) || ! file_exists( $pdf_path ) )
      return null;
    
    return [
      'path' => $pdf_path,
      'url' => $this->path_to_url( $pdf_path ),
      'filename' => basename( $pdf_path ),
      'size' => file_exists( $pdf_path ) ? filesize( $pdf_path ) : 0,
      'exists' => file_exists( $pdf_path ),
    ];
  }

  /**
   * Convert file path to URL
   *
   * @param string $file_path Full file path
   * @return string|null File URL or null if conversion fails
   * @since 1.0.0
   */
  private function path_to_url( string $file_path ): ?string {
    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit( $upload_dir['basedir'] );
    
    if ( strpos( $file_path, $base_dir ) === 0 ) {
      $relative_path = substr( $file_path, strlen( $base_dir ) );
      return trailingslashit( $upload_dir['baseurl'] ) . $relative_path;
    }
    
    return null;
  }

  /**
   * Download PDF file with proper headers
   *
   * @param string $file_path Full path to PDF file
   * @param string $download_filename Optional custom filename for download
   * @return void
   * @since 1.0.0
   */
  public function serve_pdf( string $file_path, string $download_filename = '' ): void {
    if ( ! file_exists( $file_path ) ) {
      status_header( 404 );
      wp_die( __( 'PDF file not found.', 'df-fincon' ) );
    }
    
    // Set download filename
    if ( empty( $download_filename ) )
      $download_filename = basename( $file_path );
    
    // Set headers
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $download_filename ) . '"' );
    header( 'Content-Length: ' . filesize( $file_path ) );
    header( 'Cache-Control: private, max-age=0, must-revalidate' );
    header( 'Pragma: public' );
    
    // Output file
    readfile( $file_path );
    exit;
  }

  /**
   * Generate download filename for order
   *
   * @param \WC_Order $order WooCommerce order object
   * @param string $doc_type Document type
   * @param string $doc_no Document number
   * @return string Download filename
   * @since 1.0.0
   */
  public function generate_download_filename( \WC_Order $order, string $doc_type, string $doc_no ): string {
    $order_number = $order->get_order_number();
    $date = $order->get_date_created()->format( 'Y-m-d' );
    $doc_type_label = $this->get_doc_type_label( $doc_type );
    
    return sprintf(
      '%s-%s-%s-%s%s',
      sanitize_title( get_bloginfo( 'name' ) ),
      $doc_type_label,
      $order_number,
      $date,
      self::FILE_EXTENSION
    );
  }

  /**
   * Get document type label
   *
   * @param string $doc_type Document type code
   * @return string Document type label
   * @since 1.0.0
   */
  private function get_doc_type_label( string $doc_type ): string {
    $labels = [
      'I' => 'invoice',
      'C' => 'credit-note',
      'X' => 'debit-note',
      'G' => 'goods-received',
      'T' => 'transfer',
    ];
    
    return $labels[$doc_type] ?? 'document';
  }

  /**
   * Clean up old PDF files (optional maintenance)
   *
   * @param int $days_old Delete files older than this many days
   * @return array Results of cleanup operation
   * @since 1.0.0
   */
  public function cleanup_old_files( int $days_old = 30 ): array {
    $storage_dir = $this->get_storage_dir();
    $cutoff_time = time() - ( $days_old * DAY_IN_SECONDS );
    $results = [
      'deleted' => 0,
      'failed' => 0,
      'total' => 0,
    ];
    
    $files = glob( $storage_dir . '*.pdf' );
    
    foreach ( $files as $file ) {
      $results['total']++;
      
      if ( filemtime( $file ) < $cutoff_time ) {
        if ( unlink( $file ) )
          $results['deleted']++;
        else
          $results['failed']++;
      }
    }
    
    Logger::info( 'PDF cleanup completed', $results );
    
    return $results;
  }

  /**
   * Fetch and save PDF from Fincon API
   *
   * @param int $order_id WooCommerce order ID
   * @param string $doc_type Document type (e.g., 'I' for Invoice)
   * @param string $doc_no Document number
   * @return array|\WP_Error File info array or WP_Error
   * @since 1.0.0
   */
  public function fetch_and_save_pdf( int $order_id, string $doc_type, string $doc_no ): array|\WP_Error {
    // Get PDF from Fincon API
    $fincon_api = new FinconApi();
    $api_response = $fincon_api->get_document_pdf( $doc_type, $doc_no );
    
    if ( is_wp_error( $api_response ) ) {
      Logger::error( 'Failed to fetch PDF from Fincon API', [
        'order_id' => $order_id,
        'doc_type' => $doc_type,
        'doc_no' => $doc_no,
        'error' => $api_response->get_error_message(),
      ] );
      return $api_response;
    }
    
    // Extract base64 PDF content
    $base64_pdf = $api_response['DocumentInfo']['DocumentPdf'] ?? '';
    
    if ( empty( $base64_pdf ) )
      return new \WP_Error( 'no_pdf_content', __( 'Fincon API returned empty PDF content.', 'df-fincon' ) );
    
    // Save PDF to file
    $file_info = $this->save_pdf( $base64_pdf, $doc_type, $doc_no, $order_id );
    
    if ( is_wp_error( $file_info ) )
      return $file_info;
    
    // Update order meta with PDF path (single PDF for backward compatibility)
    $order = wc_get_order( $order_id );
    if ( $order ) {
      $order->update_meta_data( OrderSync::META_PDF_PATH, $file_info['path'] );
      
      // Also update multiple PDF paths meta
      $pdf_paths = $order->get_meta( OrderSync::META_PDF_PATHS );
      if ( empty( $pdf_paths ) || ! is_array( $pdf_paths ) ) {
        $pdf_paths = [];
      }
      
      // Add this PDF to the array if not already present
      $pdf_exists = false;
      foreach ( $pdf_paths as $existing_pdf ) {
        if ( $existing_pdf['doc_no'] === $doc_no && $existing_pdf['doc_type'] === $doc_type ) {
          $pdf_exists = true;
          break;
        }
      }
      
      if ( ! $pdf_exists ) {
        $pdf_paths[] = [
          'doc_no' => $doc_no,
          'doc_type' => $doc_type,
          'path' => $file_info['path'],
          'url' => $file_info['url'],
          'filename' => $file_info['filename'],
          'saved_at' => $file_info['saved_at'],
        ];
        
        $order->update_meta_data( OrderSync::META_PDF_PATHS, $pdf_paths );
      }
      
      $order->save();
      
      Logger::info( 'PDF saved and order meta updated', [
        'order_id' => $order_id,
        'pdf_path' => $file_info['path'],
        'pdf_paths_count' => count( $pdf_paths ),
      ] );
    }
    
    return $file_info;
  }

  /**
   * Fetch and save multiple PDFs for multiple invoice numbers
   *
   * @param int $order_id WooCommerce order ID
   * @param string $doc_type Document type (e.g., 'I' for Invoice)
   * @param array $doc_numbers Array of document numbers
   * @return array|\WP_Error Array of file info arrays or WP_Error
   * @since 1.1.0
   */
  public function fetch_and_save_multiple_pdfs( int $order_id, string $doc_type, array $doc_numbers ): array|\WP_Error {
    if ( empty( $doc_numbers ) )
      return new \WP_Error( 'no_doc_numbers', __( 'No document numbers provided.', 'df-fincon' ) );
    
    $results = [
      'successful' => [],
      'failed' => [],
      'total' => count( $doc_numbers ),
    ];
    
    foreach ( $doc_numbers as $doc_no ) {
      $doc_no = trim( $doc_no );
      if ( empty( $doc_no ) )
        continue;
      
      $result = $this->fetch_and_save_pdf( $order_id, $doc_type, $doc_no );
      
      if ( is_wp_error( $result ) ) {
        $results['failed'][] = [
          'doc_no' => $doc_no,
          'error' => $result->get_error_message(),
        ];
        
        Logger::warning( 'Failed to fetch PDF for document', [
          'order_id' => $order_id,
          'doc_type' => $doc_type,
          'doc_no' => $doc_no,
          'error' => $result->get_error_message(),
        ] );
      } else {
        $results['successful'][] = [
          'doc_no' => $doc_no,
          'file_info' => $result,
        ];
        
        Logger::info( 'PDF fetched successfully for document', [
          'order_id' => $order_id,
          'doc_type' => $doc_type,
          'doc_no' => $doc_no,
          'filename' => $result['filename'],
        ] );
      }
    }
    
    // Update order meta with PDF availability status
    $order = wc_get_order( $order_id );
    if ( $order ) {
      if ( ! empty( $results['successful'] ) ) {
        $order->update_meta_data( OrderSync::META_PDF_AVAILABLE, true );
        
        // Determine invoice status
        if ( count( $results['successful'] ) > 1 ) {
          $order->update_meta_data( OrderSync::META_INVOICE_STATUS, 'multiple' );
        } else {
          $order->update_meta_data( OrderSync::META_INVOICE_STATUS, 'downloaded' );
        }
      }
      
      $order->save();
    }
    
    Logger::info( 'Multiple PDF fetch completed', [
      'order_id' => $order_id,
      'doc_type' => $doc_type,
      'total_docs' => $results['total'],
      'successful' => count( $results['successful'] ),
      'failed' => count( $results['failed'] ),
    ] );
    
    return $results;
  }

  /**
   * Get all PDFs for an order
   *
   * @param int $order_id WooCommerce order ID
   * @return array Array of PDF file info arrays
   * @since 1.1.0
   */
  public function get_all_pdfs_for_order( int $order_id ): array {
    $order = wc_get_order( $order_id );
    
    if ( ! $order )
      return [];
    
    // Try to get from META_PDF_PATHS first (new format)
    $pdf_paths = $order->get_meta( OrderSync::META_PDF_PATHS );
    
    if ( ! empty( $pdf_paths ) && is_array( $pdf_paths ) ) {
      // Validate that files still exist
      $valid_pdfs = [];
      foreach ( $pdf_paths as $pdf_info ) {
        if ( ! empty( $pdf_info['path'] ) && file_exists( $pdf_info['path'] ) ) {
          $valid_pdfs[] = $pdf_info;
        }
      }
      
      // If some files are missing, update the meta
      if ( count( $valid_pdfs ) !== count( $pdf_paths ) ) {
        $order->update_meta_data( OrderSync::META_PDF_PATHS, $valid_pdfs );
        $order->save();
      }
      
      return $valid_pdfs;
    }
    
    // Fall back to single PDF path (backward compatibility)
    $single_pdf_path = $order->get_meta( OrderSync::META_PDF_PATH );
    $single_doc_no = $order->get_meta( OrderSync::META_INVOICE_DOCNO );
    
    if ( ! empty( $single_pdf_path ) && file_exists( $single_pdf_path ) ) {
      $pdf_info = [
        'doc_no' => $single_doc_no ?: 'unknown',
        'doc_type' => 'I', // Assume invoice
        'path' => $single_pdf_path,
        'url' => $this->path_to_url( $single_pdf_path ),
        'filename' => basename( $single_pdf_path ),
        'saved_at' => filemtime( $single_pdf_path ) ? date( 'Y-m-d H:i:s', filemtime( $single_pdf_path ) ) : current_time( 'mysql' ),
      ];
      
      return [ $pdf_info ];
    }
    
    return [];
  }

  /**
   * Check if PDFs are available for an order
   *
   * @param int $order_id WooCommerce order ID
   * @return bool True if PDFs are available
   * @since 1.1.0
   */
  public function has_pdfs_for_order( int $order_id ): bool {
    $pdfs = $this->get_all_pdfs_for_order( $order_id );
    return ! empty( $pdfs );
  }

  /**
   * Get PDF download URLs for an order
   *
   * @param int $order_id WooCommerce order ID
   * @return array Array of download URLs with document info
   * @since 1.1.0
   */
  public function get_pdf_download_urls( int $order_id ): array {
    $pdfs = $this->get_all_pdfs_for_order( $order_id );
    $urls = [];
    
    foreach ( $pdfs as $pdf_info ) {
      $download_url = add_query_arg( [
        'action' => 'df_fincon_download_pdf',
        'order_id' => $order_id,
        'doc_no' => $pdf_info['doc_no'],
        'nonce' => wp_create_nonce( 'df_fincon_download_pdf_' . $order_id . '_' . $pdf_info['doc_no'] ),
      ], admin_url( 'admin-ajax.php' ) );
      
      $urls[] = [
        'doc_no' => $pdf_info['doc_no'],
        'doc_type' => $pdf_info['doc_type'],
        'url' => $download_url,
        'filename' => $pdf_info['filename'],
        'label' => $this->get_document_label( $pdf_info['doc_type'], $pdf_info['doc_no'] ),
      ];
    }
    
    return $urls;
  }

  /**
   * Get document label for display
   *
   * @param string $doc_type Document type code
   * @param string $doc_no Document number
   * @return string Human-readable document label
   * @since 1.1.0
   */
  private function get_document_label( string $doc_type, string $doc_no ): string {
    $type_labels = [
      'I' => __( 'Invoice', 'df-fincon' ),
      'C' => __( 'Credit Note', 'df-fincon' ),
      'X' => __( 'Debit Note', 'df-fincon' ),
      'G' => __( 'Goods Received', 'df-fincon' ),
      'T' => __( 'Transfer', 'df-fincon' ),
    ];
    
    $type_label = $type_labels[$doc_type] ?? __( 'Document', 'df-fincon' );
    
    return sprintf( '%s %s', $type_label, $doc_no );
  }

}