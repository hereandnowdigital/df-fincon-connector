<?php
/**
 * Invoice Management Template
 *
 * @package df-fincon-connector
 * @subpackage Templates
 * @since 1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

// Get invoice statistics
global $wpdb;

// Count orders by invoice status
$status_counts = $wpdb->get_results(
  "SELECT meta_value as status, COUNT(*) as count 
   FROM {$wpdb->postmeta} 
   WHERE meta_key = '_fincon_invoice_status' 
   GROUP BY meta_value 
   ORDER BY FIELD(meta_value, 'pending', 'available', 'downloaded', 'error', 'multiple')",
  ARRAY_A
);

// Count total synced orders
$total_synced = $wpdb->get_var(
  "SELECT COUNT(*) 
   FROM {$wpdb->postmeta} 
   WHERE meta_key = '_fincon_synced' 
   AND meta_value = '1'"
);

// Count orders with PDFs
$total_with_pdfs = $wpdb->get_var(
  "SELECT COUNT(DISTINCT post_id) 
   FROM {$wpdb->postmeta} 
   WHERE meta_key IN ('_fincon_pdf_path', '_fincon_pdf_paths') 
   AND meta_value != ''"
);

// Get recent pending invoices (last 50)
$pending_orders = $wpdb->get_results(
  "SELECT p.ID, p.post_title, pm.meta_value as order_no, pm2.meta_value as invoice_numbers, pm3.meta_value as last_check
   FROM {$wpdb->posts} p
   INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_fincon_order_no'
   INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_fincon_invoice_status' AND pm2.meta_value = 'pending'
   LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_fincon_invoice_last_check'
   WHERE p.post_type = 'shop_order'
   ORDER BY p.post_date DESC
   LIMIT 50",
  ARRAY_A
);

// Get recent available invoices (last 20)
$available_orders = $wpdb->get_results(
  "SELECT p.ID, p.post_title, pm.meta_value as order_no, pm2.meta_value as invoice_numbers
   FROM {$wpdb->posts} p
   INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_fincon_order_no'
   INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_fincon_invoice_status' AND pm2.meta_value IN ('available', 'multiple', 'downloaded')
   WHERE p.post_type = 'shop_order'
   ORDER BY p.post_date DESC
   LIMIT 20",
  ARRAY_A
);

// Status colors for badges
$status_colors = [
  'pending' => '#ff9800',
  'available' => '#4caf50',
  'downloaded' => '#2196f3',
  'error' => '#f44336',
  'multiple' => '#9c27b0',
];

$status_labels = [
  'pending' => __( 'Pending', 'df-fincon' ),
  'available' => __( 'Available', 'df-fincon' ),
  'downloaded' => __( 'Downloaded', 'df-fincon' ),
  'error' => __( 'Error', 'df-fincon' ),
  'multiple' => __( 'Multiple', 'df-fincon' ),
];

?>
<div class="wrap">
  <h1><?php esc_html_e( 'Invoice Management', 'df-fincon' ); ?></h1>
  
  <div class="card">
    <h2><?php esc_html_e( 'Invoice Statistics', 'df-fincon' ); ?></h2>
    
    <div class="invoice-stats" style="display: flex; gap: 20px; margin-bottom: 30px;">
      <div class="stat-box" style="flex: 1; background: #f6f7f7; padding: 15px; border-radius: 4px; border: 1px solid #c3c4c7;">
        <h3 style="margin-top: 0;"><?php esc_html_e( 'Total Synced Orders', 'df-fincon' ); ?></h3>
        <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $total_synced ?: 0 ); ?></div>
        <p class="description"><?php esc_html_e( 'Orders synced to Fincon', 'df-fincon' ); ?></p>
      </div>
      
      <div class="stat-box" style="flex: 1; background: #f6f7f7; padding: 15px; border-radius: 4px; border: 1px solid #c3c4c7;">
        <h3 style="margin-top: 0;"><?php esc_html_e( 'Orders with PDFs', 'df-fincon' ); ?></h3>
        <div style="font-size: 32px; font-weight: bold; color: #4caf50;"><?php echo esc_html( $total_with_pdfs ?: 0 ); ?></div>
        <p class="description"><?php esc_html_e( 'Invoices downloaded as PDF', 'df-fincon' ); ?></p>
      </div>
      
      <div class="stat-box" style="flex: 1; background: #f6f7f7; padding: 15px; border-radius: 4px; border: 1px solid #c3c4c7;">
        <h3 style="margin-top: 0;"><?php esc_html_e( 'Pending Invoices', 'df-fincon' ); ?></h3>
        <div style="font-size: 32px; font-weight: bold; color: #ff9800;">
          <?php 
          $pending_count = 0;
          foreach ( $status_counts as $row ) {
            if ( $row['status'] === 'pending' ) {
              $pending_count = $row['count'];
              break;
            }
          }
          echo esc_html( $pending_count );
          ?>
        </div>
        <p class="description"><?php esc_html_e( 'Awaiting invoice generation', 'df-fincon' ); ?></p>
      </div>
    </div>
    
    <h3><?php esc_html_e( 'Invoice Status Breakdown', 'df-fincon' ); ?></h3>
    
    <?php if ( ! empty( $status_counts ) ) : ?>
      <table class="widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Status', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Count', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Percentage', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'df-fincon' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $status_counts as $row ) : 
            $percentage = $total_synced > 0 ? round( ( $row['count'] / $total_synced ) * 100, 1 ) : 0;
            $status_color = $status_colors[$row['status']] ?? '#757575';
            $status_label = $status_labels[$row['status']] ?? ucfirst( $row['status'] );
          ?>
            <tr>
              <td>
                <span class="invoice-status-badge" style="
                  display: inline-block;
                  padding: 3px 8px;
                  border-radius: 12px;
                  background-color: <?php echo esc_attr( $status_color ); ?>;
                  color: white;
                  font-size: 12px;
                  font-weight: bold;
                  text-transform: uppercase;
                ">
                  <?php echo esc_html( $status_label ); ?>
                </span>
              </td>
              <td><?php echo esc_html( $row['count'] ); ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div style="flex: 1; background: #e0e0e0; height: 10px; border-radius: 5px;">
                    <div style="width: <?php echo esc_attr( $percentage ); ?>%; background-color: <?php echo esc_attr( $status_color ); ?>; height: 100%; border-radius: 5px;"></div>
                  </div>
                  <span><?php echo esc_html( $percentage ); ?>%</span>
                </div>
              </td>
              <td>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order&_fincon_invoice_status=' . $row['status'] ) ); ?>" class="button button-small">
                  <?php esc_html_e( 'View Orders', 'df-fincon' ); ?>
                </a>
                <?php if ( $row['status'] === 'pending' ) : ?>
                  <button type="button" class="button button-small button-secondary bulk-check-invoices" data-status="<?php echo esc_attr( $row['status'] ); ?>">
                    <?php esc_html_e( 'Check All', 'df-fincon' ); ?>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else : ?>
      <p><?php esc_html_e( 'No invoice status data available.', 'df-fincon' ); ?></p>
    <?php endif; ?>
  </div>
  
  <div class="card" style="margin-top: 20px;">
    <h2><?php esc_html_e( 'Pending Invoices', 'df-fincon' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Orders that have been synced to Fincon but invoices are not yet available. These require manual approval in Fincon.', 'df-fincon' ); ?></p>
    
    <?php if ( ! empty( $pending_orders ) ) : ?>
      <table class="widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Order', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Fincon Order No', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Last Check', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'df-fincon' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $pending_orders as $order ) : 
            $order_edit_url = admin_url( 'post.php?post=' . $order['ID'] . '&action=edit' );
            $last_check = $order['last_check'] ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order['last_check'] ) ) : __( 'Never', 'df-fincon' );
          ?>
            <tr>
              <td>
                <strong>#<?php echo esc_html( $order['ID'] ); ?></strong><br>
                <span class="description"><?php echo esc_html( $order['post_title'] ); ?></span>
              </td>
              <td><code><?php echo esc_html( $order['order_no'] ); ?></code></td>
              <td><?php echo esc_html( $last_check ); ?></td>
              <td>
                <a href="<?php echo esc_url( $order_edit_url ); ?>" class="button button-small">
                  <?php esc_html_e( 'View Order', 'df-fincon' ); ?>
                </a>
                <button type="button" class="button button-small button-secondary check-invoice-single" 
                        data-order-id="<?php echo esc_attr( $order['ID'] ); ?>"
                        data-order-no="<?php echo esc_attr( $order['order_no'] ); ?>">
                  <?php esc_html_e( 'Check Now', 'df-fincon' ); ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      
      <div style="margin-top: 15px;">
        <button type="button" class="button button-primary bulk-check-pending">
          <?php esc_html_e( 'Check All Pending Invoices', 'df-fincon' ); ?>
        </button>
        <span class="spinner" style="float: none; margin-left: 5px;"></span>
        <div id="bulk-check-result" style="margin-top: 10px; display: none;"></div>
      </div>
    <?php else : ?>
      <p><?php esc_html_e( 'No pending invoices found.', 'df-fincon' ); ?></p>
    <?php endif; ?>
  </div>
  
  <div class="card" style="margin-top: 20px;">
    <h2><?php esc_html_e( 'Available Invoices', 'df-fincon' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Orders with invoices available for download.', 'df-fincon' ); ?></p>
    
    <?php if ( ! empty( $available_orders ) ) : ?>
      <table class="widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Order', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Fincon Order No', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Invoice Numbers', 'df-fincon' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'df-fincon' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $available_orders as $order ) : 
            $order_edit_url = admin_url( 'post.php?post=' . $order['ID'] . '&action=edit' );
            $invoice_numbers = $order['invoice_numbers'] ?: '';
          ?>
            <tr>
              <td>
                <strong>#<?php echo esc_html( $order['ID'] ); ?></strong><br>
                <span class="description"><?php echo esc_html( $order['post_title'] ); ?></span>
              </td>
              <td><code><?php echo esc_html( $order['order_no'] ); ?></code></td>
              <td>
                <?php if ( $invoice_numbers ) : ?>
                  <code><?php echo esc_html( $invoice_numbers ); ?></code>
                  <?php if ( strpos( $invoice_numbers, ',' ) !== false ) : ?>
                    <span class="description"><?php esc_html_e( '(multiple)', 'df-fincon' ); ?></span>
                  <?php endif; ?>
                <?php else : ?>
                  <span class="description"><?php esc_html_e( 'N/A', 'df-fincon' ); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?php echo esc_url( $order_edit_url ); ?>" class="button button-small">
                  <?php esc_html_e( 'View Order', 'df-fincon' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=df_fincon_download_pdf&order_id=' . $order['ID'] . '&nonce=' . wp_create_nonce( 'df_fincon_download_pdf_' . $order['ID'] ) ) ); ?>" class="button button-small button-primary" target="_blank">
                  <?php esc_html_e( 'Download PDF', 'df-fincon' ); ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else : ?>
      <p><?php esc_html_e( 'No available invoices found.', 'df-fincon' ); ?></p>
    <?php endif; ?>
  </div>
  
  <div class="card" style="margin-top: 20px;">
    <h2><?php esc_html_e( 'Bulk Operations', 'df-fincon' ); ?></h2>
    
    <div class="bulk-operations" style="background: #f6f7f7; padding: 15px; border: 1px solid #c3c4c7; border-radius: 4px;">
      <h3><?php esc_html_e( 'Check Pending Invoices', 'df-fincon' ); ?></h3>
      <p><?php esc_html_e( 'Manually trigger invoice checking for all pending orders. This will query Fincon to see if invoices have been approved and are available for download.', 'df-fincon' ); ?></p>
      
      <div style="margin-bottom: 15px;">
        <label for="batch_size"><?php esc_html_e( 'Batch Size:', 'df-fincon' ); ?></label>
        <input type="number" id="batch_size" name="batch_size" value="10" min="1" max="50" style="width: 80px;">
        <p class="description"><?php esc_html_e( 'Number of orders to check at once (to avoid API rate limits)', 'df-fincon' ); ?></p>
      </div>
      
      <button type="button" class="button button-primary" id="start-bulk-check">
        <?php esc_html_e( 'Start Bulk Invoice Check', 'df-fincon' ); ?>
      </button>
      <span class="spinner" style="float: none; margin-left: 5px;"></span>
      
      <div id="bulk-operation-progress" style="margin-top: 15px; display: none;">
        <div class="progress-bar" style="background: #e0e0e0; height: 20px; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
          <div id="progress-bar-fill" style="background: #4caf50; height: 100%; width: 0%; transition: width 0.3s;"></div>
        </div>
        <div id="progress-text" style="text-align: center; font-weight: bold;"></div>
        <div id="progress-details" style="margin-top: 10px; font-size: 12px; color: #666;"></div>
      </div>
      
      <div id="bulk-operation-result" style="margin-top: 15px; display: none;"></div>
    </div>
    
    <h3 style="margin-top: 30px;"><?php esc_html_e( 'Cron Settings', 'df-fincon' ); ?></h3>
    <p><?php esc_html_e( 'Configure automatic invoice checking via cron job.', 'df-fincon' ); ?></p>
    
    <div style="background: #fff; padding: 15px; border: 1px solid #c3c4c7; border-radius: 4px;">
      <p>
        <strong><?php esc_html_e( 'Current Status:', 'df-fincon' ); ?></strong>
        <?php
        // Get the invoice check hook constant
        $invoice_check_hook = 'df_fincon_check_invoices';
        if ( wp_next_scheduled( $invoice_check_hook ) ) : ?>
          <span style="color: #4caf50;">✓ <?php esc_html_e( 'Scheduled', 'df-fincon' ); ?></span>
          <br>
          <span class="description">
            <?php
            $next_run = wp_next_scheduled( $invoice_check_hook );
            printf( __( 'Next check: %s', 'df-fincon' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) );
            ?>
          </span>
        <?php else : ?>
          <span style="color: #f44336;">✗ <?php esc_html_e( 'Not scheduled', 'df-fincon' ); ?></span>
        <?php endif; ?>
      </p>
      
      <div style="margin-top: 15px;">
        <button type="button" class="button button-secondary" id="schedule-invoice-check">
          <?php esc_html_e( 'Schedule Invoice Check', 'df-fincon' ); ?>
        </button>
        <button type="button" class="button button-secondary" id="unschedule-invoice-check">
          <?php esc_html_e( 'Unschedule Invoice Check', 'df-fincon' ); ?>
        </button>
        <span class="spinner" style="float: none; margin-left: 5px;"></span>
        <div id="cron-result" style="margin-top: 10px; display: none;"></div>
      </div>
    </div>
  </div>
</div>

<script>
jQuery(document).ready(function($) {
  // Single invoice check
  $('.check-invoice-single').on('click', function() {
    var $button = $(this);
    var $row = $button.closest('tr');
    var orderId = $button.data('order-id');
    var orderNo = $button.data('order-no');
    
    $button.prop('disabled', true).text('<?php esc_html_e( 'Checking...', 'df-fincon' ); ?>');
    
    $.post(ajaxurl, {
      action: 'df_fincon_check_invoice_status',
      order_id: orderId,
      order_no: orderNo,
      nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_check_invoice_status' ) ); ?>'
    }, function(response) {
      if (response.success) {
        $row.find('td:last').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
        setTimeout(function() {
          location.reload();
        }, 1500);
      } else {
        $button.prop('disabled', false).text('<?php esc_html_e( 'Check Now', 'df-fincon' ); ?>');
        alert('Error: ' + (response.data.message || '<?php esc_html_e( 'Unknown error', 'df-fincon' ); ?>'));
      }
    }).fail(function() {
      $button.prop('disabled', false).text('<?php esc_html_e( 'Check Now', 'df-fincon' ); ?>');
      alert('<?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?>');
    });
  });
  
  // Bulk check pending invoices
  $('.bulk-check-pending').on('click', function() {
    var $button = $(this);
    var $spinner = $button.next('.spinner');
    var $result = $('#bulk-check-result');
    
    $button.prop('disabled', true);
    $spinner.addClass('is-active');
    $result.hide().empty();
    
    $.post(ajaxurl, {
      action: 'df_fincon_bulk_check_invoices',
      nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_bulk_check_invoices' ) ); ?>'
    }, function(response) {
      $spinner.removeClass('is-active');
      
      if (response.success) {
        var html = '<div style="color: green;">' + response.data.message + '</div>';
        if (response.data.checked_count > 0) {
          html += '<div style="margin-top: 5px;">' +
                  '<?php esc_html_e( 'Checked', 'df-fincon' ); ?>: ' + response.data.checked_count +
                  '</div>';
        }
        $result.html(html).show();
        
        // Reload after a delay
        setTimeout(function() {
          location.reload();
        }, 2000);
      } else {
        $result.html('<div style="color: red;">' + response.data.message + '</div>').show();
        $button.prop('disabled', false);
      }
    }).fail(function() {
      $spinner.removeClass('is-active');
      $result.html('<div style="color: red;"><?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?></div>').show();
      $button.prop('disabled', false);
    });
  });
  
  // Start bulk check with progress
  $('#start-bulk-check').on('click', function() {
    var $button = $(this);
    var $spinner = $button.next('.spinner');
    var $progress = $('#bulk-operation-progress');
    var $result = $('#bulk-operation-result');
    var batchSize = $('#batch_size').val();
    
    $button.prop('disabled', true);
    $spinner.addClass('is-active');
    $progress.show();
    $result.hide().empty();
    
    $('#progress-bar-fill').css('width', '0%');
    $('#progress-text').text('<?php esc_html_e( 'Starting...', 'df-fincon' ); ?>');
    $('#progress-details').empty();
    
    function processBatch(offset) {
      $.post(ajaxurl, {
        action: 'df_fincon_bulk_check_invoices_batch',
        batch_size: batchSize,
        offset: offset,
        nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_bulk_check_invoices_batch' ) ); ?>'
      }, function(response) {
        if (response.success) {
          var data = response.data;
          var progress = data.progress || 0;
          
          $('#progress-bar-fill').css('width', progress + '%');
          $('#progress-text').text(data.message || '');
          $('#progress-details').html(
            '<?php esc_html_e( 'Processed', 'df-fincon' ); ?>: ' + data.processed + ' / ' + data.total +
            ' | <?php esc_html_e( 'Updated', 'df-fincon' ); ?>: ' + data.updated
          );
          
          if (data.completed) {
            $spinner.removeClass('is-active');
            $result.html('<div style="color: green;">' + data.final_message + '</div>').show();
            $button.prop('disabled', false);
            
            // Reload after a delay
            setTimeout(function() {
              location.reload();
            }, 3000);
          } else {
            // Continue with next batch
            setTimeout(function() {
              processBatch(data.next_offset);
            }, 1000);
          }
        } else {
          $spinner.removeClass('is-active');
          $result.html('<div style="color: red;">' + response.data.message + '</div>').show();
          $button.prop('disabled', false);
        }
      }).fail(function() {
        $spinner.removeClass('is-active');
        $result.html('<div style="color: red;"><?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?></div>').show();
        $button.prop('disabled', false);
      });
    }
    
    // Start first batch
    processBatch(0);
  });
  
  // Schedule invoice check cron
  $('#schedule-invoice-check').on('click', function() {
    var $button = $(this);
    var $spinner = $button.next('.spinner');
    var $result = $('#cron-result');
    
    $button.prop('disabled', true);
    $spinner.addClass('is-active');
    $result.hide().empty();
    
    $.post(ajaxurl, {
      action: 'df_fincon_schedule_invoice_check',
      nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_schedule_invoice_check' ) ); ?>'
    }, function(response) {
      $spinner.removeClass('is-active');
      
      if (response.success) {
        $result.html('<div style="color: green;">' + response.data.message + '</div>').show();
        setTimeout(function() {
          location.reload();
        }, 1500);
      } else {
        $result.html('<div style="color: red;">' + response.data.message + '</div>').show();
        $button.prop('disabled', false);
      }
    }).fail(function() {
      $spinner.removeClass('is-active');
      $result.html('<div style="color: red;"><?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?></div>').show();
      $button.prop('disabled', false);
    });
  });
  
  // Unschedule invoice check cron
  $('#unschedule-invoice-check').on('click', function() {
    var $button = $(this);
    var $spinner = $button.next('.spinner');
    var $result = $('#cron-result');
    
    $button.prop('disabled', true);
    $spinner.addClass('is-active');
    $result.hide().empty();
    
    $.post(ajaxurl, {
      action: 'df_fincon_unschedule_invoice_check',
      nonce: '<?php echo esc_js( wp_create_nonce( 'df_fincon_unschedule_invoice_check' ) ); ?>'
    }, function(response) {
      $spinner.removeClass('is-active');
      
      if (response.success) {
        $result.html('<div style="color: green;">' + response.data.message + '</div>').show();
        setTimeout(function() {
          location.reload();
        }, 1500);
      } else {
        $result.html('<div style="color: red;">' + response.data.message + '</div>').show();
        $button.prop('disabled', false);
      }
    }).fail(function() {
      $spinner.removeClass('is-active');
      $result.html('<div style="color: red;"><?php esc_html_e( 'AJAX request failed', 'df-fincon' ); ?></div>').show();
      $button.prop('disabled', false);
    });
  });
});
</script>

<style>
.invoice-status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 12px;
  color: white;
  font-size: 12px;
  font-weight: bold;
  text-transform: uppercase;
}
.stat-box h3 {
  margin-top: 0;
  color: #1d2327;
}
.bulk-operations label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
}
.bulk-operations input[type="number"] {
  margin-bottom: 10px;
}
</style>
</div>